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

The main logic of the server is placed in the callback, which is passed to the constructor. This callback is being executed for each incoming request. It accepts an instance of the `Request` object and returns `Response` object. It our case we return the same static string `Hello world` to each request. And if we open now `127.0.0.1:8000` in the browsers will see our `Hello world` response. Nice!

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/hello-http-server.png" alt="cgn-edit" class="">
</p>

Now, we can try something more interesting. Let's try to return a [stream]({% post_url 2017-06-12-phpreact-streams %}) instead of plain text. We can use [ReactPHP Stream Component](https://github.com/reactphp/stream) to use streams in out async application. For example, we can open file `cat.mp4` (you can download it from the [Github](https://github.com/seregazhuk/reactphp-blog-series/blob/master/http/cat.mp4)) in a read mode, create a `ReadableResourceStream` with it and then provide this stream as a content of the reponse like this:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $video = new \React\Stream\ReadableResourceStream(fopen('cat.mp4', 'r'), $loop);

    return new Response(200, ['Content-Type' => 'video/mp4'], $video);
});
{% endhighlight %}

To create an instance of the `ReadableResourceStream` we need an event loop, so we need to pass it to the closure. We also change `Content-Type` header to `video/mp4` to notify our browser that we are sending a video in the reponse. Now refresh the browser and watch the video stream:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streaming-server-bunny-video.gif" alt="cgn-edit" class="">
</p>

Really cool! Streaming video with several lines of code!

**Notice**. It is important to create an instance of the `ReadableResourceStream` right in the callback of the server. Remember the asynchronous nature of our application. If we create the stream outside of the callback and then simply pass it noting will work. Why? Because the reading of the video file and processing the incoming requests to the servers works asynchronously. That means that while the server is waiting for new connections we start reading video file. To prove this we can attach a handler to the stream and on every time when we read data from it we will output a message:

{% highlight php %}
<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

$loop = Factory::create();
$video = new \React\Stream\ReadableResourceStream(fopen('bunny.mp4', 'r'), $loop);
$video->on('data', function(){
    echo "Reading file\n";
});

$server = new Server(function (ServerRequestInterface $request) use ($stream) {
    return new Response(200, ['Content-Type' => 'video/mp4'], $stream);
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
$loop->run();
{% endhighlight %}

When execution reaches the last line `$loop->run();` the server starts listening to the incoming requests and we also start reading a file:

<p class="">
    <img src="/assets/images/posts/reactphp/streaming-server-wrong.gif" alt="cgn-edit" class="">
</p>

So, chances high that when the first request arrives to the server we have already reached the end of the video file and there is no data for streaming.
