---
title: "ReactPHP HTTP Server Middlewares"
tags: [PHP, Event-Driven Programming, ReactPHP, Middleware]
layout: post
description: "Explaining ReactPHP asynchronous HTTP server middlewares"
---

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

This code represents a *dummy* server, that returns `Hello world` responses to all incoming requests. But for our needs it is OK. Then we decide to log all incoming requests.

## What is middleware?

What exactly is middleware? In real application when the request comes to the server it has to go through the different request handlers. For example, it could be: authentication, validation, ACL, logger and so on. Consider this part of the request-response circle as an onion and when a request comes in, it has to go through the different layers of the onion, to get to the core. And every middleware is a layer of this onion. It is a callable object that receives the request and can modify it before passing it to the next middleware in the chain (to the next layer, deeper into the onion). 

What if we want to log all incoming requests? OK, let's add a line with `echo`:

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

And now we can extract this logging logic into the *logging middleware*. To refresh in memory a middleware:

- is a `callable`
- accepts `ServerRequestInterface` as first argument and optional callable as second argument.
- returns a `ResponseInterface` (or any promise which can be consumed by `Promise\resolve` resolving to a `ResponseInterface`)
- calls `$next($request)` to continue chaining to the next middleware or returns explicitly to abort the chain

So, following this rules a logging middleware function will look like this:

{% highlight php %}
<?php

$loggingMiddleware = function(ServerRequestInterface $request, callable $next) {
    echo date('Y-m-d H:i:s') . ' ' . $request->getMethod() . ' ' . $request->getUri() . PHP_EOL;
    return $next($request);
}
{% endhighlight %}

The the server constructor can accept an array of callables, where we can pass our middleware:


{% highlight php %}
<?php

$loggingMiddleware = function(ServerRequestInterface $request, callable $next) {
    echo date('Y-m-d H:i:s') . ' ' . $request->getMethod() . ' ' . $request->getUri() . PHP_EOL;
    return $next($request);
};

$server = new Server(
[
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

This code does the same logging. When the request comes in our first `$loggingMiddleware` is executed. It prints out log message to the server console and then passes a request object to the next middleware which returns a response and ends the chain. This is very simple example and doesn't show the real power of middlewares, when you have some complicated logic, where you modify request and response objects during the request-response life-cycle. 

## Video Streaming Server

For better understanding middleware we can use a simple video streaming server from [one of the previous articles]({% post_url 2017-07-17-reatcphp-http-server %}). Here is the source code of it:

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

How it works? When you open your browser on page `127.0.0.1:8000` and don't provide any query params it returns a blank page with `Video streaming server` message. To open a video in browser you can specify `video` param like this: `http://127.0.0.1:8000/?video=bunny.mpg`. If there is a file called `bunny.mpg` in server `media` directory, the server starts streaming this file. Very simple. 

>*`getMimeTypeByExtension()` is a custom function to detect file MIME type by its extension. You can find its implementation in [Video streaming server]({% post_url 2017-07-17-reatcphp-http-server %}) article.*

You can notice that this logic can be separated into three parts:

- a plain text response, when there is no `video` query param.
- 404 response, when a requested file is not found.
- a streaming response.

These three parts a good candidates for middlewares. Let's start with the first one: `$queryParamMiddleware`. It simply check query params. If `video` param is present it passes the request to the next middleware, otherwise it returns a plain text response:

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

>*I'm using `fopen` here to check if file exists. `file_exists()` call is blocking and may lead to race conditions.*

And the last third middleware opens a stream, wrapps it into ReactPHP `\React\Stream\ReadableResourceStream` object and returns it as a response body. This middleware doesn't accept `$next` argument, because it is the last middleware in our chain. But, notice that it `use`s an event loop to create `\React\Stream\ReadableResourceStream` object:

{% highlight php %}
<?php

$videoStreamingMiddleware = function(ServerRequestInterface $request) use ($loop) {
    $file = $request->getQueryParams()['video'];
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . basename($file);

    $video = new \React\Stream\ReadableResourceStream(fopen($filePath, 'r'), $loop);
    return new Response(200, ['Content-Type' => getMimeTypeByExtension($filePath)], $video);
};
{% endhighlight %}

Now, having all these three middlewares we can provide them to the `Server` constructor as an array:

{% highlight php %}
<?php

$server = new Server([
    $queryParamMiddleware,
    $checkFileExistsMiddleware,
    $videoStreamingMiddleware
]);
{% endhighlight %}

The code looks much cleaner when having all this request handling logic in one callback. When middlewares become too complicated they can be extracted to their own classes, that implement magic `__invoke()` method. This allows us to customize middlewares on the fly.
