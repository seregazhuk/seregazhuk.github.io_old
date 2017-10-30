---
title: "Building ReactPHP Memached Client: Unit-Testing Promises"
tags: [PHP, Event-Driven Programming, ReactPHP, Memcached, Unit-Testing]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client part 4: unit-testing asynchronous promises"
---

>This is the last article from the series about building from scratch a streaming Memcached PHP client for ReactPHP ecosystem. The library is already released and published, you can find it on [GitHub](https://github.com/seregazhuk/php-react-memcached).

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

This method is necessary to assert mocked expectations. By default PHPUnit doesn't count Mockery assertions and if there are no `$this->assert*` calls in the test class, PHPUnit will report: `This test did not perform any assertions`. So we add Mockery assertions to PHPUnit. Then we call `Mockery::close()` to run verification for expectations.
