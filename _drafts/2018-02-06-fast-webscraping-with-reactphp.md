---
title: "Fast Web Scrapping With ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Asynchronously parsing web-pages with ReactPHP"
---

Almost every PHP developer has ever parsed some data from the Web. Often we need some data, which is available only on some web site and we want to pull this data and save it some-where. It looks like we open a browser, walk through the links and copy data that we need. But the same thing can be automated via script. In this tutorial I will show you the way how you can increase the speed of you parser making requests asynchronously.  We are going to use asynchronous HTTP client called [buzz-react](https://github.com/clue/php-buzz-react) written by [Christian Lück](https://twitter.com/another_clue). It is a simple PSR-7 HTTP client for ReactPHP ecosystem.

## The Task

We are going to create a simple web scrapper for parsing movie information from [IMDB](http://www.imdb.com) *Coming Soon* page:

<div class="row">
    <p class="text-center image col-sm-6">
        <img src="/assets/images/posts/fast-webscrapping-reactphp/coming-soon-page.png" 
            alt="coming-soon-page">
    </p>
</div>

We want to get all movies for the upcoming year: 12 pages, a page for each month. Each page has approximately 20 movies. So in common we are going to make 240 requests. Making these requests one after another can take some time...

<div class="row">
    <p class="text-center image col-sm-6">
        <img src="/assets/images/posts/fast-webscrapping-reactphp/months-select.jpg" alt="months-select" class="">
    </p>
</div>

And now imagine that me can run these requests concurrently. In this way the scrapper is going to be significantly fast. Let's try it. For traversing the DOM I'm going to use [Symfony DomCrawler Component](https://symfony.com/doc/current/components/dom_crawler.html).

## Set Up

Before we start writing the scrapper we need to download the required dependencies via composer. 

clue/buzz-react:

{% highlight bash %}
composer require clue/buzz-react
{% endhighlight %}

Symfony DomCrawler:

{% highlight bash %}
composer require symfony/dom-crawler
{% endhighlight %}

CSS-selector for DomCrawler, which allows to use jQuery-like selectors to traverse:

{% highlight bash %}
composer require symfony/css-selector
{% endhighlight %}

Now, we can start coding. This is our start:

{% highlight php %}
<?php

require '../vendor/autoload.php';

use Clue\React\Buzz\Browser;

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

// ...

$loop->run();
{% endhighlight %}

We create an instance of the event loop and HTTP client. The last line in the script actually runs the program. Everything before it is a *setup* section, where we configure the behavior of our async code. 

## Making requests

Public interface of the client's main `Clue\React\Buzz\Browser` class is very straight forward. It has a set of methods named after HTTP verbs: `get()`, `post()`, `put()` and so on. Each method returns a promise. In our case to request a page we can use `get($url, $headers = [])` method:

{% highlight php %}
<?php 

// ...

$client->get('http://www.imdb.com/movies-coming-soon/')
    ->then(function(\Psr\Http\Message\ResponseInterface $response) {
        echo $response->getBody() . PHP_EOL;
    });
{% endhighlight %}

The code above simply outputs the requested page on the screen. When a response is received the promise fulfills with an instance of `Psr\Http\Message\ResponseInterface`. 

>*Unlike [ReactPHP HTTPClient]({% post_url 2017-07-26-reactphp-http-client %}), `clue/buzz-react` buffers the response and fulfills the promise once the whole response is received. Actually, it is a default behavior and you can change it if you need streaming responses.*

