---
title: "Building Video Streaming Server with ReactPHP"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Create a video streaming server in PHP with ReactPHP"
image: "/assets/images/posts/reactphp/video-streaming.jpg" 
---

<iframe width="95%" height="600" src="https://www.youtube.com/embed/iNH3CPZQ_Ms" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>

In this article, we will build a simple asynchronous video streaming server with ReactPHP. ReactPHP is a [set of independent components](https://github.com/reactphp) which allows you to create an asynchronous application in PHP. [ReactPHP Http](http://reactphp.org/http/) is a high-level component which provides a simple asynchronous interface for handling incoming connections and processing HTTP requests. 

<p class="text-center image">
    <img itemprop="image" src="/assets/images/posts/reactphp/video-streaming.jpg" alt="hello server" class="">
</p>


The core of every ReactPHP application is the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}){:target="_blank"}. It is the most low-level component. Every other component uses it. Event loop runs in a single thread and is responsible for scheduling asynchronous operations. So, the most of ReactPHP applications have the following structure:

- We init the loop.
- Set up the world (configure other components that use the loop).
- Run the loop.

When we run the loop, the application starts execution of the asynchronous operations. The execution runs endlessly, until we call `$loop->stop()` to stop it or terminate the script itself.

In our case, the *Set up the world* step looks the following:

- Create a server (`React\Http\Server`) for handling incoming requests.
- Create a socket (`React\Socket\Server`) to start listening for the incoming connections.

At first, let's create a very simple `Hello world` server to understand how it works:

{% highlight php %}
<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

// init the event loop
$loop = Factory::create();

// set up the components
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";

// run the application
$loop->run();
{% endhighlight %}

The main logic of the server is placed in the callback, which is passed to the server constructor. This callback is being executed for each incoming request. It accepts an instance of the `Request` object and returns `Response` object. The `Response` class constructor accepts the response code, headers and the body of the response. In our case, for each request, we return the same static string `Hello world`. 

If we execute this script it will run endlessly. The server is working and is listening to the incoming requests. If we now open `127.0.0.1:8000` in the browser we will see our `Hello world` response. Nice!

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/hello-http-server.png" alt="hello server" class="">
</p>

## Simple Video Streaming

Now, we can try something more interesting. For example, we can use [Filesystem component]({% post_url 2018-02-27-reactphp-filesystem %}){:target="_blank"} and open file `bunny.mp4` (you can download it from the [Github](https://github.com/seregazhuk/reactphp-blog-series/blob/master/http/media/bunny.mp4)) in a **read mode** and then provide its stream as a response body. 

{% highlight php %}
<?php

$filesystem = \React\Filesystem\Filesystem::create($loop);

$server = new Server(function (ServerRequestInterface $request) use ($filesystem) {
    $file = $filesystem->file('media/bunny.mp4');

    return $file->open('r')->then(
        function (\React\Filesystem\Stream\ReadableStream $stream) {
            return new Response(200, ['Content-Type' => 'video/mp4'], $stream);
        }
    );
});
{% endhighlight %}

We open the file in a **read mode** and receive its stream. Then this stream is used as a response body. We also have changed `Content-Type` header to `video/mp4` to notify our browser that we are sending a video in the response. There is no need to specify a `Content-Length` header because behind the scenes ReactPHP will automatically use chunked transfer encoding and send the respective header `Transfer-Encoding: chunked`.

Now refresh the browser and watch the streaming video:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streaming-server-bunny-video.gif" alt="simple streaming server" class="">
</p>


Really cool! We have a streaming video server with several lines of code!

Now instead of returning `Response` object we return a promise that resolves with this object. Why? We are dealing with a filesystem which is definitely slow. When it takes some time for the request to be processed (often more than 1ms) it **should** be wrapped into a promise. This prevents the server from slowing down.

## Improvements

On the next step, we can improve a little our server. Let's say that a user can specify in the query string a file name to be streamed. For example, when users type in the browser: `http://127.0.0.1:8000/?video=bunny.mpg` the server starts streaming file `bunny.mpg`. We will store our files for streaming in `media` directory. Now we need somehow to get the query parameters from the request. Request object that we receive in the request handler has method `getQueryParams` which returns an array of the GET query, similar to global variable `$_GET`:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($filesystem) {
    $params = $request->getQueryParams();
    $fileName = $params['video'] ?? null;

    if ($fileName === null) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $fileName;
    $file = $filesystem->file($filePath);

    return $file->open('r')->then(
        function (ReadableStreamInterface $stream) {
            return new Response(200, ['Content-Type' => 'video/mp4'], $stream);
        }
      );
});
{% endhighlight %}

Now to view `bunny.mpg` video, we can visit `http://127.0.0.1:8000?video=bunny.mp4` in the browser. The server checks the incoming request for GET parameters. If it finds `video` parameter we assume that it is a video file name, which user wants to be streamed. Then we build a path to this file, open it in a *readable mode* and pass its stream to the response. But there are two issues here. Do you see them?

- What if there is no such file on server? We should return 404 page in this case.
- A user can request **any** file on the server. We should allow to request only certain files.

### Checking if file exists
Before opening a file and creating a stream we should check if this file exists on the server. If not we simply return `404` response:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($filesystem) {
    $params = $request->getQueryParams();
    $fileName = $params['video'] ?? null;

    if ($fileName === null) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $fileName;
    $file = $filesystem->file($filePath);

    return $file->exists()
        ->then(
            function () use ($file) {
                return $file->open('r')
                    ->then(function (ReadableStream $stream) {
                        return new Response(200, ['Content-Type' => 'video/mp4'], $stream);
                    });
            },
            function () {
                return new Response(404, ['Content-Type' => 'text/plain'], "This video doesn't exist on server.");
            });
});
{% endhighlight %}


This code looks a bit complex because we have to sequence calls to the filesystem. At first we check the file existence and only then we open it and send a response. Method `exists()` return a promise that resolves if the file exists, otherwise it rejects. We add two handlers to this promise. The first one is being called if the requested file exists, so we open it and return a response. The second handler returns a `404` response.

Now our server doesn't crash when a user requests a wrong file. It responds with a correct message:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/video-streaming-server-404.png" alt="video streaming 404 error" class="">
</p>

But these nested promises don't look nice, let's fix it. `$file->open('r')` call returns a promise that resolves with an instance of a readable stream. Instead of working with this promise we can *extract* a stream from it. Replace one `then` block with a helper function `\React\Promise\Stream\unwrapReadable()`. This function returns a readable stream, which acts as a proxy for the future promise resolution:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($filesystem) {
    $params = $request->getQueryParams();
    $fileName = $params['video'] ?? null;

    if ($fileName === null) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $fileName;
    $file = $filesystem->file($filePath);

    return $file->exists()
        ->then(
            function () use ($file) {
                return new Response(200, ['Content-Type' => 'video/mp4'], unwrapReadable($file->open('r')));
            },
            function () {
                return new Response(404, ['Content-Type' => 'text/plain'], "This video doesn't exist on server.");
            });
});
{% endhighlight %}

### Allowing to request only certain files

But still, there is an issue with requesting files. A user can request **any** file on the server. For example, if our server code is located in `server.php` file and we check this URL in the browser `http://127.0.0.1:8000/?video=../server.php` the result will be the following:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streaming-server-hack.png" alt="video streaming 404 error" class="">
</p>

Not very secure... To fix it, we can use `basename` function to grab only the file name from the request and cut out the path if it was specified:

{% highlight php %}
<?php

// ...

$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);

// ...

{% endhighlight %}

Now for the same URL we will receive 404 page. Fixed.

## Refactoring
Actually, the server is ready, but the main logic, which is placed in the request handler doesn't look very nice. Of course, if you are not going to change or extend it, you can keep it as it is, right in a callback. But if the server logic is going to change, for example instead of a plain text we would like to render some HTML pages this callback will grow and very soon it will become hard to understand and maintain. Let's make some refactoring and extract this logic into its own `VideoStreaming` class. To be able to use this class as a *callable* request handler we should implement a magic `__invoke()` method in it. And then we can simply pass an instance of this class as a callback to the `Server` constructor:

{% highlight php %}
<?php 

// ... 

$loop = Factory::create();

$videoStreaming = new VideoStreaming(Filesystem::create($loop));

$server = new Server($videoStreaming);
{% endhighlight %}

Now we can start building `VideoStreaming` class. It requires a single dependency - an instance of the `FilesystemInterface` which will be injected through the constructor. At first, we can simply copy-and-paste the code from a request callback into the `__invoke()` method and then start refactoring it:

{% highlight php %}
<?php

final class VideoStreaming
{
    // ... 

    /**
     * @param ServerRequestInterface $request
     * @return Response|PromiseInterface
     */
    function __invoke(ServerRequestInterface $request)
    {
        $params = $request->getQueryParams();
        $file = $params['video'] ?? null;

        if ($file === null) {
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
        }

        $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $fileName;
        $file = $filesystem->file($filePath);

        return $file->exists()
            ->then(
                function () use ($file) {
                    return new Response(200, ['Content-Type' => 'video/mp4'], unwrapReadable($file->open('r')));
                },
                function () {
                    return new Response(404, ['Content-Type' => 'text/plain'], "This video doesn't exist on server.");
                }
            );
    }
}
{% endhighlight %}

Next, we can refactor this `__invoke` method. Let's figure out what is happening here:

1. We parse the request query and determine the file which user has requested.
2. Create a stream from this file and send it back as a response.

So, it looks like we can extract two methods here:

{% highlight php %}
<?php 

final class VideoStreaming
{
    // ...

    /**
     * @param ServerRequestInterface $request
     * @return Response|PromiseInterface
     */
    function __invoke(ServerRequestInterface $request)
    {
        $file = $this->getFilePath($request);
        if ($file === null) {
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
        }
 
       return $this->makeResponseFromFile($file);
    }

    /**
     * @param ServerRequestInterface $request
     * @return string|null
     */
    private function getFilePath(ServerRequestInterface $request)
    {
        // ...
    }

    /**
     * @param string $filePath
     * @return Response
     */
    private function makeResponseFromFile($filePath)
    {
        // ...
    }
}
{% endhighlight %}

The first `getFilePath` is very simple. We receive request parameters with `$request->getQueryParams()` method. Then if there is no `file` key there we simply return `null`, which means that a user has opened the server without any GET parameters. In this case, we could show a static page or something like this. Now we return a simple plain text message `Video streaming server`. If a user has specified `file` in GET request we create a path to this file and return it:

{% highlight php %}
<?php 

final class VideoStreaming
{
    // ...

    /**
     * @param ServerRequestInterface $request
     * @return string|null
     */
    private function getFilePath(ServerRequestInterface $request)
    {
        $file = $request->getQueryParams()['file'] ?? null;
        if ($file === null) {
            return null;
        }

        return __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);
    }

    // ... 
}
{% endhighlight %}

Method `makeResponseFromFile()` will be also very simple. If there is no file for the given path we immediately return a 404 response. Otherwise, we open this file, receive its readable stream and return it as a response body:

{% highlight php %}
<?php 

final class VideoStreaming
{
    // ...

    /**
     * @param string $filePath
     * @return PromiseInterface
     */
    private function makeResponseFromFile($filePath)
    {
        $file = $this->filesystem->file($filePath);

        return $file->exists()
            ->then(
                function () use ($file) {
                    return new Response(200, ['Content-Type' => 'video/mp4'], unwrapReadable($file->open('r')));
                },
                function () {
                    return new Response(404, ['Content-Type' => 'text/plain'], "This video doesn't exist on server.");
                }
            );
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

final class VideoStreaming
{
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param ServerRequestInterface $request
     * @return Response|PromiseInterface
     */
    function __invoke(ServerRequestInterface $request)
    {
        $file = $this->getFilePath($request);
        if ($file === null) {
            return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
        }

        return $this->makeResponseFromFile($file);
    }

    /**
     * @param string $filePath
     * @return PromiseInterface
     */
    private function makeResponseFromFile($filePath)
    {
       $file = $this->filesystem->file($filePath);

        return $file->exists()
            ->then(
                function () use ($file) {
                    return new Response(200, ['Content-Type' => 'video/mp4'], unwrapReadable($file->open('r')));
                },
                function () {
                    return new Response(404, ['Content-Type' => 'text/plain'], "This video doesn't exist on server.");
                }
          );
    }

    /**
     * @param ServerRequestInterface $request
     * @return string|null
     */
    private function getFilePath(ServerRequestInterface $request)
    {
        $file = $request->getQueryParams()['file'] ?? null;
        if ($file === null) {
            return null;
        }

        return __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);
    }
}
{% endhighlight %}

Of course, instead of a simple request handler callback now we have 3 times more code, but if this code is going to be changed in the future it will be much easier to make these changes. 

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/http){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

If you are interested in ReactPHP watch [this conference talk](https://www.youtube.com/watch?v=giCIozOefy0){:target="_blank"} by [Christian Lück](https://twitter.com/another_clue){:target="_blank"} where he explains the main ideas behind ReactPHP.

{% include book_promo.html %}
