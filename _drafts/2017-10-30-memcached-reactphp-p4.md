---
title: "Building ReactPHP Memached Client: Unit-Testing Promises"
tags: [PHP, Event-Driven Programming, ReactPHP, Memcached, Unit-Testing]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client part 4: unit-testing asynchronous promises"
---

>This is the last article from the series about building from scratch a streaming Memcached PHP client for ReactPHP ecosystem. The library is already released and published, you can find it on [GitHub](https://github.com/seregazhuk/php-react-memcached).

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-memcached/logo3.png" alt="logo" class="">
</p>

In the [previous article]({% post_url 2017-10-28-memcached-reactphp-p3 %}), we have completely finished with the client source code. And now it's time to start testing it. The client has a promise-base interface:

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

It is necessary to decide what we are going to test. For the consumer's point of view it has promises, that can be resolved or rejected. And actually all client methods return promises. So, we need to test that in some conditions these promises are resolve and in others - are rejected. 
As an example, let's test that the client resolves a pending request promise with the response from the server. 

Under the hood the client uses a duplex stream and Memcached protocol parser, but we are going to test the client code in isolation, so these dependencies will be mocked. 

>*For mocking objects I'm going to use a very popular [Mockery](https://github.com/mockery/mockery) library. It has very clear and intuitive interface, but if you meet some difficulties check out [official documentation](http://docs.mockery.io/en/latest/).*

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

    /**
     * @var Parser|MockInterface
     */
    protected $parser;

    protected function setUp()
    {
        $this->stream = Mockery::mock(DuplexStreamInterface::class)->shouldReceive('on')->getMock();
        $this->parser = Mockery::mock(Parser::class, [new RequestFactory(), new ResponseFactory()])->makePartial();
        $this->client = new Client($this->stream, $this->parser);

        parent::setUp();
    }

    // ...
}
{% endhighlight %}

`setUp()` method creates mocks and instantiates an instance of the client. Here is the source code of the client constructor:

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

As you can see, it immediately calls `on()` method on the stream to attach some event handlers to `data` and `close` events. That's why I've set an expectation for `on()` method on the stream mock: `shouldReceive('on')`. Before implementing tests we need to add one more method `tearDown()`:

{% highlight php %}
<?php

class StreamingClientTest extends TestCase
{
    // ...

    protected function tearDown()
    {
        parent::tearDown();
        if ($container = Mockery::getContainer()) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }

        Mockery::close();
    }
}
{% endhighlight %}

This method is necessary to assert mocked expectations. By default PHPUnit doesn't count Mockery assertions and if there are no `$this->assert*` calls in the test class, PHPUnit will report: `This test did not perform any assertions`. So we add Mockery assertions to PHPUnit. Then we call `Mockery::close()` to run verification for expectations. When all these preparations are done we can move to writing tests.

## Promise was resolved

So, we start with a simple test. It will check that the client resolves a promise from the request with a response data from the server. For these purposes we will use `version()` method, because it is very simple and has no arguments:

1. We call `version()` method.
2. We set up expectation that Memcached parser returned response `12345`, which we consider as a server version.
3. We assert that the promise from `version` method was resolved with the value of `12345`.

To set mock expectations we need to refresh in memory what happens under the hood, when we call `version()` method on the client. The `Client` class has no such method, and for all Memcached commands it actually uses `__call()` method:

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

In our test `$this->parser` and `$this->stream` are mocks. So, we start our test with setting up expectations for these mocks:

{% highlight php %}
<?php

// ...

class ClientTest extends TestCase
{
    /** @test */
    public function it_resolves_a_promise_with_data_from_response()
    {
        $this->parser->shouldReceive('makeRequest')->once();
        $this->stream->shouldReceive('write')->once();
        $promise = $this->client->version();

       // ...
    }    
}
{% endhighlight %}

The code above can be described like this: 

>*When we call `version()` on the client, it should call `makeRequest()` method on the protocol parser and then the client should call `write()` method on the stream. Then we call `version()` method, which returns a promise.*

And here comes the main section of this article: *how to test a promise*. In this particular test we need to check that the promise resolves with the data from the server. We assume, that the server has returned a string `12345` as a server version. To pass server responses to the client we can use `resolveRequests()` method. It accepts an array of responses and use them to resolve pending promises.




