---
title: "Making Async HTTP Requests With ReactPHP"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Making asynchronous http requests in PHP with ReactPHP"
---

## The problem
We need to perform batches of http requests. For example we decided to parse [PHP documentation](http://php.net/manual/en/langref.php) from php.net. We can start parsing page by page with cURL, but it will take a lot of time since we need to wait for each request to be finished before we can start a new one. The larger the number of requests you are dealing with, the more this latency grows.
We can perform the first request, parse all links that we need and then call them asynchronously. In the asynchronous way there is no need to wait until the last request is finished. We can start processing the results immediately when any of the requests is being finished. ReactPHP has [HttpClient](http://reactphp.org/http-client/) component which allows you to send HTTP requests in an asynchronous way.

## ReactPHP HTTP-Client

The component itself is very simple. To start sending requests we need an instance of the `React\HttpClient\Client` object:

{% highlight php %}
<?php

require '../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);
{% endhighlight %}

`Client` class is very simple and its interface consists of the one `request()` method.