---
title: Building Memached Client For ReactPHP
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Creating Memcached client PHP library for ReactPHP"
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

Although we haven't yet created `Client` and `ProtocolParser` classes, the factory itself is ready. To client our future client we should call it like this:

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
        print_r($e->getMessage());
    });

$loop->run();
{% endhighlight %}

## Client 



We are going to use a duplex stream (readable and writable). ReactPHP [Stream Component](https://reactphp.org/stream/) already has an interface for this use case (React\Stream\DuplexStreamInterface).
