---
title: Building ReactPHP Memached Client
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client."
---

>This is the first article from the series about building from scratch a streaming Memcached PHP client for ReactPHP ecosystem. This client was inspired by [Christian Lück](https://twitter.com/another_clue)  and his [php-redis-react](https://github.com/clue/php-redis-react) library.

Before writing code we should think about our client's API: how we are going to use it, what methods it is going to have. The client is going to be used in ReactPHP asynchronous ecosystem, so I'm going to provide a promise-based interface for it (every method returns a promise). Also we are building a streaming client. Under the hood we will open a socket connection and use it as a stream. The client itself will be a wrapper over this binary stream communication. That means that it is our job to manually parse Memcached protocol to write and read data with sockets. So, having all of this in mind, let's start.

## Cleint Factory
Our client has two dependencies: 
- a stream, which represents a binary socket connection between client and server
- some Memcached protocol parser to create requests and parse responses.

So, we need some how to build and pass these dependencies to the client. The best option for it will be a factory. The factory creates dependencies and then uses them to create a client. The first dependency is a streaming socket connection. ReactPHP Socket Component has `Connector` class which allows to create streaming connections. The connector itself depends on the event loop, so it will be passed as a dependency to the factory:

{% highlight php %}
<?php
namespace seregazhuk\React\Memcached;

use React\EventLoop\LoopInterface;
use React\Socket\Connector;

class Factory
{
    private $connector;

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->connector = new Connector($loop);
    }

    // ...
}
{% endhighlight %}

The last step is to implement `createClient($address)`. This method accepts memecached connection string like `localhost:11211` (server address by default) and returns a promise. If the connection was established the promise resolves with an instance of our client. Otherwise the promise rejects with exception:

{% highlight php %}
<?php
namespace seregazhuk\React\Memcached;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use seregazhuk\React\Memcached\Protocol\Parser;
use seregazhuk\React\Memcached\Protocol\Response\Factory as ResponseFactory;
use seregazhuk\React\Memcached\Protocol\Request\Factory as RequestFactory;

class Factory
{
    private $connector;

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->connector = new Connector($loop);
    }

    /**
     * Creates a memcached client connected to a given connection string
     *
     * @param string $address Memcached server URI to connect to
     * @return PromiseInterface resolves with Client or rejects with \Exception
     */
    public function createClient($address)
    {
        $promise = $this
            ->connector
            ->connect($address)
            ->then(
                function (ConnectionInterface $stream) {
                    $parser = new Parser(new ResponseFactory(), new RequestFactory());

                    return new Client($stream, new ProtocolParser());
                });

        return $promise;
    }
}
{% endhighlight %}

>*I don't want to cover Memcached protocol in these articles because it will take too long to mention all the details: how the request is constructed and how we should parse responses. Here is [the official protocol description](https://github.com/memcached/memcached/blob/master/doc/protocol.txt) and a [nice article](https://blog.elijaa.org/2010/05/21/memcached-telnet-command-summary/) with all commands summary. Take a look if you are interested. The implementation of the protocol parser is beyond this article, but it is available in the source code on GitHub. And we will continue with asynchronous code and integration with ReactPHP components.*

Although we haven't yet created `Client` class the factory itself is ready. To create our future client we should call it like this:

{% highlight php %}
<?php

use seregazhuk\React\Memcached\Factory;
use seregazhuk\React\Memcached\Client;

require '../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient('localhost:11211')->then(
    function (Client $client) {
        // connection was successful
        // ...
    },
    function(Exception $e){
        // something went wrong
        echo $e->getMessage(); die();
    });

$loop->run();
{% endhighlight %}

Factory's `createClient` method returns a promise which resolves with an instance of our client. Next, let's move on implementing a client.

## Client 

### Making a request

The client communicates with Memcached server via a duplex (readable and writable) stream. That means that we are sending raw data to it, and the server returns responses in raw Memcached protocol.

So, the client depends on a duplex stream and Memcached protocol parser:

{% highlight php %}
<?php

namespace seregazhuk\React\Memcached;

use LogicException;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\DuplexStreamInterface;
use seregazhuk\React\Memcached\Protocol\Parser;

class Client
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var DuplexStreamInterface
     */
    private $stream;

    /**
     * @param DuplexStreamInterface $stream
     * @param Parser $parser
     */
    public function __construct(DuplexStreamInterface $stream, Parser $parser)
    {

        $this->stream = $stream;
        $this->parser = $parser;

        // ... 
    }
}
{% endhighlight %}

ReactPHP [Stream Component](https://reactphp.org/stream/) already has an interface for a duplex stream (`React\Stream\DuplexStreamInterface`), so we can type hint it. I don't want to implement wrappers for all Memcached commands in this client. Instead we can use `__call()` magic method and consider all calls to methods that are not implemented in the client as Memcached commands. 

To execute these commands asynchronously and don't wait for the results we use Deferred objects and promises. Just to refresh in memory:

 - A **promise** is a placeholder for the initially unknown result of the asynchronous code.
 - A **deferred** represents the code which is going to be executed to receive this result.

>*If you are new to ReactPHP promises check [this article]({% post_url 2017-06-16-phpreact-promises %}).*

The logic is the following. When we call a method that is not implemented in `Client`, the `__call()` method is being executed. In this method we create an instance of `React\Promise\Deferred` class. Then we parse the called method's name and arguments into the actual Memcached command. This command is written to the connection stream. The deferred object is stored in the state as a pending request and its promise is returned. For storing deferred objects we use a wrapper - class `Request`. It represents a command which was sent to the server and a deferred object that should be resolved with the response for this command:

{% highlight php %}
<?php

namespace seregazhuk\React\Memcached;

use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class Request
{
    /**
     * @var Deferred
     */
    private $deferred;

    /**
     * @var string
     */
    private $command;

    /**
     * @param string $command
     */
    public function __construct($command)
    {
        $this->deferred = new Deferred();
        $this->command = $command;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return Promise|PromiseInterface
     */
    public function getPromise()
    {
        return $this->deferred->promise();
    }

    /**
     * @param mixed $value
     */
    public function resolve($value)
    {
        $this->deferred->resolve($value);
    }
}

{% endhighlight %}

And here is the implementation of the client's `__call()` method:

{% highlight php %}
<?php

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

        $query = $this->parser->makeRequest($name, $args);
        $this->stream->write($query);
        $this->requests[] = $request;

        return $request->getPromise();
    }
}
{% endhighlight %}

We create a *request* with a command name. Then the protocol parser creates a query string that is sent to the connection and a *pending request* is stored in the state. The next step is resolving pending requests.

### Resolving a response

We store deferred objects in the state so we can later resolve their promises. When we receive some data from the connection stream we consider it as responses from Memcached server. Then we can use these responses to resolve our pending requests.

To process received from Memcached server data we need to attach a handler to the stream's `data` event:

{% highlight php %}
<?php

class Client
{
    // ...

    /**
     * @param DuplexStreamInterface $stream
     * @param Parser $parser
     */
    public function __construct(DuplexStreamInterface $stream, Parser $parser)
    {
        $this->stream = $stream;
        $this->parser = $parser;

        $stream->on('data', function ($chunk) {
            // ...
        });

    }
}
{% endhighlight %}

The process of handling the received data consists of two steps:

1. The protocol parser parses the raw data into a batch of responses (because we can receive responses for several commands at once).
2. We resolve pending requests with these responses. 
3. If there are no pending requests but we received a response, that means that something went wrong and we throw an exception.

{% highlight php %}
<?php

class Client
{
    // ...

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
    }

    /**
     * @param array $responses
     */
    protected function resolveRequests(array $responses)
    {
        if (empty($this->requests)) {
            throw new LogicException('Received unexpected response, no matching request found');
        }

        foreach ($responses as $response) {
            /* @var $request Request */
            $request = array_shift($this->requests);

            $parsedResponse = $this->parser->parseResponse($request->getCommand(), $response);
            $request->resolve($parsedResponse);
        }
    }
}
{% endhighlight %}

And that is all. The first very primitive version of the streaming client is ready. To check it we can use a simple example. Let's put something into the cache and then retrieve it:

{% highlight php %}
<?php

use seregazhuk\React\Memcached\Factory;
use seregazhuk\React\Memcached\Client;

require '../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient('localhost:11211')->then(
    function (Client $client) {
        $client->set('name', 'test')->then(function($result){
            var_dump($result);
            echo "The value was stored\n";
        });
        $client->get('name')->then(function($data){
            var_dump($data);
            echo "The value was retrieved\n";
        });
    },
    function(Exception $e){
        echo $e->getMessage(), "\n";
    });

$loop->run();
{% endhighlight %}

If we run this check script the result is the following:

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp-memcached/set-get-example.png" alt="set-get-example" class="">
    </p>
</div>

The client is very simple now and should be improved. For example, it allows to store only strings. If we try to store an array the client will fail. Also there is still no way to end or close the connection. There improvements will be implemented in the next article.
