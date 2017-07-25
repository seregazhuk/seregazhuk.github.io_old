---
title: "Making Async HTTP Requests With ReactPHP"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Making asynchronous http requests in PHP with ReactPHP"
---

## The problem
We need to perform batches of http requests. For example we decided to parse [PHP documentation](http://php.net/manual/en/langref.php) from [php.net](http://php.net). We can start parsing page by page with cURL, but it will take a lot of time since we need to wait for each request to be finished before we can start a new one. The larger the number of requests you are dealing with, the more this latency grows.
We can perform the first request, parse all links that we need and then call them asynchronously. In the asynchronous way there is no need to wait until the last request is finished. We can start processing the results immediately when any of the requests is being finished. ReactPHP has [HttpClient](http://reactphp.org/http-client/) component which allows you to send HTTP requests in an asynchronous way.

## ReactPHP HTTP-Client

The component itself is very simple. To start sending requests we need an instance of the `React\HttpClient\Client` object:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);
{% endhighlight %}

`Client` class is very simple and its interface consists of the one `request()` method. It accepts a request method, URL, and additional headers and returns an instance of the `React\HttpClient\Request` class. Let's create a `GET` request to `https://php.net`:
{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$request = $client->request('GET', 'http://php.net/');

$loop->run();
{% endhighlight %}

By this moment we are not executing any real requests, we have only *prepared* a request. Next step is to set up it.

`React\HttpClient\Request` class implements `WritableStreamInterface`, so we can attach handlers to some events of this stream. Now the most interesting for us is the `response` event. This event is emmited when the response headers were received from the server and successfully parsed:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$request = $client->request('GET', 'http://php.net/');
$request->on('response', function (\React\HttpClient\Response $response) {
    // ...     
});

$loop->run();
{% endhighlight %}

The callback for this event accepts an instance of the `\React\HttpClient\Response` as an argument. This class implements `ReadableStreamInterface` which means that we can also consider it as a stream and read data from it. To receive the reponse body we can listen to the `data` event. The handler for this event recieves a chunk of the response body as an argument. 

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$request = $client->request('GET', 'http://php.net/');
$request->on('response', function (\React\HttpClient\Response $response) {
    $response->on('data', function ($chunk) {
        echo $chunk;
    });
}); 

$loop->run();
{% endhighlight %}

But if we run this code we stil get nothing. This is because there will be no execution till we call `end()` method on the request object:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$request = $client->request('GET', 'http://php.net/');
$request->on('response', function (\React\HttpClient\Response $response) {
    $response->on('data', function ($chunk) {
        echo $chunk;
    });
}); 

$request->end();
$loop->run();
{% endhighlight %}

This method indicates that we have finished sending the request.

## Downloading File

We can use an instance of the `\React\HttpClient\Response` class as a *readable stream* and then *pipe* it to a writable stream as a source. As a result we can read from the response and write it to a file:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$file = new \React\Stream\WritableResourceStream(fopen('sample.mp4', 'w'), $loop);
$request = $client->request('GET', 'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4');

$request->on('response', function (\React\HttpClient\Response $response) use ($file) {
    $response->pipe($file);
});

$request->end();
$loop->run();
{% endhighlight %}

In this example we download a small video sample using a GET request and stream it to a local file. As soon as the request starts returning chunks of the downloading video it will *pipe* that data to the `sample.mp4` file. 

As a next step we can add a progress for our download. To track the progress we need to know the total size of the downloading file and the current downloaded size. We can use `getHeaders()` method of the response object to retrieve server headers. We need `Content-Length` header, wich contains the full size of the file:

{% highlight php %}
<?php

$request->on('response', function (\React\HttpClient\Response $response) use ($file) {
    $size = $response->getHeaders()['Content-Length'];
    $currentSize = 0;

    $response->pipe($file);
});
{% endhighlight %}

To get the current dowloaded size we can use the length of the received data. We start with zero and then every time we receive a new chunk of data we increase this value by the length of this data:

{% highlight php %}
<?php
$response->on('data', function($data) use ($size, &$currentSize){
    $currentSize += strlen($data);
    echo "\033[1A", "Downloading: ", number_format($currentSize / $size * 100), "%\n";
});
{% endhighlight %}


Now we need to *merge* streaming to the local file and outputing the progress. We can wrap the outputing the download progress into an instance of the `\React\Stream\ThroughStream()`. This sort of streams can be used to process data through the pipes, exactly what we need. We write data to this stream, the data is being processed and then we can read the processed data from it.

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);
$file = new \React\Stream\WritableResourceStream(fopen('sample.mp4', 'w'), $loop);

$request = $client->request('GET', 'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4');

$request->on('response', function (\React\HttpClient\Response $response) use ($file) {
    $size = $response->getHeaders()['Content-Length'];
    $currentSize = 0;

    $through = new \React\Stream\ThroughStream();
    $through->on('data', function($data) use ($size, &$currentSize){
        $currentSize += strlen($data);
        echo "\033[1A", "Downloading: ", number_format($currentSize / $size * 100), "%\n";
    });

    $response->pipe($through)->pipe($file);
});

$request->end();
echo "\n";
$loop->run();
{% endhighlight %}

In this snippet we read data from the response. Then *pipe* it to track the download progress and then *pipe* this data to the local file on disk.

<p class="">
    <img src="/assets/images/posts/reactphp/http-client-download.gif" alt="hello server" class="">
</p>
