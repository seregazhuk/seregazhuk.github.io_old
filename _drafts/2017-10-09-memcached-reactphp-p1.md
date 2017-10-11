---
title: Building ReactPHP Memached Client
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client."
---

>This is the first article from the series about building from scratch a streaming Memcached PHP client for ReactPHP ecosystem. This client was inspired by [Christian Lück](https://twitter.com/another_clue)  and his [php-redis-react](https://github.com/clue/php-redis-react) library.

Before writing code we should think about our client's API: how we are going to use it, what methods it is going to have. The client is going to be used in ReactPHP asynchronous ecosystem, so I'm going to provide a promise-based interface (every method returns a promise). Also we a building a streaming client. Under the hood we will open a socket connection and use it as a stream. The client will be a wrapper over this binary stream communication. That means that it will be our job to manually parse Memcached protocol to write and read data with sockets. So, having all of this in mind, let's start.

## Factory
Our client has two dependencies: 
- a stream, which represents a binary socket connection between client and server
- some Memcached protocol parser to create requests and parse responses.

So, we need some how build and pass these dependencies to the client. The best options for it will be a factory. The factory creates dependencies and then uses them to create a client. The first dependency is a streaming socket connection. ReactPHP Socket Component has `Connector` class which allows to create streaming connections. The connector itself depends on the event loop, so it will be passed as a dependency to the factory:

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

The last step is to implement `createClient($address)`. This method accepts memecached connection string like this `localhost:11211` (server address by default) and returns a promise. If the connection was established the promise resolves with an instance of our client. Otherwise the promise rejects with exception:

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
                    return new Client($stream, new ProtocolParser());
                });

        return $promise;
    }
}
{% endhighlight %}

Although we haven't yet created `Client` and `ProtocolParser` classes, the factory itself is ready. To create our future client we should call it like this:

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

The client communicates with Memcached server via a duplex (readable and writable) stream. That means that we are sending raw data to it, and the server returns responses in raw Memcached protocol.

>*I don't want to cover Memcached protocol in this article because it will take too long to mention all the details: how the request is constructed and how we should parse responses. Here is [the official protocol description](https://github.com/memcached/memcached/blob/master/doc/protocol.txt) and a [nice article](https://blog.elijaa.org/2010/05/21/memcached-telnet-command-summary/) with all commands summary. Take a look if you are interested. Also, the implementation of the protocol parser is beyond this article, but it is available in the source code on GitHub. And we will continue with asynchronous code and integration with ReactPHP components.*

So, the client depends on a duplex stream and Memcached protocol parser:

{% highlight php %}
<?php

<?php

namespace seregazhuk\React\Memcached;

use LogicException;
use React\Promise\Deferred;
use React\Stream\DuplexStreamInterface;

class Client
{
    /**
     * @var ProtocolParser
     */
    protected $parser;

    /**
     * @var DuplexStreamInterface
     */
    private $stream;


    /**
     * @param DuplexStreamInterface $stream
     * @param ProtocolParser $parser
     */
    public function __construct(DuplexStreamInterface $stream, ProtocolParser $parser)
    {
        // ...

        $this->stream = $stream;
        $this->parser = $parser;
    }
}
{% endhighlight %}

ReactPHP [Stream Component](https://reactphp.org/stream/) already has an interface for a duplex stream (`React\Stream\DuplexStreamInterface`), so we can type hint. I don't want to implement wrappers for all Memcached commands in this client. Instead we can use a magic method `__call()` and consider all calls to methods that are not implemented in the client as Memcached commands. 

To execute these commands asynchronously and don't wait for the results we use Deferred objects and promises. To refresh in memory:

 - A **promise** is a placeholder for the initially unknown result of the asynchronous code.
 - A **deferred** represents the code which is going to be executed to receive this result.

>*If you are new to ReactPHP promises check [this article]({% post_url 2017-06-16-phpreact-promises %}).*

The logic is the following. When we call a method that is not implemented in `Client` the `__call()` method is being executed. In this method we create an instance of `React\Promise\Deferred` class. Then we parse the called method's name and arguments into the actual Memcached command. This command is written to the connection stream. The deferred object is stored in the state as a pending request and its promise is returned:

{% highlight php %}
<?php

class Client
{
    // ...

    /**
     * @var Deferred[]
     */
    private $requests = [];

    /**
     * @param string $name
     * @param array $args
     * @return Promise|PromiseInterface
     */
    public function __call($name, $args)
    {
        $request = new Deferred();

        $query = $this->parser->makeRequest($name, $args);
        $this->stream->write($query);
        $this->requests[] = $request;

        return $request->promise();
    }
}
{% endhighlight %}

We store deferred objects in the state so we can later resolve their promises. When we receive some data from the connection stream we consider it as responses from Memcached server. Then we can use these responses to resolve our pending requests.
