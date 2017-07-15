---
title: "Buliding Video Streaming Server wiht ReactPHP"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
---

Our server will be build over the [ReactPHP Http Component](http://reactphp.org/http/). It provides a simple asynchronous interface for handling incoming connections and processing HTTP requests. To create a server you need:
- Init the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}).
- Create a server for handling incoming requests (`React\Http\Server`).
- Create a socket to start listening for the incoming connections (`React\Socket\Server`).

At first let's create a very simple `Hello world` server

{% highlight php %}
<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
$loop->run();
{% endhighlight %}

The main logic of the server is placed in the callback, which is passed to the constructor. This callback is being executed for each incoming request. It accepts an instance of the `Request` object and returns `Response` object. It our case we return the same static string `Hello world` to each request.



