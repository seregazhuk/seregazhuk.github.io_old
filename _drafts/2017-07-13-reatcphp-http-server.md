---
title: "Buliding Video Streaming Server with ReactPHP"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Create a video streaming server in PHP with ReactPHP"
---

In this article, we will build a simple video streaming server on top of the [ReactPHP Http Component](http://reactphp.org/http/), which provides a simple asynchronous interface for handling incoming connections and processing HTTP requests. To create a server we will need to:

- Init the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}).
- Create a server (`React\Http\Server`) for handling incoming requests.
- Create a socket (`React\Socket\Server`) to start listening for the incoming connections.
- Run the event loop.

At first, let's create a very simple `Hello world` server to understand how `ReactPHP Http` works:

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

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";
$loop->run();
{% endhighlight %}

The main logic of the server is placed in the callback, which is passed to the server constructor. This callback is being executed for each incoming request. It accepts an instance of the `Request` object and returns `Response` object. The `Response` class constructor accepts the response code, headers and the body of the response. In our case, for each request, we return the same static string `Hello world`. And if we open now `127.0.0.1:8000` in the browser we will see our `Hello world` response. Nice!

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/hello-http-server.png" alt="hello server" class="">
</p>

## Simple Video Streaming

Now, we can try something more interesting. `React\Http\Response` constructor can accept an instance of [ReactPHP ReadableStreamInterface](https://github.com/reactphp/stream#readablestreaminterface) as a response body. This allows us to *stream* data directly into the response body. Check [this article]({% post_url 2017-06-12-phpreact-streams %}) if you want to know more about ReactPHP streams.

For example, we can open file `bunny.mp4` (you can download it from the [Github](https://github.com/seregazhuk/reactphp-blog-series/blob/master/http/media/bunny.mp4)) in a read mode, create a `ReadableResourceStream` with it and then provide this stream as a response body like this:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $video = new \React\Stream\ReadableResourceStream(fopen('bunny.mp4', 'r'), $loop);

    return new Response(200, ['Content-Type' => 'video/mp4'], $video);
});
{% endhighlight %}

To create an instance of the `ReadableResourceStream` we need an event loop, so we need to pass it to the closure. We also have changed `Content-Type` header to `video/mp4` to notify our browser that we are sending a video in the response. There is no need to specify a `Content-Length` header because behind the scenes ReactPHP will automatically use chunked transfer encoding and send the respective header `Transfer-Encoding: chunked`.

Now refresh the browser and watch the streaming video:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streaming-server-bunny-video.gif" alt="simple streaming server" class="">
</p>


Really cool! We have a streaming video server with several lines of code!

**Notice**. It is important to create an instance of the `ReadableResourceStream` right in the callback of the server. Remember the asynchronous nature of our application. If we create the stream outside of the callback and then simply pass into the callback, there will be on streaming at all. Why? Because the reading of the video file and processing the incoming requests to the server both work asynchronously. That means that while the server is waiting for new connections we also start reading a video file. To prove this we can attach a handler to the stream and on every time when we read data from it, we will output a message:

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

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";
$loop->run();
{% endhighlight %}

When execution reaches the last line `$loop->run();` the server starts listening to the incoming requests and we also start reading a file:

<p class="">
    <img src="/assets/images/posts/reactphp/streaming-server-wrong.gif" alt="streaming server wrong example" class="">
</p>

So, chances high that when the first request arrives at the server we have already reached the end of the video file and there is no data for streaming. When the request handler receives a response stream that is already closed, it will simply send an empty response body, which means in our case no video streaming and an empty page in the browser.

## Improvements

On the next step, we can improve a little our server. Let's say that a user can specify in the query string the file name to be streamed. For example, when users type in the browser: `http://127.0.0.1:8000/?video=bunny.mpg` the server starts streaming file `bunny.mpg`. We will store our files for streaming in `media` directory. Now we need somehow to get the query parameters from the request. Request object that we receive in the request handler has method `getQueryParams` which returns an array of the get query, similar to global variable `$_GET`:

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

- What if there is no such file on server? We should return 404 page in this case.
- Now we have a hardcoded `Content-Type` header value. We should determine it according to the specified file.

### Checking if file exists
Before opening a file and creating a stream we should check if this file exists on the server. If not we simply return `404` response:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $params = $request->getQueryParams();
    $file = $params['video'] ?? '';

    if (empty($file)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;

    if (!file_exists($filePath)) {
        return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
    }

    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);

    return new Response(200, ['Content-Type' => 'video/mp4'], $video);
});
{% endhighlight %}

Now our server doesn't crash when a user requests a wrong file. It responds with a correct message:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/video-streaming-server-404.png" alt="video streaming 404 error" class="">
</p>

### Determining file mime type
In PHP we have a nice function `mime_content_type` which returns MIME Content-type for a file. We can use it to replace a hardcoced value for `Content-Type` header:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $params = $request->getQueryParams();
    $file = $params['video'] ?? '';

    if (empty($file)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($filePath)) {
        return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
    }

    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);

    return new Response(200, ['Content-Type' => mime_content_type($filePath)], $video);
});
{% endhighlight %}

Very nice, we have removed a hardcoded `Content-Type` header value and now it is determined automatically according to the file.

## Refactoring
Actually, the server is ready, but the main logic, which is placed in the request handler doesn't look very nice. Of course, if you are not going to change or extend it, you can keep it as it is, right in a callback. But if the server logic is going to change, for example instead of a plain text we would like to render some HTML pages this callback will grow and very soon it will become hard to understand and maintain. Let's make some refactoring and extract this logic into its own `VideoStreaming` class. To be able to use this class as *callable* request handler we should implement magic `__invoke()` method in it. And then we can simply pass an instance of this class as a callback to the `Server` constructor:

{% highlight php %}
<?php 

// ... 

$loop = Factory::create();

$videoStreaming = new VideoStreaming($loop);

$server = new Server($videoStreaming);
{% endhighlight %}

Now we can start building `VideoStreaming` class. It requires a single dependency - an instance of the event loop which will be injected through the constructor. At first, we can simply copy-and-paste the code from a request callback into the `__invoke` method and then start refactoring it:

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
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
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

Next, we can refactor this `__invoke` method. Let's figure out what is happening here:

1. We parse the request query and determine the file which user has requested.
2. Create a stream from this file and send it back as a response.

So, it looks like we can extract two methods here:

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
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
        }
 
       return $this->makeResponseFromFile($file);
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
    protected function makeResponseFromFile($filePath)
    {
        // ...
    }
}
{% endhighlight %}

The first `getFilePath` is very simple. We receive request parameters with `$request->getQueryParams()` method. Then if there is no `file` key there we simply return an empty string, which means that a user has opened the server without any GET parameters. In this case, we could show a static page or something like this. Now we return a simple plain text message `Video streaming server`. If a user has specified `file` in GET request we create a path to this file and return it:

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

Method `makeResponseFromFile` will be also very simple. If there is no file for the given path we immediately return a 404 response. Otherwise, we open this file, create a readable stream and return it as a response body:

{% highlight php %}
<?php 

class VideoStreaming
{
    // ...

    /**
     * @param string $filePath
     * @return Response
     */
    protected function makeResponseFromFile($filePath)
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
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
        }

        return $this->makeResponseFromFile($file);
    }

    /**
     * @param string $filePath
     * @return Response
     */
    protected function makeResponseFromFile($filePath)
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

Of course, instead of a simple request handler callback now we have 3 times more code, but if this code is going to be changed in the future it will be much easier to make these changes.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/http).

<strong>Other ReactPHP articles:</strong>

- [Event Loop And Timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Streams]({% post_url 2017-06-12-phpreact-streams %})
- [Promises]({% post_url 2017-06-16-phpreact-promises %})
- [Sockets: server]({% post_url 2017-06-22-reactphp-chat-server %}) and  [client]({% post_url 2017-06-24-reactphp-chat-client %})
- [UDP chat]({% post_url 2017-07-05-reactphp-udp %})