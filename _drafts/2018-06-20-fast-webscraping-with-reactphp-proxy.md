---
title: "Fast Web Scraping With ReactPHP. Part 3: Using Proxy"
tags: [PHP, Event-Driven Programming, ReactPHP, Proxy]
layout: post
description: "Using proxy for fast anonymous web scraping with ReactPHP"
image: "/assets/images/posts/fast-webscraping-reactphp-throttling/throttling-simpsons.jpg"

---

In the [previous article] we have improved our web scraper and added some throttling. We have used a simple in-memory queue to avoid sending hundreds or thousands of concurrent requests and thus to avoid being blocked. But what if you are already blocked? The site that you are scraping has already added your IP to its blacklist and you don't know whether it is temporal block or a permanent one. 

You can handle it using proxy. Using proxies and rotating IP addresses can prevent you from being detected as a scraper. The idea of rotating different IP addresses while scraping - is to make your scraper look like *real* users accessing the website from different multiple locations. If you implement it right, you drastically reduce the chances of being blocked.

In this article I will show you how to send concurrent HTTP requests with ReactPHP using a proxy. We will play around concurrent HTTP requests and then we will come back to the parser, which we have written in the previous articles. We will update the parser to use a proxy for performing requests.

## How to send requests through a proxy in ReactPHP

For sending concurrent HTTP we will use [clue/reactphp-buzz](https://github.com/clue/reactphp-buzz) package. To install run the following command:

{% highlight bash %}
composer require clue/buzz-react
{% endhighlight %}

Let's write a simple asynchronous HTTP request:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;

$loop = React\EventLoop\Factory::create();

$client = new Browser($loop);

$client->get('http://google.com/')
    ->then(function (ResponseInterface $response) {
        var_dump((string)$response->getBody());
    });

$loop->run();
{% endhighlight %}

We create an instance of `Clue\React\Buzz\Browse` which is an asynchronous HTTP client. Then we request Google web-page via method `get($url)`. Method `get($url)` returns a promise, which resolves with an instance of `Psr\Http\Message\ResponseInterface`. This snippet above requests `http://google.com` and then prints its HTML.

>*For more detailed explanation of working with this asynchronous HTTP client check [this]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"} post.*

Class `Browser` is very flexible. You can specify different connection settings, like DNS resolution, TSL parameters, timeouts and of course proxies. All these settings are configured within an instance of `\React\Socket\Connector`. Class `Connector` accepts a loop and then a configuration array. So, let's create one and pass it to own client as a second argument.

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;

$loop = React\EventLoop\Factory::create();

$connector = new \React\Socket\Connector($loop, ['dns' => '8.8.8.8']);
$client = new Browser($loop, $connector);

$client->get('http://google.com/')
    ->then(function (ResponseInterface $response) {
        var_dump($response->getBody());
    });

$loop->run();
{% endhighlight %}

The connector tells the client to use `8.8.8.8` for DNS resolution. Before we can start using proxy we need to install [clue/reactphp-socks](https://github.com/clue/reactphp-socks) package:

{% highlight bash %}
composer require clue/socks-react
{% endhighlight %}

This library provides SOCKS4, SOCKS4a and SOCKS5 proxy client/server implementation for ReactPHP. In our case we need a client. This client will be used to connect to a proxy server. Then our main HTTP client will use this proxy client to send connections through a proxy server.

{% highlight php %}
<?php

$client = new Clue\React\Socks\Client('127.0.0.1:1080', new Connector($loop));
{% endhighlight %}

The constructor of `Clue\React\Socks\Client` class accepts an address of the proxy server (`127.0.0.1:1080`) and a an instance of the `Connector`. We have already covered `Connector` above. We create an *empty* connector here, with no configuration array. 

Name `Clue\React\Socks\Client` can confuse you, that it is *one more client* in our code. But it is not the same thing as `Clue\React\Buzz\Browser`, it doesn't send requests. Consider it as a connection, not a client. The main purpose of it is to establish a connection to a proxy server. Then the *real* client will use this connection to perform requests.

To use this proxy connection we need to update a *connector* and specify `tcp` option:

{% highlight php %}
<?php

$proxy = new Client('127.0.0.1:1080', new Connector($loop));
$client = new Browser($loop, new Connector($loop, ['tcp' => $proxy]));
{% endhighlight %}

The full code now looks like this:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use Clue\React\Socks\Client;
use React\Socket\Connector;

$loop = React\EventLoop\Factory::create();

$proxy = new Client('127.0.0.1:1080', new Connector($loop));
$client = new Browser($loop, new Connector($loop, ['tcp' => $proxy]));

$client->get('http://google.com/')
    ->then(function (ResponseInterface $response) {
        var_dump((string)$response->getBody());
    });

$loop->run();
{% endhighlight %}


Now, the problem is: where to get a real proxy?

## Let's find a proxy



