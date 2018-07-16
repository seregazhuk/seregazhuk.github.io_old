---
title: "Fast Web Scraping With ReactPHP. Part 3: Using Proxy"
tags: [PHP, Event-Driven Programming, ReactPHP, Proxy, Web Scraping]
layout: post
description: "ReactPHP tutorial: using proxy for fast anonymous web scraping with ReactPHP"
image: "/assets/images/posts/fast-webscraping-reactphp-proxy/mr-x.jpg"

---

In the [previous article]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"}, we have created a scraper to parse movies data from [IMDB](http://www.imdb.com){:target="_blank"}. We have also [used a simple in-memory queue]({% post_url 2018-03-19-fast-webscraping-with-reactphp-limiting-requests %}){:target="_blank"} to avoid sending hundreds or thousands of concurrent requests and thus to avoid being blocked. But what if you are already blocked? The site that you are scraping has already added your IP to its blacklist and you don't know whether it is a temporal block or a permanent one. 

Such issued can be resolved with a proxy server. Using proxies and rotating IP addresses can prevent you from being detected as a scraper. The idea of rotating different IP addresses while scraping - is to make your scraper look like *real* users accessing the website from different multiple locations. If you implement it right, you drastically reduce the chances of being blocked.

In this article, I will show you how to send concurrent HTTP requests with ReactPHP using a proxy server. We will play around with some concurrent HTTP requests and then we will come back to the scraper, which we have written before. We will update the scraper to use a proxy server for performing requests.

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscraping-reactphp-proxy/mr-x.jpg" style="width: 60%">
</p>

## How to send requests through a proxy in ReactPHP

For sending concurrent HTTP we will use [clue/reactphp-buzz](https://github.com/clue/reactphp-buzz) package. To install it run the following command:

{% highlight bash %}
composer require clue/buzz-react
{% endhighlight %}

Now, let's write a simple asynchronous HTTP request:

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

We create an instance of `Clue\React\Buzz\Browser` which is an asynchronous HTTP client. Then we request Google web page via method `get($url)`. Method `get($url)` returns a promise, which resolves with an instance of `Psr\Http\Message\ResponseInterface`. This snippet above requests `http://google.com` and then prints its HTML.

>*For a more detailed explanation of working with this asynchronous HTTP client check [this]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"} post.*

Class `Browser` is very flexible. You can specify different connection settings, like DNS resolution, TSL parameters, timeouts and of course proxies. All these settings are configured within an instance of `\React\Socket\Connector`. Class `Connector` accepts a loop and then a configuration array. So, let's create one and pass it to our client as a second argument.

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;

$loop = React\EventLoop\Factory::create();

$connector = new \React\Socket\Connector($loop, ['dns' => '8.8.8.8']);
$client = new Browser($loop, $connector);

$client
    ->get('http://google.com/')
    ->then(function (ResponseInterface $response) {
        var_dump($response->getBody());
    });

$loop->run();
{% endhighlight %}

This connector tells the client to use `8.8.8.8` for DNS resolution. 

Before we can start using proxy we need to install [clue/reactphp-socks](https://github.com/clue/reactphp-socks) package:

{% highlight bash %}
composer require clue/socks-react
{% endhighlight %}

This library provides SOCKS4, SOCKS4a and SOCKS5 proxy client/server implementation for ReactPHP. In our case, we need a client. This client will be used to connect to a proxy server. Then our main HTTP client will use this proxy client to send connections through a proxy server.

{% highlight php %}
<?php

$client = new Clue\React\Socks\Client('127.0.0.1:1080', new Connector($loop));
{% endhighlight %}

>*Notice, that this `127.0.0.1:1080` is just a dummy address. Of course, there is no proxy server running on our machine.*

The constructor of `Clue\React\Socks\Client` class accepts an address of the proxy server (`127.0.0.1:1080`) and an instance of the `Connector`. We have already covered `Connector` above. Create an *empty* connector here, with no configuration array. 

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

$client
    ->get('http://google.com/')
    ->then(function (ResponseInterface $response) {
        var_dump((string)$response->getBody());
    });

$loop->run();
{% endhighlight %}


Now, the problem is: where to get a real proxy? 

## Let's find a proxy

On the Internet, you can find many sites dedicated to providing free proxies. For example, you can use [https://www.socks-proxy.net](https://www.socks-proxy.net){:target="_blank"}. Visit it and pick a proxy from *Socks Proxy* list.

In this tutorial, I use `184.178.172.13:15311`.

>*Probably when you read this article this particular proxy wouldn't work. Please, pick another proxy from the site I mentioned above.*

Now, the working example looks like this:

{% highlight php %}
<?php

$proxy = new Client('184.178.172.13:15311', new Connector($loop));
$client = new Browser($loop, new Connector($loop, ['tcp' => $proxy]));

$client
    ->get('http://google.com/')
    ->then(
        function (ResponseInterface $response) {
            var_dump((string)$response->getBody());
        }, 
        function (Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
        });

$loop->run();
{% endhighlight %}

Notice, that I have added an *onRejected* callback. A proxy server might not work (especially a free one), thus it would be useful to show an error if our request has failed. Run the code and you will see HTML code of Google main page.

## Updating the scraper

To refresh the memory here is the consumer code of the scraper [from the previous article]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"}:

{% highlight php %}
<?php 

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$scraper = new Scraper($client, $loop);
$scraper->scrape([
    'http://www.imdb.com/title/tt1270797/',
    'http://www.imdb.com/title/tt2527336/',
], 40);

$loop->run();
print_r($scraper->getMovieData());
{% endhighlight %}

We create an event loop. Then we create an instance of `Clue\React\Buzz\Browser`. The scraper uses this instance to perform concurrent requests. We scrape two URLs with 40 seconds timeout. As you can see we even don't need to touch the scraper's code. All we need is to update `Browser` constructor and provide a `Connector` configured for using a proxy server. At first, create a proxy client with an empty connector:

{% highlight php %}
<?php

$proxy = new SocksClient('184.178.172.13:15311', new Connector($loop));
{% endhighlight %}

Then we need a new connector for `Browser` with a configured `tcp` option, where we provide our client:

{% highlight php %}
<?php

$connector = new Connector($loop, ['tcp' => $proxy]);
{% endhighlight %}

And the last step is to update `Browser` constructor by providing a connector:

{% highlight php %}
<?php

$client = new Browser($loop, $connector);
{% endhighlight %}

The updated *proxy version* looks the following:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use React\Socket\Connector;
use Clue\React\Socks\Client as SocksClient;

$loop = React\EventLoop\Factory::create();

$proxy = new SocksClient('184.178.172.13:15311', new Connector($loop));
$connector = new Connector($loop, ['tcp' => $proxy]);

$client = new Browser($loop, $connector);

$scraper = new Scraper($client, $loop);
$scraper->scrape([
    'http://www.imdb.com/title/tt1270797/',
    'http://www.imdb.com/title/tt2527336/',
    // ...
    ], 40);

$loop->run();
print_r($scraper->getMovieData());
{% endhighlight %}

But, as I have mentioned before proxies might not work. It will be nice to know why we have scrapped nothing. So, it looks like we still have to update a scraper's code and add errors handling. The part of the scraper which performs HTTP requests looks the following:

{% highlight php %}
<?php

class Scraper
{
    /**
    * @var Browser
    */
    private $client;

    /**
    * @var array
    */
    private $scraped = [];

    /**
    * @var LoopInterface
    */
    private $loop;

    public function __construct(Browser $client, LoopInterface $loop)
    {
        $this->client = $client;
        $this->loop = $loop;
    }

    public function scrape(array $urls = [], $timeout = 5)
    {
        $this->scraped = [];

        foreach ($urls as $url) {
            $promise = $this->client->get($url)->then(
            function (\Psr\Http\Message\ResponseInterface $response) {
                $this->scraped[] = $this->extractFromHtml((string)$response->getBody());
            });

            $this->loop->addTimer($timeout, function () use ($promise) {
                $promise->cancel();
            });
        }
    }

    public function extractFromHtml($html)
    {
        // parsing the data
    }

    public function getMovieData()
    {
        return $this->scraped;
    }
}
{% endhighlight %}

The *request* logic is located inside `scrape()` method. We loop through specified URLs and perform a concurrent request for each of them. Each request returns a promise. As an *onFulfilled* handler, we provide a closure where the response body is being scraped. Then, we set a timer to cancel a promise and thus a request by timeout. One thing is missing here. There is no error handling for this promise. When the parsing is done there is no way to figure out what errors have occurred. It will be nice to have a list of errors, where we have URLs as keys and appropriate errors as values.
So, let's add a new `$errors` property and a getter for it:

{% highlight php %}
<?php

class Scraper
{
    // ...

    /**
    * @var array
    */
    private $errors = [];

    // ...

    public function getErrors() 
    {
        return $this->errors;
    }
}    
{% endhighlight %}


Then we need to update method `scrape()` and add a *rejection* handler for the request promise:

{% highlight php %}
<?php

$promise = $this->client
    ->get($url)
    ->then(
        function (\Psr\Http\Message\ResponseInterface $response) {
            $this->scraped[] = $this->extractFromHtml((string)$response->getBody());
        },
        function (Exception $exception) use ($url) {
            $this->errors[$url] = $exception->getMessage();
        });
{% endhighlight %}

When an error occurs we store it inside `$errors` property with an appropriate URL. Now we can keep track of all the errors during the scraping. Also, before scrapping don't forget to instantiate `$errors` property with an empty array. Otherwise, we will continue storing old errors. Here is an updated version of `scrape()` method:

{% highlight php %}
<?php

public function scrape(array $urls = [], $timeout = 5)
{
    $this->scraped = [];
    $this->errors = [];

    foreach ($urls as $url) {
        $promise = $this->client->get($url)->then(
        function (\Psr\Http\Message\ResponseInterface $response) {
            $this->scraped[] = $this->extractFromHtml((string)$response->getBody());
        },
        function (Exception $exception) use ($url) {
            $this->errors[$url] = $exception->getMessage();
        });

        $this->loop->addTimer($timeout, function () use ($promise) {
            $promise->cancel();
        });
    }
}
{% endhighlight %}

Now, the consumer code can be the following:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use React\Socket\Connector;
use Clue\React\Socks\Client as SocksClient;

$loop = React\EventLoop\Factory::create();

$proxy = new SocksClient('184.178.172.13:15311', new Connector($loop));
$connector = new Connector($loop, ['tcp' => $proxy]);
$client = new Browser($loop, $connector);

$scraper = new Scraper($client, $loop);
$scraper->scrape([
    'http://www.imdb.com/title/tt1270797/',
    'http://www.imdb.com/title/tt2527336/',
    // ...
    ], 40);

$loop->run();
print_r($scraper->getMovieData());
print_r($scraper->getErrors());
{% endhighlight %}

At the end of this snippet, we print both scraped data and errors. A list of errors can be very useful. In addition to the fact that we can track dead proxies, we can also detect whether we are banned or not.

## What if my proxy requires authentication?

All these examples above work fine for free proxies. But when you are serious about scraping chances high that you have  private proxies. In most cases they require authentication. Providing your credentials is very simple, just update your proxy connection string like this:

{% highlight php %}
<?php

$proxy = new SocksClient('username:password@184.178.172.13:15311', new Connector($loop));
{% endhighlight %}

But keep in mind that if you credentials contain some special characters they should be encoded:

{% highlight php %}
<?php

$user = 'test';
$pass = 'p:s@w';
$connectionString = rawurlencode($user) . ':' . rawurlencode($pass) . '@184.178.172.13:15311';
$proxy = new SocksClient($connectionString, new Connector($loop));
{% endhighlight %}

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/web-scraping/proxy){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
