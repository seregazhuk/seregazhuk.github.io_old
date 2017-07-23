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