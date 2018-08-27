---
title: "Fast Web Scraping With ReactPHP: Download all Images from a Website"
tags: [PHP, Event-Driven Programming, ReactPHP, Symfony Components, Web Scraping]
layout: post
description: "Asynchronously parsing images from a website with ReactPHP"
image: "/assets/images/posts/fast-webscraping-reactphp/logo.jpg"
---

## What is Web Scraping?

Have you ever needed to grab some data from site that doesn't provide a public API? To solve this problem we can use web scraping and pull the required information out from the HTML. Of course, we can manually extract the required data from a website, but this process can become very tedious. So, it will be more efficient to automate it via the scraper.

Well, in this tutorial we are going to scrap cats images from [Pexels](https://www.pexels.com/){:target="_blank"}. This website provides high quality and completely free stock photos. They have a public API but it has a limit of 200 requests per hour.

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscraping-reactphp-images/pexels-cats-search.png" style="width: 60%">
</p>

## Making concurrent requests

The main advantage of using asynchronous PHP in web scraping is that we can make a lot of work in less time. Instead of querying each web page one by one and waiting for responses we can request as many pages as we want at once. Thus we can start processing the results as they arrive. 

Let's start with pulling an asynchronous HTTP client called [buzz-react](https://github.com/clue/php-buzz-react){:target="_blank"}:

{% highlight bash %}
composer require clue/buzz-react
{% endhighlight %}

Now, we are ready and lets request an [image page on pexels](https://www.pexels.com/photo/kitten-cat-rush-lucky-cat-45170/){:target="_blank"}:

{% highlight php %}
<?php

require __DIR__ . '/../vendor/autoload.php';

use Clue\React\Buzz\Browser;

$loop = \React\EventLoop\Factory::create();

$client = new Browser($loop);
$client->get('https://www.pexels.com/photo/kitten-cat-rush-lucky-cat-45170/')
    ->then(function(\Psr\Http\Message\ResponseInterface $response) {
        echo $response->getBody();
    });

$loop->run();
{% endhighlight %}

We have created an instance of `Clue\React\Buzz\Browser`, then we have used it as HTTP client. The code above makes an asynchronous `GET` request to an web page with kittens. Method `$client->get($url)` returns a [promise]({% post_url 2017-06-16-phpreact-promises %}){:target="_blank"} that resolves with a contents of the requested page.

The client works asynchronously, that means that we can easily request several pages and these pages will be requested asynchronously:

{% highlight php %}
<?php

require __DIR__ . '/../vendor/autoload.php';

use Clue\React\Buzz\Browser;

$loop = \React\EventLoop\Factory::create();

$client = new Browser($loop);
$client->get('https://www.pexels.com/photo/kitten-cat-rush-lucky-cat-45170/')
    ->then(function(\Psr\Http\Message\ResponseInterface $response) {
        echo $response->getBody();
    });

$client->get('https://www.pexels.com/photo/adorable-animal-baby-blur-177809/')
    ->then(function(\Psr\Http\Message\ResponseInterface $response) {
        echo $response->getBody();
    });

$loop->run();
{% endhighlight %}

The idea is here the following:

- make a request
- get a promise
- add a handler to a promise
- once promise is resolved process the response

So, this logic can be extracted to a class. Let's create a wrapper over the `Browser`. 

Create a class called `Scraper` with the following content:

{% highlight php %}
<?php

use Clue\React\Buzz\Browser;

final class ScraperForImages
{
    private $client;

    public function __construct(Browser $client)
    {
        $this->client = $client;
    }

    public function scrape(array $urls)
    {
        foreach ($urls as $url) {
            $this->client->get($url)->then(
                function (\Psr\Http\Message\ResponseInterface $response) {
                    $this->processResponse((string) $response->getBody());
                });
        }
    }

    private function processResponse(string $html)
    {
        // ...
    }
}
{% endhighlight %}

We inject `Browser` as a constructor dependency and provide one public method `scrape(array $urls)`. Then for each specified URL we make a `GET` request. Once the response is done we call a private method `processResponse(string $html)` with the contents of the response. The next step is to inspect the received HTML code and extract images from it.

## Crawling the website


## Conclusion

In [the previous tutorial]({% post_url 2018-02-12-fast-webscraping-with-reactphp %}){:target="_blank"}, we have used ReactPHP to speed up the process of web scraping and to query web pages concurrently. But what if we also need to save images concurrently? In an asynchronous application we cannot use such native PHP function like `file_get_contents()`, because blocks the flow, so there will be no speed increase in storing images on disk. To process files asynchronously in a non-blocking way in ReactPHP we need to use a package called [reactphp/filesystem](https://github.com/reactphp/filesystem){:target="_blank"}.
