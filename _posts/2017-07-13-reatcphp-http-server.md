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
    <img src="/assets/images/posts/reactphp/hello-http-server.png" alt="hello server" class="">
</p>

## Simple Video Streaming

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
    <img src="/assets/images/posts/reactphp/streaming-server-bunny-video.gif" alt="simple streaming server" class="">
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
    <img src="/assets/images/posts/reactphp/streaming-server-wrong.gif" alt="streaming server wrong example" class="">
</p>

So, chances high that when the first request arrives to the server we have already reached the end of the video file and there is no data for streaming.

## Improvements

On the next step we can a little improve our server. Let's say that a user can specify in the query string the file name to be streamed. For example, when users types in the browser: `http://127.0.0.1:8000/?video=bunny.mpg` the server starts streaming file `bunny.mpg`. We will store our files for streaming in `media` directory. Now we need somehow to get the query parameters from the request. Request object that we receive in the callback has method `getQueryParams` which returns an array of the get query, similar to global variable `$_GET`:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $params = $request->getQueryParams();
    $file = $params['video'];

    if (empty($file)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;
    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);

    return new Response(200, ['Content-Type' => 'video/mp4'], $video);
});
{% endhighlight %}

Now to view `bunny.mpg` video, we can visit `http://127.0.0.1:8000?video=bunny.mp4` in the browser. The server checks the incoming request for GET parameters. If it finds `video` parameter we assume that it is the video file name, which user wants to be streamed. Then we build a path to this file, open a *readable stream* and pass it to the response. But there are two issues here. Do you see them?

- What if there is no such file on server? We should return 404 page.
- Now we have a hardcoded `Content-Type` header value. We should determine it according to the specified file.

### Checking if file exists
Before opening a file and creating a stream we should check if this file exists on the server. If not we simply return `404` response:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $params = $request->getQueryParams();
    $file = $params['video'] ?? '';

    if (empty($file)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($filePath)) {
        return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
    }

    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);

    return new Response(200, ['Content-Type' => 'video/mp4'], $video);
});
{% endhighlight %}

Now our server doesn't crash when we request a wrong file. It responds with a correct message:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/video-streaming-server-404.png" alt="video streaming 404 error" class="">
</p>

### Determining file mime type
In PHP we have a nice function `mime_content_type` which returns MIME Content-type for a file:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $params = $request->getQueryParams();
    $file = $params['video'] ?? '';

    if (empty($file)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($filePath)) {
        return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
    }

    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);

    return new Response(200, ['Content-Type' => mime_content_type($filePath)], $video);
});
{% endhighlight %}

Very nice, we have removed a hardcoded `Content-Type` header value and now it is determined automatically acording to the file.

## Refactoring
Actually the server is ready, but the main logic, which is placed in the server callback doesn't look very nice. Ofcourse if you are not going to change or extends it, you can keep it as it is, right in a ballback. But if the server logic is going to change, for example instead of plain text we would like to render some html pages this callback will grow and very soon it will become hard to understand. Let's make some refactoring and extract this logic into its own `VideoStreaming` class. To be able to use this class as *callable* we should implement magic `__invoke()` method in it. And then we can simply pass an instance of this class as a callback to the `Server` constructor:

{% highlight php %}
<?php 

$loop = Factory::create();

$videoStreaming = new VideoStreaming($loop);

$server = new Server($videoStreaming);
{% endhighlight %}

Now we can start builing `VideoStreaming` class. It requires a single dependency - an instance of the event loop which will be injected through the constructor. At first we can simply copy-and-paste the code from a server callback into the `__invoke` method and then start refactoring it:

{% highlight php %}
<?php

class VideoStreaming
{
    // ... 

    /**
     * @param ServerRequestInterface $request
     * @return Response
     */
    function __invoke(ServerRequestInterface $request)
    {
        $params = $request->getQueryParams();
        $file = $params['video'] ?? '';

        if (empty($file)) {
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming');
        }

        $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($filePath)) {
            return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
        }

        $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $this->eventLoop);

        return new Response(200, ['Content-Type' => mime_content_type($filePath)], $video);
    }
}
{% endhighlight %}

Next we can refactoring this method. Let's figure out what is happening here:

1. We parse the request query and determine the file which user has requested.
2. Create a stream from this file and send it back as a response.

So, it looks like we can extract to methods here:

{% highlight php %}
<?php 

class VideoStreaming
{
    // ...

    /**
     * @param ServerRequestInterface $request
     * @return Response
     */
    function __invoke(ServerRequestInterface $request)
    {
        $file = $this->getFilePath($request);
        if (empty($file)) {
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming');
        }
 
       return $this->makeStreamResponse($file);
    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function getFilePath(ServerRequestInterface $request)
    {
        // ...
    }

    /**
     * @param string $filePath
     * @return Response
     */
    protected function makeStreamResponse($filePath)
    {
        // ...
    }
}
{% endhighlight %}

The first `getFilePath` is very simple. We receive request parameters with `$request->getQueryParams()` method. Then if there is no `file` key there we simply return empty string, which means that user has opened the server without any GET parameters. It this case we could show a static page or something like this. Now we return a simple plain text message `Video streaming`. If user has specified `file` in GET request we create a path to this file and return it:

{% highlight php %}
<?php 

class VideoStreaming
{
    // ...

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function getFilePath(ServerRequestInterface $request)
    {
        $file = $$request->getQueryParams()['file'] ?? '';

        if (empty($file)) return '';

        return __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;
    }

    // ... 
}
{% endhighlight %}

Method `makeStreamResponse` will be also very simple. If there is no file for the given path we emidiatelly return a 404 response. Otherwise we open this file, create a readable stream and return it as a response:

{% highlight php %}
<?php 

class VideoStreaming
{
    // ...

    /**
     * @param string $filePath
     * @return Response
     */
    protected function makeStreamResponse($filePath)
    {
        if (!file_exists($filePath)) {
            return new Response(404, ['Content-Type' => 'text/plain'], "Video $filePath doesn't exist on server.");
        }

        $stream = new ReadableResourceStream(fopen($filePath, 'r'), $this->eventLoop);

        return new Response(200, ['Content-Type' => mime_content_type($filePath)], $stream);
    }
}   
{% endhighlight %}

Here is a full code of the `VideoStreaming` class:

{% highlight php %}
<?php

use React\Http\Response;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use Psr\Http\Message\ServerRequestInterface;

class VideoStreaming
{
    /**
     * @var LoopInterface
     */
    protected $eventLoop;

    /**
     * @param LoopInterface $eventLoop
     */
    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;
    }

    /**
     * @param ServerRequestInterface $request
     * @return Response
     */
    function __invoke(ServerRequestInterface $request)
    {
        $file = $this->getFilePath($request);
        if (empty($file)) {
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming');
        }

        return $this->makeStreamResponse($file);
    }

    /**
     * @param string $filePath
     * @return Response
     */
    protected function makeStreamResponse($filePath)
    {
        if (!file_exists($filePath)) {
            return new Response(404, ['Content-Type' => 'text/plain'], "Video $filePath doesn't exist on server.");
        }

        $stream = new ReadableResourceStream(fopen($filePath, 'r'), $this->eventLoop);

        return new Response(200, ['Content-Type' => mime_content_type($filePath)], $stream);
    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function getFilePath(ServerRequestInterface $request)
    {
        $file = $request->getQueryParams()['file'] ?? '';

        if (empty($file)) return '';

        return __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;
    }
}
{% endhighlight %}

Ofcouse instead of a simple callback now we have 3 times more code, but if this code is going to be changed in future it will be much easier to make these required changes.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/http).

<strong>Other ReactPHP articles:</strong>

- [Event Loop And Timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Streams]({% post_url 2017-06-12-phpreact-streams %})
- [Promises]({% post_url 2017-06-16-phpreact-promises %})
- [Sockets: server]({% post_url 2017-06-22-reactphp-chat-server %}) and  [client]({% post_url 2017-06-24-reactphp-chat-client %})
- [UDP chat]({% post_url 2017-07-05-reactphp-udp %})