---
title: "Building ReactPHP Memcached Client: Unit-Testing Promises"
tags: [PHP, Event-Driven Programming, ReactPHP, Memcached, Unit-Testing]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client part 4: unit-testing asynchronous promises"
image: "/assets/images/posts/reactphp-memcached/logo4.png"
---

>This is the last article from the series about building from scratch a streaming Memcached PHP client for ReactPHP ecosystem. The library is already released and published, you can find it on [GitHub](https://github.com/seregazhuk/php-react-memcached){:target="_blank"}.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-memcached/logo4.png" alt="logo" class="">
</p>

In the [previous article]({% post_url 2017-11-03-memcached-reactphp-p3 %}){:target="_blank"}, we have completely finished with the source code for async Memcached ReactPHP client. And now it's time to start testing it. The client has a promise-base interface:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory
    ->createClient('localhost:11211')
    ->then(function (Client $client) {
        $client->set('example', 'Hello world')
            ->then(function() {
                // the value was set
            });

        $client->get('example')
            ->then(function ($data) {
                // handle data
            });
});

$loop->run();
{% endhighlight %}

And how should we test this code? Should we create an event loop and `run()` it in every test? Or we don't need it at all? Let's figure it out.

### Preparing

It is necessary to decide what we are going to test. From the consumer's point of view, the client returns promises, that can be resolved or rejected. And actually, all client methods return promises. So, we need to test that in some conditions these promises are resolved and in others - are rejected. Also, we can additionally check resolved values and rejection reasons.
As an example, let's test that the client resolves a pending request promise with the response from the server. 

Under the hood, the client uses a duplex stream for communication with a server, but we are going to test the client code in isolation, so this dependency will be mocked. 

>*For mocking objects I'm going to use a very popular [Mockery](https://github.com/mockery/mockery){:target="_blank"} library. It has very clear and intuitive interface, but if you meet some difficulties check out its [official documentation](http://docs.mockery.io/en/latest/){:target="_blank"}.*

So, we start with an empty test class:

{% highlight php %}
<?php

namespace seregazhuk\React\Memcached\tests;

use Mockery;
use Mockery\MockInterface;
use React\Stream\DuplexStreamInterface;
use seregazhuk\React\Memcached\Client;
use seregazhuk\React\Memcached\Exception\Exception;
use seregazhuk\React\Memcached\Protocol\Parser;
use seregazhuk\React\Memcached\Protocol\Request\Factory as RequestFactory;
use seregazhuk\React\Memcached\Protocol\Response\Factory as ResponseFactory;

class StreamingClientTest extends TestCase
{
    // ...
}
{% endhighlight %}

The first thing we need to do is to set up the client and its dependencies:

{% highlight php %}
<?php

class StreamingClientTest extends TestCase
{
    /**
     * @var DuplexStreamInterface|MockInterface
     */
    protected $stream;

    /**
     * @var Client
     */
    protected $client;


    protected function setUp()
    {
        $this->stream = Mockery::mock(DuplexStreamInterface::class)->shouldReceive('on')->getMock();
        $this->client = new Client($this->stream, new Parser());

        parent::setUp();
    }

    // ...
}
{% endhighlight %}

`setUp()` method creates a mock and instantiates an instance of the client. Here is the source code of the client constructor:

{% highlight php %}
<?php

class Client 
{
    /**
     * @param DuplexStreamInterface $stream
     * @param Parser $parser
     */
    public function __construct(DuplexStreamInterface $stream, Parser $parser)
    {
        $this->stream = $stream;
        $this->parser = $parser;

        $stream->on('data', function ($chunk) {
            $parsed = $this->parser->parseRawResponse($chunk);
            $this->resolveRequests($parsed);
        });

        $stream->on('close', function() {
            if(!$this->isEnding) {
                $this->emit('error', [new ConnectionClosedException()]);
                $this->close();
            }
        });
    }
}
{% endhighlight %}

As you can see, it immediately calls `on()` method on the stream to attach some event handlers to `data` and `close` events. That's why I've set an expectation for `on()` method on the stream mock: `shouldReceive('on')`. Before implementing tests we need to add one more thing - a trait:

{% highlight php %}
<?php

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class StreamingClientTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    // ...
    
}
{% endhighlight %}

This trait is necessary for PHPUnit to assert mocked expectations. By default PHPUnit doesn't count Mockery assertions and if there are no `$this->assert*` calls in the test class, PHPUnit will report: `This test did not perform any assertions`. So we should include this integration trait in the test class. When all these preparations are done we can move on to writing tests.

## Assert Promise Resolves

So, we start with a simple test. It will check that the client resolves a promise from the request with a response data from the server. For these purposes, we will use client's `version()` method (that returns Memcached server version), because it is very simple and has no arguments. The scenario is the following:

1. We call `version()` method.
2. We assert that the promise from `version()` method was resolved with the value of `12345`.

To set mock expectations we need to refresh in memory what happens under the hood when we call `version()` method on the client. The `Client` class has no such method, and for all Memcached commands it actually uses magic `__call()` method:

{% highlight php %}
<?phph

class Client 
{
    // ...

    /**
     * @param string $name
     * @param array $args
     * @return Promise|PromiseInterface
     */
    public function __call($name, $args)
    {
        $request = new Request($name);

        if($this->isEnding) {
            $request->reject(new ConnectionClosedException());
        } else {
            try {
                $query = $this->parser->makeRequest($name, $args);
                $this->stream->write($query);
                $this->requests[] = $request;
            } catch (WrongCommandException $e) {
                $request->reject($e);
            }
        }

        return $request->getPromise();
    }
}
{% endhighlight %}

In our test, we have mocked instance of `$this->stream`. So, we start our test with setting up expectations for this mock:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    /** @test */
    public function it_resolves_a_promise_with_data_from_response()
    {
        $this->stream->shouldReceive('write')->once();
        $promise = $this->client->version();

       // ...
    }    
}
{% endhighlight %}

The code above can be described like this: 

>*When we call `version()` on the client, it should call `write()` method on the stream. Then we call `version()` method, which returns a promise.*

And here comes the main section of this article: *how to test a promise*. In this particular test, we need to check that the promise resolves with the data from the server. We assume, that the server has returned a string `12345` as a server version. To pass server responses to the client we can use `resolveRequests()` method. It accepts an array of responses and uses them to resolve pending requests:


{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    /** @test */
    public function it_resolves_a_promise_with_data_from_response()
    {
        $this->stream->shouldReceive('write')->once();
        $promise = $this->client->version();

        $this->client->resolveRequests(['12345']);
        // assert that promise resolves with `12345`
    }    
}
{% endhighlight %}

The last step is assertion. Tests run synchronously, so we need to *wait* for a promise to be resolved. Then we get the resolved value and assert it with an expectation. For *waiting* (or running promises in a synchronous way) there is a nice library [clue/php-block-react](https://github.com/clue/php-block-react){:target="_blank"} from [Christian Lück](https://twitter.com/another_clue){:target="_blank"}. This library can be used for running ReactPHP async components in a traditional synchronous way - exactly what we need. After installing this library we have an access to a set of functions from `Clue\React\Block` namespace. One of them is `await()`:

`await(PromiseInterface $promise, LoopInterface $loop, $timeout = null)`

It accepts a promise, an instance of the event loop, and a timeout to wait. When the promise is resolved this function returns a resolved value. If the promise rejects or timeout is out, this function throws an exception. In our case we don't have an event loop, so let's create one. It will be used in many tests, so I'm going to instantiate it in `setUp()` method:

{% highlight php %}
<?php

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use React\EventLoop\Factory as LoopFactory;

class StreamingClientTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var DuplexStreamInterface|MockInterface
     */
    protected $stream;

    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        $this->loop = LoopFactory::create();

        $this->stream = Mockery::mock(DuplexStreamInterface::class)->shouldReceive('on')->getMock();
        $this->client = new Client($this->stream, new Parser());

        parent::setUp();
    }
}
{% endhighlight %}

Now, the whole test looks like this:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    /** @test */
    public function it_resolves_a_promise_with_data_from_response()
    {
        $this->stream->shouldReceive('write')->once();
        $promise = $this->client->version();

        $this->client->resolveRequests(['12345']);
        $resolvedValue = Block\await($promise, $this->loop);
        $this->assertEquals('12345', $resolvedValue);
    }    
}
{% endhighlight %}

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promise-resolved-success.png" alt="testing-promise-resolved-success" class="">
    </p>
</div>

To prove that the test actually tests the promise let's change `assertEquals()` expectation and see what happens:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    /** @test */
    public function it_resolves_a_promise_with_data_from_response()
    {
        $this->stream->shouldReceive('write')->once();
        $promise = $this->client->version();

        $this->client->resolveRequests(['12345']);
        $resolvedValue = Block\await($promise, $this->loop);
        $this->assertEquals('some-value', $resolvedValue);
    }    
}
{% endhighlight %}

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promise-resolved-fail.png" alt="testing-promise-resolved-fail" class="">
    </p>
</div>

As being expected the test fails, that means that assertions work fine.

Now, we can extract a custom assertion from it, so the test will look more explicit for the reader:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    self::DEFAULT_WAIT_TIMEOUT = 2;

    /** @test */
    public function it_resolves_a_promise_with_data_from_response()
    {
        $this->stream->shouldReceive('write')->once();
        $promise = $this->client->version();

        $this->client->resolveRequests(['12345']);
        $this->assertPromiseResolvesWith($promise, '12345');
    }    

    public function assertPromiseResolvesWith(PromiseInterface $promise, $value, $timeout = null)
    {
        $failMessage = 'Failed asserting that promise resolves with a specified value. ';

        try {
            $result = Block\await($promise, $this->loop, $timeout ?: self::DEFAULT_WAIT_TIMEOUT);
        } catch (TimeoutException $exception) {
            $this->fail($failMessage . 'Promise was rejected by timeout.');
        } catch (Exception $exception) {
            $this->fail($failMessage . 'Promise was rejected.');
        }

        $this->assertEquals($value, $result, $failMessage);
    }
}
{% endhighlight %}

We have extracted custom `assertPromiseResolvesWith()` assertion. It tries to resolve a promise. If the promise is resolved it checks the resolved value with an expected one. If the promise is rejected the test fails with a nice clear message. By default, this assertion waits for 2 seconds, because without `$timeout` `Block\await()` function is going to wait endlessly.

For example, if our promise rejects we will get a nice message explaining it:

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promise-resolved-rejected.png" alt="testing-promise-resolved-rejected" class="">
    </p>
</div>

## Assert Promise Rejects

The next step is to test that promise rejects. For example, in our case with Memcached client when the connection is closed the client rejects all incoming requests.

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    /** @test */
    public function it_rejects_all_new_requests_when_closed()
    {
        $this->connection->shouldReceive('close')->once();
        $this->client->close();
        $promise = $this->client->version();

        // assert that promise rejects
    }    
}
{% endhighlight %}

And again we can use the same `Block\await()` function to assert that promise rejects. When this happens `Block\await()` throws an exception which was the promise rejection reason. To create an assertion we can add an empty `try/catch` block:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    // ...

    /** @test */
    public function it_rejects_all_new_requests_when_closed()
    {
        $this->connection->shouldReceive('close')->once();
        $this->client->close();

        try {
            Block\await($this->client->version(), $this->loop);
        } catch (Exception $exception) {
            return;
        }

        $this->fail('Failed asserting that promise rejects. Promise was resolved.');
    }    
}
{% endhighlight %}

If an exception was thrown we consider it as a passed test, otherwise the promise was resolved and we consider the test as a failed one.

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promise-rejected-pass.png" alt="testing-promise-rejected-pass" class="">
    </p>
</div>

To prove that test actually works as we expect, let's remove `close()` call and simulate that server has returned some responses with `resolveRequests()` call:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    // ...

        /** @test */
    public function it_rejects_all_new_requests_when_closed()
    {
        $this->connection->shouldReceive('write')->once();
        $promise = $this->client->version();
        $this->client->resolveRequests(['12345']);

        try {
            Block\await($promise, $this->loop);
        } catch (Exception $exception) {
            return;
        }

        $this->fail('Failed asserting that promise rejects. Promise was resolved.');
    }
}
{% endhighlight %}

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promise-rejected-fail.png" alt="testing-promise-rejected-fail" class="">
    </p>
</div>

This logic can be also extracted to a custom assertion like this:

{% highlight php %}
<?php

class ClientTest extends TestCase
{
    // ...

    /** @test */
    public function it_rejects_all_new_requests_when_closed()
    {
        $this->connection->shouldReceive('close')->once();
        $this->client->close();

        $this->assertPromiseRejects($this->client->version());
    }    

    /**
     * @param PromiseInterface $promise
     * @param int|null $timeout
     * @return Exception
     */
    public function assertPromiseRejects(PromiseInterface $promise, $timeout = null)
    {
        try {
            Block\await($this->client->version(), $this->loop);
        } catch (Exception $exception) {
            return $exception;
        }

        $this->fail('Failed asserting that promise rejects. Promise was resolved.');
    }
}
{% endhighlight %}

Also, this assertion can be improved for use cases when we want to check the reason why the promise was rejected. The reason is always an instance of the `Exception` class. To check that promise was rejected with a required reason we can use PHPUnit `assertInstanceOf` assertion. Let's rewrite the previous test and now also check the rejection reason. The client when being closed rejects all incoming requests with an instance of `ConnectionClosedException`:

{% highlight php %}
<?php

class ClientTest extends TestCase
{
    // ...

    /** @test */
    public function it_rejects_all_new_requests_when_closed()
    {
        $this->connection->shouldReceive('close')->once();
        $this->client->close();

        $this->assertPromiseRejectsWith($this->client->version(), ConnectionClosedException::class);
    }    

    /**
     * @param PromiseInterface $promise
     * @param string $reasonExceptionClass
     * @param int|null $timeout
     */
    public function assertPromiseRejectsWith(PromiseInterface $promise, $reasonExceptionClass, $timeout = null)
    {
        $reason = $this->assertPromiseRejects($promise, $timeout);

        $this->assertInstanceOf(
            $reasonExceptionClass,
            $reason,
            'Failed asserting that promise rejects with a specified reason.'
        );
    }

    /**
     * @param PromiseInterface $promise
     * @param int|null $timeout
     * @return Exception
     */
    public function assertPromiseRejects(PromiseInterface $promise, $timeout = null)
    {
        try {
            Block\await($this->client->version(), $this->loop);
        } catch (Exception $exception) {
            return $exception;
        }

        $this->fail('Failed asserting that promise rejects. Promise was resolved.');
    }
}
{% endhighlight %}

This new `assertPromiseRejectsWith()` assertion under the hood calls `assertPromiseRejects()` to check that the promise was actually rejected. Then simply checks an instance of the rejection exception. To prove that this assertion works, let's assert a wrong exception (`LogicException`) and see what happens:

{% highlight php %}
<?php

class ClientTest extends TestCase
{
    // ...

    /** @test */
    public function it_rejects_all_new_requests_when_closed()
    {
        $this->connection->shouldReceive('close')->once();
        $this->client->close();

        $this->assertPromiseRejectsWith($this->client->version(), LogicException::class);
    }   
}
{% endhighlight %}

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promise-rejected-with.png" alt="testing-promise-rejected-with" class="">
    </p>
</div>

We get a nice explaining message, why the test has failed.

### Using Mocks For Assertions

There is another approach for testing promises - using mocks instead of *waiting*. The main idea is the following:

- create a *callable* mock
- add this mock as a resolve/reject handler
- set an expectation to this mock
- assert that this callable was/was not called.

First of all, we need a callable mock. In PHP `Closure` class is declared as `final`, so we cannot mock it. Instead, we can create our own implementation:

{% highlight php %}
<?php

class CallableStub
{
    public function __invoke()
    {
    }
}
{% endhighlight %}

It is a simple class with the only one method `__invoke()`. Then we add two basic assertion methods for resolving and for rejection:

{% highlight php %}
<?php

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;

class PromiseTestingWithMocksTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return MockInterface|callable
    */
    public function assertCallableCalledNever()
    {
         return Mockery::mock(CallableStub::class)
            ->shouldNotReceive('__invoke')
            ->getMock();
    }

    /**
     * @return MockInterface|callable
     */
    public function assertCallableCalledOnce()
    {
        return Mockery::mock(CallableStub::class)
            ->shouldReceive('__invoke')
            ->once()
            ->getMock();
    }
}
{% endhighlight %}

These methods create a mock for our `CallableStub` and set an expectation on it. Then we can use these mocks and add them as promise handlers. When promise resolves/rejects these mocks will be executed. Let's write a *dummy* test, just to check how these assertions work:

{% highlight php %}
<?php

class PromiseTestingWithMocksTest extends TestCase
{
    /** @test */
    public function promise_resolves()
    {
        $deferred = new Deferred();
        $deferred->resolve();

        $deferred
            ->promise()
            ->then($this->assertCallableCalledOnce(), $this->assertCallableCalledNever());
    }

    // ...
}
{% endhighlight %}

To test that promise resolves we set `assertCallableCalledOnce()` expectation as a resolving handler and `assertCallableCalledNever` as a rejection one. If the promise resolves, the first callback is called one, and the second callback is never executed. And when we run the test it works!

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promises-with-mocks-success.png" alt="testing-promises-with-mocks-success" class="">
    </p>
</div>

To prove that it works, let's now reject the promise:

{% highlight php %}
<?php

class PromiseTestingWithMocksTest extends TestCase
{
    /** @test */
    public function promise_resolves()
    {
        $deferred = new Deferred();
        $deferred->reject();

        $deferred
            ->promise()
            ->then($this->assertCallableCalledOnce(), $this->assertCallableCalledNever());
    }

    // ...
}
{% endhighlight %}

<div class="row">
    <p class="col-sm-12">
        <img src="/assets/images/posts/reactphp-memcached/testing-promises-with-mocks-fail.png" alt="testing-promises-with-mocks-fail" class="">
    </p>
</div>

The test fails, but can you see the reason why? And here comes a huge disadvantage when using mocks - meaningless messages. The test says that:

{% highlight bash %}
Expectation failed for method name is equal to <string:__invoke> when invoked 1 time(s).
Method was expected to be called 1 times, actually called 0 times.
{% endhighlight %}

Something about `__invoke` method and that it should be called, but not a word about promises, and why the test actually has failed. When using mocks we cannot provide custom fail messages, that's why I don't like this approach for testing promises and prefer to use `Clue\React\Block` functions to *wait* for a promise and then simply run some assertions. 

Also, if you write functional tests, that require running the loop, you tests will become even more tricky. Now, you should run the loop, wait for things to happen, then stop the loop, and only then run the assertions. Something like this:

{% highlight php %}
<?php

class ClientTest 
{
    /** @test */
    public function it_retrieves_server_stats()
    {
        $promise = $this->client->stats();
        $this->loop->addTimer(1, function(Timer $timer){
            $timer->getLoop()->stop();
        });
        $this->loop->run();

        $promise->then($this->assertCallableCalledNever(), $this->assertCallableCalledOnce());
    }
}
 {% endhighlight %} 

## Conclusion
Testing asynchronous code sometimes can be tricky. In this article I've covered two approaches for testing promises: 
- running an event loop and waiting for a promise 
- using mocks with expectations for promise handlers

As for me I prefer the first one (using [clue/php-block-react](https://github.com/clue/php-block-react){:target="_blank"} library) because it is much easier to use, the tests look readable and failure messages exactly tell the reason why the tests have failed.

<hr>
Writing this article has inspired me to create [my own testing library for ReactPHP promises](https://github.com/seregazhuk/php-react-promise-testing){:target="_blank"}. It contains `TestCase` class which extends base PHPUnit `TestCase` and provides a set of convenient assertions:

- `assertPromiseResolves()`
- `assertPromiseResolvesWith()`
- `assertPromiseRejects()`
- `assertPromiseRejectsWith()`

So, if you are going to test ReactPHP promises try `seregazhuk/react-promise-testing` library and use nice and readable assertions.
