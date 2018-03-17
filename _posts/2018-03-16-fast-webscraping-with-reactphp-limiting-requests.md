---
title: "Fast Web Scraping With ReactPHP: Limiting Concurrency"
tags: [PHP, Event-Driven Programming, ReactPHP, Symfony Components]
layout: post
description: "Limiting the number of concurrent asynchronous web-requests with a simple queue in ReactPHP"
---

In the previous article we [have build]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"} a simple asynchronous web scraper. You should be very accurate with web scraping. If the site doesn't provide any public API for its resources that often means that this site doesn't want that information to be in a public access. In this case the last change to get the required information is to scrap it from the web-pages. When dealing with an asynchronous web scraper we have concurrent requests. To prevent the situation with sending hundreds of concurrent requests and thus a chance being blocked by the site, it is a good practice to limit the number of these requests. In an asynchronous workflow we can organize a simple queue for it. Let's say that we are going to scrap 100 pages, but want to send only 10 requests at a time. To achieve this we can put all these requests in the queue and then take the first 10 quests. Each time a request becomes complete we take a new request out of the queue.

For a simple task like web scraping such tools like RabbitMQ can be overhead. Actually, for our scraper all we need is a simple *in-memory* queue. And ReactPHP ecosystem already has a solution for it: [clue/mq-react](https://github.com/clue/php-mq-react){:target="_blank"} library written by [Christian Lück](https://twitter.com/another_clue){:target="_blank"}. Let's figure out how can we use it to throttle multiple HTTP requests.

First things first we should install the library:

{% highlight bash %}
composer require clue/mq-react:^1.0
{% endhighlight %}

## Queue Of Concurrent Requests

Well, the problem we need to solve is: create a queue of HTTP requests and execute a certain amount of them at a time. For making HTTP queries we use an asynchronous HTTP client [clue/buzz-react](https://github.com/clue/php-buzz-react){:target="_blank"}. The snippet below executes two concurrent requests to [IMDB](http://www.imdb.com):

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

Now, let's perform the same but with the queue. First of all, we need an instance of `Clue\React\Mq\Queue`. It allows to concurrently execute the same handler (callback that returns a promise) with different (or same) arguments:

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

In the snippet above we create a queue. This queue allows execution for only two handlers at a time. Each handler is a callback which accepts `$url` and returns a promise via `$browser->get($url)`. Then this `$queue` instance can be used to queue the requests:

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

In the snippet above the `$queue` instance is *called* as a function. Class `Clue\React\Mq\Queue` can be invokable and accepts any number of arguments. All these arguments will be passed into the handler wrapped by the queue. Consider calling `$queue($url)` as placing a `$browser->get($url)` call into a queue. From this moment the queue controls the number of concurrent requests. In our queue instantiation we have declared `$concurrency` as 2 meaning only two concurrent requests at a time. While two requests are being executed the others are waiting in the queue. Once one of the requests is complete (the promise from `$browser->get($url)` is resolved) a new request starts. 

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

Class `Parser` accepts via method `parse($urls)` an array of [IMDB](http://www.imdb.com){:target="_blank"} movie page URLs and then sends asynchronous requests to them. When responses arrive method `extractFromHtml($html)` scraps data out of them. The following code can be used to scrap data about two movies and then print this data to the screen:

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

We have already learned from the previous section that working with the queue consists of two steps:

- instantiate a queue providing a concurrency limit and a handler
- add asynchronous calls to the queue

To integrate a queue with the `Parser` class we need to update its `parse(array $urls, $timeout = 5)` method with sends asynchronous requests. At first, we need to accept a new argument for concurrency limit and then instantiate a queue providing this limit and a handler:

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

As a handler we use `$this->client->get($url)` call which makes an asynchronous request to a specified URL and returns a promise. Once the request is done and response is received the promise fulfills with this response. Then next step is to invoke the queue with the specified URLs. Now, the `$queue` variable is a placeholder for `$this->client->get($url)` but wrapped into the queue. So, we can just replace this call with `$queue($url)`:

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

And we are done. All the *limiting concurrency* logic is hidden from us and is handled by the queue.
