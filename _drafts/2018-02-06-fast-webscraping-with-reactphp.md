---
title: "Fast Web Scrapping With ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Asynchronously parsing web-pages with ReactPHP"
---

Almost every PHP developer has ever parsed some data from the Web. Often we need some data, which is available only on some web site and we want to pull this data and save it some-where. It looks like we open a browser, walk through the links and copy data that we need. But the same thing can be automated via script. In this tutorial I will show you the way how you can increase the speed of you parser making requests asynchronously.  We are going to use asynchronous HTTP client called [buzz-react](https://github.com/clue/php-buzz-react) written by [Christian Lück](https://twitter.com/another_clue). It is a simple PSR-7 HTTP client for ReactPHP ecosystem.

## The Task

We are going to create a simple web scrapper for parsing movie information from [IMDB](http://www.imdb.com) *Coming Soon* page:

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscrapping-reactphp/coming-soon-page.png"  alt="coming-soon-page">
</p>

We want to get all movies for the upcoming year: 12 pages, a page for each month. Each page has approximately 20 movies. So in common we are going to make 240 requests. Making these requests one after another can take some time...

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscrapping-reactphp/months-select.jpg" alt="months-select" class="">
</p>

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

We create an instance of the event loop and HTTP client. The last line in the script actually runs the program. Everything before it is a *setup* section, where we configure the behavior of our asynchronous code. 

## Making Request

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

>*Unlike [ReactPHP HTTPClient]({% post_url 2017-07-26-reactphp-http-client %}), `clue/buzz-react` buffers the response and fulfills the promise once the whole response is received. Actually, it is a default behavior and [you can change it](https://github.com/clue/php-buzz-react#streaming) if you need streaming responses.*

So, as you can see, the whole process of scrapping is very simple:

1. Make a request and receive the promise.
2. Add fulfillment handler to the promise.
3. Inside the handler traverse the response and parse the required data.
4. If needed repeat from step 1.

In our case, to parse all *coming soon* movies we need:

1. Make the request to `http://www.imdb.com/movies-coming-soon`.
2. Parse it and get all links for each upcoming month.
3. Make request to month page.
4. Parse month page and get all links to movies.
5. Make request to movie page and grab all required information from it.

## Parser

Now, when we have defined the algorythm it's time to write some code. We start with an empty `Parser` class. It will be a wrapper over the `buzz-react` `Browser` class:

{% highlight php %}
<?php

class Parser {
    const BASE_URL = 'http://www.imdb.com';

    /**
     * @var Browser
     */
    private $browser;

    public function __construct(Browser $browser)
    {
        $this->browser = $browser->withBase(self::BASE_URL);
    }

    public function parse($url) 
    {
        // ...
    }
}
{% endhighlight %}

In the constructor we set base URL for the client. It will be convenient because we are going to visit only one site and I don't want to build full URLs constantly. The interface is very simple: just one public method `run($url)`. It will accept the relative URL of the page, we are going to parse `movies-coming-soon`. 

What's going to happen inside `parse()` method? Well, actually everything. But don't worry, we are going to break small logic pieces into own methods. So, at first we need to get month links. We make a request, receive the response and then traverse it with Symfony DomCrawler:

{% highlight php %}
<?php

class Parser {

    // ... 

    public function parse($url)
    {
        $this->browser->get($url)
            ->then(function(ResponseInterface $response) {
                $crawler = new Crawler((string)$response->getBody());
                $monthLinks = $crawler->filter('.date_select option')->extract(['value']);
                foreach ($monthLinks as $monthLink) {
                    // ... parse month-page
                }
            }, function(Exception $e){
                echo $e->getMessage();
            });
    }
}
{% endhighlight %}

Inside the fulfillment handler we create an instance of the `Crawler`. This class is responsible for traversing the DOM. Then we `extract` URLs from the months selection. As you can see this `<select>` tag has class `date_select` and each `<option>` inside of it contains URL to an appropriate page in `value` attribute:

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscrapping-reactphp/months-select-dom.png" alt="months-select-dom" class="">
</p>    

So, we use jQuery-like selector `.date_select option` to get filter all `<option>` tags and then `extract(['value'])` returns an array, that contains values for all `value` attributes of the filtered tags. This is how we grab all URLs to month pages. The next step is to parse month-page and grab all links to movies from this page:

{% highlight php %}
<?php

class Parser {

    // ... 

    public function parse($url)
    {
        $this->browser->get($url)
            ->then(function(ResponseInterface $response) {
                $crawler = new Crawler((string)$response->getBody());
                $monthLinks = $crawler->filter('.date_select option')->extract(['value']);
                foreach ($monthLinks as $monthLink) {
                    $this->parseMonthPage($monthLink);
                }
            }, function(Exception $e){
                echo $e->getMessage();
            });
    }

    private function parseMonthPage($monthPageUrl)
    {
        // ...
    }
}
{% endhighlight %} 

Actually from this moment everything is going to be similar: 

 - make the request
 - inside the promise handler create an instance of the `Crawler` with a response body
 - then grab everything you need.

On the month page we need an URL to the movie. This URL can be extracted from the movie title:

<p class="text-center image">
    <img src="/assets/images/posts/fast-webscrapping-reactphp/movie-title-link.png" alt="movie-title-link" class="">
</p>  

All these links has the same selector: `.overview-top h4 a`. And again we filter the tags and then `extract` the required attributes as an array. In this case we are interested in the links `href` attributes:

{% highlight php %}
<?php

class Parser {

    // ... 

    public function parse($url)
    {
        // ...
    }

    private function parseMonthPage($monthPageUrl)
    {
        $this->browser->get($monthPageUrl)
            ->then(function(ResponseInterface $response) {
                $crawler = new Crawler((string)$response->getBody());
                $movieLinks = $crawler->filter('.overview-top h4 a')->extract(['href']);

                foreach ($movieLinks as $movieLink) {
                    // ... parse movie data
                }
            });
    }
}
{% endhighlight %}

And the final step is parsing the movie data. Let's say that we want:
- title
- description
- release date
- genres

<div class="row">
    <p class="text-center image col-sm-6">
        <img src="/assets/images/posts/fast-webscrapping-reactphp/movie-page-title.jpg" 
            alt="movie-page-title">
    </p>
    <p class="text-center image col-sm-6">
        <img src="/assets/images/posts/fast-webscrapping-reactphp/movie-page-other.jpg" 
            alt="movie-page-other">
    </p>
</div>

And again ... make the request and inside the promise handler create a `Crawler` to traverse the DOM:

{% highlight php %}
<?php

class Parser {

    // ... 

    private function parseMovieData($moviePageUrl)
    {
        $this->browser->get($moviePageUrl)
            ->then(function(ResponseInterface $response){
                $crawler = new Crawler((string)$response->getBody());
                $title = $crawler->filter('h1')->text();
                $genres = $crawler->filter('[itemprop="genre"] a')->extract(['_text']);
                $description = trim($crawler->filter('[itemprop="description"]')->text());
    
                // process parsed data
        }, function(Exception $e){
            echo $e->getMessage();
        });
    }
}
{% endhighlight %}

The title is taken from the `h1` tag. Genres are received as text contents of the appropriate links. Here in `->extract(['_text'])` statement special attribute `_text` represents a node value. The description is also taken as a text value from the appropriate tag. Things become a little tricky with a release date:
 
<p class="text-center image">
    <img src="/assets/images/posts/fast-webscrapping-reactphp/release-date.jpg" alt="release-date" class="">
</p>  

As you can see it is inside `<div>` tag, but we cannot simply extract the text from it. In this case the release date will be `Release Date: 16 February 2018 (USA) See more »`. And this is not what we need. Before extracting the text from this DOM element we need to remove all tags inside of it:

{% highlight php %}
<?php

// ...

$crawler->filter('#titleDetails .txt-block')->each(function (Crawler $crawler) {
    foreach ($crawler->children() as $node) {
        $node->parentNode->removeChild($node);
    }
});

$releaseDate = trim($crawler->filter('#titleDetails .txt-block')->eq(2)->text());
{% endhighlight %}

Here we select all `<div>` tags from the *Details* section. Then, we loop through them and remove all child tags. This code makes our `<div>`s free from all inner tags. To get a release date we select the third (at index `2`) element and grab its text (now free from other tags).

At this moment we need to decide how we want to process the parsed data. There are two ways:

 - we can continue running asynchronously and process data as soon as we receive it
 - we can collect all data and then process this collection

Let's try both of them.

## Processing Parsed Data Asynchronously
For example, we can save the parsed data to the file as a json string. But as we are running asynchronously we **must use streams**, no `file_put_contents` calls! Otherwise we will block an event loop. To create a writable stream we use`\React\Stream\WritableResourceStream` class, which requires a resource opened in a writable mode and an event loop. Inside our `Parser` class there is no way to get the loop, so we inject it via the constructor:

{% highlight php %}
<?php

class Parser {

    // ...

    /**
     * @var LoopInterface
     */
    private $loop;

    public function __construct(Browser $browser, LoopInterface $loop)
    {
        $this->browser = $browser->withBase(self::BASE_URL);
        $this->loop = $loop;
    }
}
{% endhighlight %}

Then we can create a writable stream and save parsed movie data to it. I'm going to store these files inside `parsed` folder. File names will be with the following pattern: `$fileName = __DIR__ . '/parsed/' . $title . '.json';` (movie title and `json` extension).

{% highlight php %}
<?php

// ...

$fileName = __DIR__ . '/parsed/' . $title . '.json';
$stream = new \React\Stream\WritableResourceStream(fopen($fileName, 'w'), $this->loop);

$stream->write(json_encode([
    'title' => $title,
    'genres' => $genres,
    'description' => $description,
    'release_date' => $releaseDate
]));
$stream->end();
{% endhighlight %}
