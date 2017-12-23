---
title: "ReactPHP HTTP Server Middleware"
tags: [PHP, Event-Driven Programming, ReactPHP, Middleware]
layout: post
description: "ReactPHP asynchronous HTTP server middleware tutorial"
image: "/assets/images/posts/reactphp/middleware.png"
---

## What is middleware?

What exactly is middleware? In real application when the request comes to the server it has to go through the different request handlers. For example, it could be authentication, validation, ACL, logger, caching and so on. Consider the request-response circle as an onion and when a request comes in, it has to go through the different layers of this onion, to get to the core. And every middleware is a layer of the onion. It is a callable object that receives the request and can modify it (or modify the response) before passing it to the next middleware in the chain (to the next layer of the onion). 

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/middleware.png" alt="middleware" class="">
</p>

## Defining middleware

Let's start with a simple server example:

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

This code represents a *dummy* server, that returns `Hello world` responses to all incoming requests. But for our needs it is OK. Now, what if we want to log all incoming requests? So, let's add a line with `echo`:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) {
    echo date('Y-m-d H:i:s') . ' ' . $request->getMethod() . ' ' . $request->getUri() . PHP_EOL;
    return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
});
{% endhighlight %}

When we run our server and make a request to it (I use Curl in terminal) we see a log output on the server
console:

<p class="">
  <img src="/assets/images/posts/reactphp/http-server-logging.gif" alt="http-server-logging" class="">
</p>

And now we can extract this logging logic into the *logging middleware*. ReactPHP middleware:

- is a `callable`
- accepts `ServerRequestInterface` as the first argument and optional callable as the second one
- returns a `ResponseInterface` (or any promise which can be consumed by `Promise\resolve` resolving to a `ResponseInterface`)
- calls `$next($request)` to continue chaining to the next middleware or returns explicitly to abort the chain

So, following these rules a logging middleware function will look like this:

{% highlight php %}
<?php

$loggingMiddleware = function(ServerRequestInterface $request, callable $next) {
    echo date('Y-m-d H:i:s') . ' ' . $request->getMethod() . ' ' . $request->getUri() . PHP_EOL;
    return $next($request);
}
{% endhighlight %}

The server constructor can accept an array of callables, where we can pass our middleware:

{% highlight php %}
<?php

$loggingMiddleware = function(ServerRequestInterface $request, callable $next) {
    echo date('Y-m-d H:i:s') . ' ' . $request->getMethod() . ' ' . $request->getUri() . PHP_EOL;
    return $next($request);
};

$server = new Server([
    $loggingMiddleware,
    function (ServerRequestInterface $request) {
        return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";

$loop->run();
{% endhighlight %}

This code does the same logging. When the request comes in our first `$loggingMiddleware` is executed. It prints out a log message to the server console and then passes a request object to the next middleware which returns a response and ends the chain. This is a very simple example and doesn't show the real power of middleware when you have some complicated logic, where you modify request and response objects during the request-response life-cycle. 

## Attaching middleware to a server

For better understanding middleware we can use a simple video streaming server from [one of the previous articles]({% post_url 2017-07-17-reatcphp-http-server %}). Here is the source code for it:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($loop) {
    $params = $request->getQueryParams();
    $file = $params['video'] ?? '';

    if (empty($file)) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);
    @$fileStream = fopen($filePath, 'r');
    if (!$fileStream) {
        return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
    }

    $video = new \React\Stream\ReadableResourceStream($fileStream, $loop);

    return new Response(200, ['Content-Type' => getMimeTypeByExtension($filePath)], $video);
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";
$loop->run();

{% endhighlight %}

How does it work? When you open your browser on URL `127.0.0.1:8000` and don't provide any query params the server returns a blank page with `Video streaming server` message. To open a video in the browser you can specify `video` query param like this: `http://127.0.0.1:8000/?video=bunny.mpg`. If there is a file called `bunny.mpg` in server `media` directory, the server starts streaming this file. Very simple. 

>*`getMimeTypeByExtension()` is a custom function to detect file MIME type by its extension. You can find its implementation in [Video streaming server]({% post_url 2017-07-17-reatcphp-http-server %}) article.*

You can notice that this request handling logic can be separated into three parts:

- **a plain text response**, when there is no `video` query param.
- **404 response**, when a requested file is not found.
- **a streaming response**.

These three parts are good candidates for middleware. Let's start with the first one: `$queryParamMiddleware`. It simply checks query params. If `video` param is present it passes the request to the next middleware, otherwise, it stops the chain and returns a plain text response:

{% highlight php %}
<?php

$queryParamMiddleware = function(ServerRequestInterface $request, callable $next) {
    $params = $request->getQueryParams();

    if (!isset($params['video'])) {
        return new Response(200, ['Content-Type' => 'text/plain'], 'Video streaming server');
    }
    
    return $next($request);
};
{% endhighlight %}

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/http-middleware-server-welcome-message.png" alt="http-middleware-server-welcome-message" class="">
</p>

Then, if the request has reached the second middleware, that means that we have `video` query param. So, we can check if a specified file exists on the server. If not we return 404 response, otherwise we continue chaining to the next middleware:

{% highlight php %}
<?php

$checkFileExistsMiddleware = function(ServerRequestInterface $request, callable $next) {
    $file = $request->getQueryParams()['video'];
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);
    @$fileStream = fopen($filePath, 'r');

    if (!$fileStream) {
        return new Response(404, ['Content-Type' => 'text/plain'], "Video $file doesn't exist on server.");
    }
    
    return $next($request);
};
{% endhighlight %}

>*I'm using `fopen` here to check if a file exists. `file_exists()` call is blocking and may lead to race conditions, so it is not recommended to use it in an asynchronous application.*

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/http-middleware-file-not-found.png" alt="http-middleware-file-not-found" class="">
</p>

And the last third middleware opens a stream, wraps it into ReactPHP `\React\Stream\ReadableResourceStream` object and returns it as a response body. This middleware doesn't accept `$next` argument because it is the last middleware in our chain. But, notice that it `use`s an event loop to create `\React\Stream\ReadableResourceStream` object:

{% highlight php %}
<?php

$videoStreamingMiddleware = function(ServerRequestInterface $request) use ($loop) {
    $file = $request->getQueryParams()['video'];
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);

    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);
    return new Response(200, ['Content-Type' => getMimeTypeByExtension($filePath)], $video);
};
{% endhighlight %}

Now, having all these three middleware we can provide them to the `Server` constructor as an array:

{% highlight php %}
<?php

$server = new Server([
    $queryParamMiddleware,
    $checkFileExistsMiddleware,
    $videoStreamingMiddleware
]);
{% endhighlight %}

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streaming-server-bunny-video.gif" alt="simple streaming server" class="">
</p>

The code looks much cleaner than having all this request handling logic in one callback. Our request-response cycle consists of three layers of the middleware onion: 

- `$queryParamMiddleware`
- `$checkFileExistsMiddleware`
- `$videoStreamingMiddleware`

When the request comes in it has to go through all these layers. And each layer decides whether to continue chaining or we are done and a response should be returned.

When middleware becomes too complicated it can be extracted to its own class, that implements magic `__invoke()` method. This allows customizing middleware on the fly. 

## Modifying response

PHP community has already standardized middleware under [PSR-7: HTTP message interfaces](http://www.php-fig.org/psr/psr-7/), but ReactPHP doesn't provide any interfaces for middleware implementations. So, don't confuse PSR-7 middleware and ReactPHP HTTP middleware. As you can notice ReactPHP middleware doesn't accept the response object, but only request:

{% highlight php %}
<?php

$myMiddleware = function (ServerRequestInterface $request, callable $next) {
    // ...
}
{% endhighlight %}

So, it may look like there is no way to modify the response. But it is not exactly the truth. It may look a little tricky, but you can. Let me show how.

In this example we are going to add some headers to the response. We create a server with an array of two middleware: the first one is going to add a custom header to the resulting response, and the second one simply returns the response:

{% highlight php %}
<?php

$server = new Server([
    function (ServerRequestInterface $request, callable $next) {
        // add custom header
    },
    function (ServerRequestInterface $request) {
         return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);
{% endhighlight %}

So, how can we modify the response returned by the next middleware? We know that the `$next` variable represents the next middleware in the chain, so we can explicitly call it and pass a request object to it:

{% highlight php %}
<?php

$server = new Server([
    function (ServerRequestInterface $request, callable $next) {
        return $next($request);
    },
    function (ServerRequestInterface $request) {
         return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);
{% endhighlight %}

In this snippet, the first middleware simply continues the chain and returns the response from the next middleware. Actually, the result of `$next($request)` statement is not an instance of the `Response` as you might expect. It is an instance of the `PromiseInterface`. So, to get the response object we need to attach *onResolved* handler. The resolved value will be an instance of the `Response` from the next middleware:

{% highlight php %}
<?php

$server = new Server([
    function (ServerRequestInterface $request, callable $next) {
        return $next($request)
            ->then(function(\Psr\Http\Message\ResponseInterface $response) {
                return $response->withHeader('X-Custom', 'foo');
            });
    },
    function (ServerRequestInterface $request) {
        return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);
{% endhighlight %}

And now we can add our custom `X-Custom` header with `foo` value and check if everything works as expected:

<p class="text-center">
    <img src="/assets/images/posts/reactphp/http-middleware-curl-custom-header.png" alt="http-middleware-curl-custom-header" class="">
</p>

I use `Curl` in terminal with `-i` flag to receive the response with headers. You scan ee that the server returns a response from the second middleware with `Hello world` message. And also response headers contain our `X-Custom` header.

## Middleware under the hood of the Server

ReactPHP HTTP Component comes with three middleware implementations:

- `LimitConcurrentRequestsMiddleware`
- `RequestBodyParserMiddleware`
- `RequestBodyBufferMiddleware`

All of them under the hood are included in `Server` class, so there is no need to explicitly pass them. Why these particular middleware? Because they are required to match PHP's request behavior.

### Limiting concurrent requests

`LimitConcurrentRequestsMiddleware` can be used to limit how many next handlers can be executed concurrently. `Server` class tries to determine this number automatically according to your `php.ini` settings (it uses `memory_limit` and `post_max_size` values). But a predefined maximum number of pending handlers is `100`.  This middleware has its own queue. If the number of pending handlers exceeds the allowed limit, the request goes to the queue and its streaming body is paused. Once one of the pending requests is done, the middleware fetches the oldest pending request from the queue and resumes its streaming body.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/limit-concurrent-requests.png" alt="limit-concurrent-requests" class="">
</p>

To demonstrate how it works, we can attach a timer for 2 seconds in one of the middleware to simulate a busy server:

{% highlight php %}
<?php

$server = new Server([
    function(ServerRequestInterface $request, callable $next) use ($loop) {
        $deferred = new \React\Promise\Deferred();
        $loop->addTimer(2, function() use ($next, $request, $deferred) {
            echo 'Resolving request' . PHP_EOL;
            $deferred->resolve($next($request));
        });

        return $deferred->promise();
    },
    function (ServerRequestInterface $request) {
        return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);
{% endhighlight %}

Then when running two *parallel* Curl requests we can see that they both are resolved with a delay of 2 seconds:

<p class="">
  <img src="/assets/images/posts/reactphp/http-middleware-limit-concurrent-requests-middleware.gif" alt="http-middleware-limit-concurrent-requests-middleware" class="">
</p>

And now see what happens if we use `LimitConcurrentRequestsMiddleware` and set the limit to 1:

{% highlight php %}
<?php

$server = new Server([
    new \React\Http\Middleware\LimitConcurrentRequestsMiddleware(1),
    function(ServerRequestInterface $request, callable $next) use ($loop) {
        $deferred = new \React\Promise\Deferred();
        $loop->addTimer(2, function() use ($next, $request, $deferred) {
            echo 'Resolving request' . PHP_EOL;
            $deferred->resolve($next($request));
        });

        return $deferred->promise();
    },
    function (ServerRequestInterface $request) {
        return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);
{% endhighlight %}

<p class="">
  <img src="/assets/images/posts/reactphp/limit-concurrent-requests-middleware-queue.gif" alt="limit-concurrent-requests-middleware-queue" class="">
</p>

The requests are queued. While the first request is being processed, the second one is stored in the middleware's queue. After two seconds, when the first request is done, the second one is dispatched from the queue and then processed. In this way, we have actually removed concurrency and incoming requests are processed by server simply one by one.

### Buffering request body

When POST or PUT request reaches HTTP server we can get access to its body by calling `$request->getParsedBody()`. This method returns an associative array that represents a parsed request body. Under the hood, the server receives a request which body is a stream. So, behind the scenes, `React\Http\Server` at first uses `RequestBodyBufferMiddleware` to buffer this stream in memory. The request is buffered until its body end has been reached and then the next middleware in the chain will be called with a complete, buffered request. And the next middleware is `RequestBodyParserMiddleware`.

### Parsing request body

When a request body is parsed it goes to `RequestBodyParserMiddleware` which actually parses form fields and file uploads. This middleware makes it possible to receive request params, when you call `$request->getParsedBody()`:

{% highlight php %}
<?php

$server = new \React\Http\Server([
    function (ServerRequestInterface $request) {
        print_r($request->getParsedBody());
        return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
    }
]);
{% endhighlight %}

To check how it works we again use Curl from terminal and provide some form data `curl 127.0.0.1:8000 --data "param1=value1&param2=value2"`:

<p class="">
  <img src="/assets/images/posts/reactphp/parse-requests-body-middleware.gif" alt="parse-requests-body-middleware" class="">
</p>

Under the hood this middleware parses requests that use `Content-Type: application/x-www-form-urlencoded` or `Content-Type: multipart/form-data` headers. That allows us to get access to both request body and uploads. 

### Uploading files

To get an instance of the uploaded file you can `$request->getUploadedFiles()`. This method returns an array of `Psr\Http\Message\UploadedFileInterface` instances. Each upload from the submitted request is represented by its own instance:

{% highlight php %}
<?php

$server = new \React\Http\Server([
    function (ServerRequestInterface $request) {
        $files = $request->getUploadedFiles();

        /** @var \Psr\Http\Message\UploadedFileInterface|null $file */
        $file = $files['file'] ?? null;

        return new Response(
            200, 
            ['Content-Type' => 'text/plain'],  
            $file ? $file->getClientFilename() : ''
        );
    }
]);
{% endhighlight %}

For example, we can upload a simple text file from terminal `curl 127.0.0.1:8000 -F "file=@text.txt"` (flag `-F` means `multipart/form-data`):

<p class="">
  <img src="/assets/images/posts/reactphp/parse-requests-body-upload-middleware.gif" alt="parse-requests-body-upload-middleware" class="">
</p>

This code simply returns an uploaded file name as a response.

To store an uploaded file use `UploadedFileInterface::getStream()` method, which returns an instance of the `Psr\Http\Message\StreamInterface`. To get the contents of the uploaded file we can cast this object to a string. For example, this snippet opens a writable stream and stores the contents of the uploaded file in `dest.txt`:

{% highlight php %}
<?php 

$server = new \React\Http\Server([
    function (ServerRequestInterface $request) use ($loop) {
        /** @var \Psr\Http\Message\UploadedFileInterface[] $files */
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;

        if($file) {
            $dest = new \React\Stream\WritableResourceStream(fopen('dest.txt', 'w'), $loop);
            $dest->write($file->getStream());
        }

        return new Response(200, ['Content-Type' => 'text/plain']);
    }
]);
{% endhighlight %}

>*Actually, `UploadedFileInterface` has `moveTo()` method that can be used exactly to store an upload. But this method by its nature is going to be blocking (as a recommended alternative to calling `move_uploaded_file()`), so it can't be used in an asynchronous application. That is the reason why we have to use `getStream()`. Calling `moveTo()` will throw `RuntimeException`.*

## Conclusion
A chain of middleware between the server and your application is a powerful tool to customize the way how your request and response behave. In addition to already built-in `Server` middleware, there is a [list of third-party middleware](https://github.com/reactphp/http/wiki/Middleware) created by ReactPHP community.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/http-server-middleware).

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
