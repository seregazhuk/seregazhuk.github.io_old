---
title: "Fast Web Scraping With ReactPHP. Part 2: Throttling Requests"
tags: [PHP, Event-Driven Programming, ReactPHP, Symfony Components]
layout: post
description: "Throttling the number of concurrent asynchronous web-requests with a simple in-memory queue in ReactPHP"
image: "/assets/images/posts/fast-webscraping-reactphp-throttling/throttling-simpsons.jpg"

---

Scraping allows transforming the massive amount of unstructured HTML on the web into the structured data. A good scraper can retrieve the required data much quicker than the human does.  In the [previous article]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"}), we have built a simple asynchronous web scraper. It accepts an array of URLs and makes asynchronous requests to them. When responses arrive it parses data out of them. Asynchronous requests allow to increase the speed of scraping: instead of waiting for all requests being executed one by one we run them all at once and as a result we wait only for the slowest one. 

It is very convenient to have a single HTTP client which can be used to send as many HTTP requests as you want concurrently. But at the same time, a bad scraper which performs hundreds of concurrent requests per second can impact the performance of the site being scraped. Since the scrapers don't drive any human traffic on the site and just affect the performance, some sites don't like them and try to block their access. The easiest way to prevent being blocked is to crawl *nicely* with auto throttling the scraping speed (limiting the number of concurrent requests). The faster you scrap, the worse it is for everybody. The scraper should look like a human and perform requests accordingly.

A good solution for throttling requests is a simple queue. Let's say that we are going to scrap 100 pages, but want to send only 10 requests at a time. To achieve this we can put all these requests in the queue and then take the first 10 quests. Each time a request becomes complete we take a new one out of the queue.

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscraping-reactphp-throttling/throttling-requests.png"  alt="logo">
</p>

## Queue Of Concurrent Requests

For a simple task like web scraping such powerful tools like RabbitMQ, ZeroMQ or Kafka can be overhead. Actually, for our scraper, all we need is a simple *in-memory* queue. And ReactPHP ecosystem already has a solution for it: [clue/mq-react](https://github.com/clue/php-mq-react){:target="_blank"} a library written by [Christian Lück](https://twitter.com/another_clue){:target="_blank"}. Let's figure out how can we use it to throttle multiple HTTP requests.

First things first we should install the library:

{% highlight bash %}
composer require clue/mq-react:^1.0
{% endhighlight %}

Well, here the problem we need to solve: 
>*create a queue of HTTP requests and execute a certain amount of them at a time.*

For making HTTP queries we use an asynchronous HTTP client for ReactPHP [clue/buzz-react](https://github.com/clue/php-buzz-react){:target="_blank"}. The snippet below executes two concurrent requests to [IMDB](http://www.imdb.com){:target="_blank"}:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;

$loop = React\EventLoop\Factory::create();
$browser = new Browser($loop);

$urls = [
    'http://www.imdb.com/title/tt1270797/',
    'http://www.imdb.com/title/tt2527336/',
];

foreach ($urls as $url) {
    $browser->get($url)->then(function($response) {
        // handle response
    });
}

$loop->run();
{% endhighlight %}

Now, let's perform the same task but with the queue. First of all, we need to instantiate a queue (create an instance of `Clue\React\Mq\Queue`). It allows to concurrently execute the same handler (callback that returns a promise) with different (or same) arguments:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;
use Clue\React\Mq\Queue;

$loop = React\EventLoop\Factory::create();
$browser = new Browser($loop);

$queue = new Queue(2, null, function($url) use ($browser) {
    return $browser->get($url);
});
{% endhighlight %}

In the snippet above we create a queue. This queue allows execution only for two handlers at a time. Each handler is a callback which accepts an `$url` argument and returns a promise via `$browser->get($url)`. Then this `$queue` instance can be used to queue the requests:

{% highlight php %}
<?php

$urls = [
    'http://www.imdb.com/title/tt1270797/',
    'http://www.imdb.com/title/tt2527336/',
    'http://www.imdb.com/title/tt4881806/',
];

foreach ($urls as $url) {
    $queue($url)->then(function($response) {
        echo $response;
    });
}

$loop->run();
{% endhighlight %}

In the snippet above the `$queue` instance is *called* as a function. Class `Clue\React\Mq\Queue` can be invokable and accepts any number of arguments. All these arguments will be passed to the handler wrapped by the queue. Consider calling `$queue($url)` as placing a `$browser->get($url)` call into a queue. From this moment the queue controls the number of concurrent requests. In our queue instantiation, we have declared `$concurrency` as 2 meaning only two concurrent requests at a time. While two requests are being executed the others are waiting in the queue. Once one of the requests is complete (the promise from `$browser->get($url)` is resolved) a new request starts. 

## Scraper With Queue

Here is the source code of the scraper for [IMDB](http://www.imdb.com){:target="_blank"} movie pages built on top of [clue/buzz-react](https://github.com/clue/php-buzz-react){:target="_blank"} and [Symfony DomCrawler Component](https://symfony.com/doc/current/components/dom_crawler.html){:target="_blank"}:

{% highlight php %}
<?php

class Parser
{
    /**
     * @var Browser
     */
    private $client;

    /**
     * @var array
     */
    private $parsed = [];

    /**
     * @var LoopInterface
     */
    private $loop;

    public function __construct(Browser $client, LoopInterface $loop)
    {
        $this->client = $client;
        $this->loop = $loop;
    }

    public function parse(array $urls = [], $timeout = 5)
    {
        foreach ($urls as $url) {
             $promise = $this->client->get($url)->then(
                function (\Psr\Http\Message\ResponseInterface $response) {
                   $this->parsed[] = $this->extractFromHtml((string) $response->getBody());
                });

             $this->loop->addTimer($timeout, function() use ($promise) {
                 $promise->cancel();
             });
        }
    }

    public function extractFromHtml($html)
    {
        $crawler = new Crawler($html);

        $title = trim($crawler->filter('h1')->text());
        $genres = $crawler->filter('[itemprop="genre"] a')->extract(['_text']);
        $description = trim($crawler->filter('[itemprop="description"]')->text());

        $crawler->filter('#titleDetails .txt-block')->each(
            function (Crawler $crawler) {
                foreach ($crawler->children() as $node) {
                    $node->parentNode->removeChild($node);
                }
            }
        );

        $releaseDate = trim($crawler->filter('#titleDetails .txt-block')->eq(3)->text());

        return [
            'title'        => $title,
            'genres'       => $genres,
            'description'  => $description,
            'release_date' => $releaseDate,
        ];
    }

    public function getMovieData()
    {
        return $this->parsed;
    }
}
{% endhighlight %}

Class `Parser` via method `parse($urls)` accepts an array of [IMDB](http://www.imdb.com){:target="_blank"} URLs and then sends asynchronous requests to these pages. When responses arrive method `extractFromHtml($html)` scraps data out of them. The following code can be used to scrap data about two movies and then print this data to the screen:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$parser = new Parser($client, $loop);
$parser->parse([
    'http://www.imdb.com/title/tt1270797/',
    'http://www.imdb.com/title/tt2527336/',
]);

$loop->run();
print_r($parser->getMovieData());
{% endhighlight %}

>*If you want a more detailed explanation of building this scraper read the previous post ["Fast Web Scraping With ReactPHP"]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"}.*

From the previous section we have already learned that the process of queuing the requests consists of two steps:

- instantiate a queue providing a concurrency limit and a handler
- add asynchronous calls to the queue

To integrate a queue with the `Parser` class we need to update its method `parse(array $urls, $timeout = 5)` which sends asynchronous requests. At first, we need to accept a new argument for concurrency limit and then instantiate a queue providing this limit and a handler:

{% highlight php %}
<?php

class Parser
{
    // ...

    public function parse(array $urls = [], $timeout = 5, $concurrencyLimit = 10)
    {
        $queue = new Clue\React\Mq\Queue($concurrencyLimit, null, function ($url) {
            return $this->client->get($url);
        });

        // ...
    }

    // ...
}
{% endhighlight %}

As a handler we use `$this->client->get($url)` call which makes an asynchronous request to a specified URL and returns a promise. Once the request is done and the response is received the promise fulfills with this response. 

Then the next step is to invoke the queue with the specified URLs. Now, the `$queue` variable is a placeholder for `$this->client->get($url)` call, but this call is being taken from the queue. So, we can just replace this call with `$queue($url)`:

{% highlight php %}
<?php

class Parser
{
    // ...

    public function parse(array $urls = [], $timeout = 5)
    {
        $queue = new Clue\React\Mq\Queue($concurrencyLimit, null, function ($url) {
            return $this->client->get($url);
        });

        foreach ($urls as $url) {
            /** @var Promise $promise */
            $promise = $queue($url)->then(
                function (\Psr\Http\Message\ResponseInterface $response) {
                    $this->parsed[] = $this->extractFromHtml((string)$response->getBody());
                }
            );

            $this->loop->addTimer($timeout, function () use ($promise) {
                $promise->cancel();
            });
        }
    }

    // ...
}
{% endhighlight %}

And we are done. All the *limiting concurrency* logic is hidden from us and is handled by the queue. Now, to scrap the pages with only 10 concurrent requests at a time we should call the `Parser` like this:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$parser = new Parser($client, $loop);
$urls = [
    // pages to scrap
];
$parser->parse($urls, 2, 10);

$loop->run();
print_r($parser->getMovieData());
{% endhighlight %}

Method `parse()` accepts an array of URLs to scrap, then a timeout for each request and the last argument is a concurrency limit.

## Conclusion

It is a good practice to use throttling for concurrent requests to prevent the situation with sending hundreds of such requests and thus a chance of being blocked by the site. In this article I've shown a quick overview of how you can use a lightweight in-memory queue in conjunction with HTTP client to limit the number of concurrent requests.

More detailed information about [clue/php-mq-react](https://github.com/clue/php-mq-react){:target="_blank"} library you can find in [this post](https://www.lueck.tv/2018/introducing-mq-react){:target="_blank"} by [Christian Lück](https://twitter.com/another_clue){:target="_blank"}.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/web-scraping){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}

