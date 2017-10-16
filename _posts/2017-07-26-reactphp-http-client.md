---
title: "Making Asynchronous HTTP Requests With ReactPHP"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Downloading files in parallel by making asynchronous http requests in PHP with ReactPHP"
---

## The Problem
We need to perform batches of HTTP requests. For example, we need to download several video files. We can start downloading them one by one, but it will take a lot of time since we need to wait for each request to be finished before we can start a new one. The larger the number of requests we are dealing with, the more this latency grows. We also cannot perform any other operations until all files will be downloaded.

In an asynchronous way, there is no need to wait until the last request is being finished. We can start processing the results immediately when any of the requests are being finished. ReactPHP has [HttpClient](http://reactphp.org/http-client/) component which allows you to send HTTP requests asynchronously.

## ReactPHP HttpClient

The component itself is very simple. To start sending requests we need an instance of the `React\HttpClient\Client` class:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);
{% endhighlight %}

`Client` class is very simple and its interface consists of the one `request()` method. It accepts a request method, URL, optional additional headers and returns an instance of the `React\HttpClient\Request` class. Let's create a `GET` request to `https://php.net`:
{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$request = $client->request('GET', 'http://php.net/');

$loop->run();
{% endhighlight %}

By this moment we are not executing any real requests, we have only *prepared* a request. Next step is to set up it.

`React\HttpClient\Request` class implements `WritableStreamInterface`, so we can attach handlers for some events of this stream. Now the most interesting for us is the `response` event. This event is emitted when the response headers were received from the server and successfully parsed:

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

The callback for this event accepts an instance of the `\React\HttpClient\Response` as an argument. This class implements `ReadableStreamInterface` which means that we can also consider it as a stream and read data from it. To receive the response body we can listen to the `data` event. The handler for this event receives a chunk of the response body as an argument. 

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

But if we run this code we still get nothing. This is because there will be no execution till we call `end()` method on the request object:

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

<p class="">
    <img src="/assets/images/posts/reactphp/http-client-basic.gif" alt="http-client-baic" class="">
</p>

## Downloading File

We can use an instance of the `\React\HttpClient\Response` class as a *readable stream* and then *pipe* it to a writable stream as a source. As a result, we can read from the response and write it to a file:

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

>*If you are new to streams check [this]({% post_url 2017-06-12-phpreact-streams %}) article about ReactPHP streams.*

In this example, we download a small video sample using a GET request and stream it to a local file. As soon as the request starts returning chunks of the downloading video it will *pipe* that data to the `sample.mp4` file. 

As a next step, we can add a progress for our download. To track the progress we need to know the total size of the downloading file and the current downloaded size. We can use `getHeaders()` method of the response object to retrieve server headers. We need a `Content-Length` header, which contains the full size of the file:

{% highlight php %}
<?php

$request->on('response', function (\React\HttpClient\Response $response) use ($file) {
    $size = $response->getHeaders()['Content-Length'];
    $currentSize = 0;

    $response->pipe($file);
});
{% endhighlight %}

To get the current downloaded size we can use the length of the received data. We start with zero and then every time we receive a new chunk of data we increase this value by the length of this data:

{% highlight php %}
<?php
$response->on('data', function($data) use ($size, &$currentSize){
    $currentSize += strlen($data);
    echo "Downloading: ", number_format($currentSize / $size * 100), "%\n";
});
{% endhighlight %}

Now we need to *merge* streaming to the local file and tracking the progress. We can wrap the outputting the download progress into an instance of the `\React\Stream\ThroughStream()`. This sort of streams can be used to process data through the pipes, exactly what we need. We write data to this stream, the data is being processed and then we can read the processed data from it. 

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);
$file = new \React\Stream\WritableResourceStream(fopen('sample.mp4', 'w'), $loop);

$request = $client->request('GET', 'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4');

$request->on('response', function (\React\HttpClient\Response $response) use ($file) {
    $size = $response->getHeaders()['Content-Length'];
    $currentSize = 0;

    $progress = new \React\Stream\ThroughStream();
    $progress->on('data', function($data) use ($size, &$currentSize){
        $currentSize += strlen($data);
        echo "Downloading: ", number_format($currentSize / $size * 100), "%\n";
    });

    $response->pipe($progress)->pipe($file);
});

$request->end();
$loop->run();
{% endhighlight %}

In this snippet, we read data from the response. Then *pipe* it to track the download progress and then *pipe* this data to the local file on disk. The progress is showing but the output doesn't look great.

<p class="">
    <img src="/assets/images/posts/reactphp/http-client-bad-output.gif" alt="http-client-bad-output" class="">
</p>

To fix this issue we can use *cursor* movement character. ANSI escape sequences allow moving the cursor around the screen. We can use this sequence to move the cursor *N* lines up `\033[<N>A`. In our case, we need to move the cursor one line up (`\033[1A`). And because we are moving a cursor one line up, we should add one line break before we start showing the progress:


{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);
$file = new \React\Stream\WritableResourceStream(fopen('sample.mp4', 'w'), $loop);

$request = $client->request('GET', 'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4');

$request->on('response', function (\React\HttpClient\Response $response) use ($file) {
    $size = $response->getHeaders()['Content-Length'];
    $currentSize = 0;

    $progress = new \React\Stream\ThroughStream();
    $progress->on('data', function($data) use ($size, &$currentSize){
        $currentSize += strlen($data);
        echo "\033[1A", "Downloading: ", number_format($currentSize / $size * 100), "%\n";
    });

    $response->pipe($progress)->pipe($file);
});

$request->end();
$loop->run();

echo "\n";
{% endhighlight %}

With this simple changes, the output looks pretty nice!

<p class="">
    <img src="/assets/images/posts/reactphp/http-client-download.gif" alt="http-client-download" class="">
</p>


## Parallel Downloading
When our main logic for downloading file is ready we can extract it to a class and improve it for handling multiple parallel downloads. This class will have a single `download()` method which will accept an array of links. When we call `download()` it starts downloading specified files in parallel, like this:

{% highlight php %}
<?php

// ...

$loop = React\EventLoop\Factory::create();
$client = React\HttpClient\Client($loop);

$files = [
    'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4',
    'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_2mb.mp4',
    'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_5mb.mp4',
];

(new Downloader($loop, $client))->download($files);
{% endhighlight %}

This class will be a wrapper over the HTTP client. We also need an instance of the event loop to perform some async operations. So, we require them in the constructor:

{% highlight php %}
<?php

class Downloader
{
     /**
     * @var React\EventLoop\LoopInterface;
     */
    private $loop;

    /**
     * @var \React\HttpClient\Client
     */
    protected $client;

    /**
     * @param Client $client
     * @param LoopInterface $loop
     */
    public function __construct(Client $client, LoopInterface $loop)
    {
        $this->client = $client;
        $this->loop = $loop;
    }

    /**
     * @param array $files
     */
    public function download(array $files)
    {
        // ...
    }
}
{% endhighlight %}

The main process of handling multiple downloads will be the following. For every specified link we:

1. Instantiate a request.
2. Setup all handlers for this request.
3. Store this request in a property.

When all requests are instantiated and configured we walk through them and for each request call `end()` method to start sending data. The last step is to `run()` the event loop:

{% highlight php %}
<?php

class Downloader
{
    // ...

    /**
     * @param array $files
     */
    public function download(array $files)
    {
        foreach ($files as $index => $file) {
            $this->initRequest($file, $index + 1);
        }

        echo str_repeat("\n", count($this->requests));

        $this->runRequests();
    }
}
{% endhighlight %}

Our `initRequest()` method will be very similar to the code from the previous section where we download a single file:

{% highlight php %}
<?php

class Downloader
{
    // ... 

    /**
     * @param string $url
     * @param int $position
     */
    public function initRequest($url, $position)
    {
        $fileName = basename($url);
        $file = new \React\Stream\WritableResourceStream(fopen($fileName, 'w'), $this->loop);

        $request = $this->client->request('GET', $url);
        $request->on('response', function (\React\HttpClient\Response $response) use ($file, $fileName, $position) {
            $size = $response->getHeaders()['Content-Length'];
            $currentSize = 0;

            $progress = new \React\Stream\ThroughStream();
            $progress->on('data', function($data) use ($size, &$currentSize, $fileName, $position){
                $currentSize += strlen($data);
                echo str_repeat("\033[1A", $position), 
                    "$fileName: ", number_format($currentSize / $size * 100), "%", 
                    str_repeat("\n", $position);
            });

            $response->pipe($progress)->pipe($file);
        });

        $this->requests[] = $request;
    }
    // ...
}
{% endhighlight %}

The only difference is that now we need to show several lines of output. That's why we need a `$position` variable which is used to format the output properly according to the specified number of links. All the rest code is the same: we create an instance of the request and setup the handlers. Then we store this request in the `$requests` property. 
We can refactor this method and extract configuring an instance of the `ThroughStream` into its own method:

{% highlight php %}
<?php

class Downloader
{
    // ... 

    /**
     * @param string $url
     * @param int $position
     */
    public function initRequest($url, $position)
    {
        $fileName = basename($url);
        $file = new \React\Stream\WritableResourceStream(fopen($fileName, 'w'), $this->loop);

        $request = $this->client->request('GET', $url);
        $request->on('response', function (\React\HttpClient\Response $response) use ($file, $fileName, $position) {
            $size = $response->getHeaders()['Content-Length'];
            $progress = $this->makeProgressStream($size, $fileName, $position);
            $response->pipe($progress)->pipe($file);
        });

        $this->requests[] = $request;
    }

    /**
     * @param int $size
     * @param string $fileName
     * @param int $position
     * @return \React\Stream\ThroughStream
     */
    protected function makeProgressStream($size, $fileName, $position)
    {
        $currentSize = 0;

        $progress = new \React\Stream\ThroughStream();
        $progress->on('data', function($data) use ($size, &$currentSize, $fileName, $position){
            $currentSize += strlen($data);
            echo str_repeat("\033[1A", $position), 
                "$fileName: ", number_format($currentSize / $size * 100), "%",
                str_repeat("\n", $position);
        });

        return $progress;
    }

    // ...
}
{% endhighlight %}

Now the last step is to *run* the requests. We need to call `end()` method on each request stored in the `$requests` property and then call `run()` method on the event loop. After that we clear the `$requests` property. This is the final version of `Downloader` class:

{% highlight php %}
<?php

class Downloader
{
    /**
     * @var React\EventLoop\LoopInterface;
     */
    private $loop;

    /**
     * @var \React\HttpClient\Client
     */
    protected $client;

    /**
     * @var array
     */
    private $requests = [];

    /**
     * @param Client $client
     * @param LoopInterface $loop
     */
    public function __construct(Client $client, LoopInterface $loop)
    {
        $this->client = $client;
        $this->loop = $loop;
    }

    /**
     * @param string|array $files
     */
    public function download(array $files)
    {
        foreach ($files as $index => $file) {
            $this->initRequest($file, $index + 1);
        }

        echo str_repeat("\n", count($this->requests));

        $this->runRequests();
    }

    /**
     * @param string $url
     * @param int $position
     */
    public function initRequest($url, $position)
    {
        $fileName = basename($url);
        $file = new \React\Stream\WritableResourceStream(fopen($fileName, 'w'), $this->loop);

        $request = $this->client->request('GET', $url);
        $request->on('response', function (\React\HttpClient\Response $response) use ($file, $fileName, $position) {
            $size = $response->getHeaders()['Content-Length'];
            $progress = $this->makeProgressStream($size, $fileName, $position);
            $response->pipe($progress)->pipe($file);
        });

        $this->requests[] = $request;
    }

    /**
     * @param int $size
     * @param string $fileName
     * @param int $position
     * @return \React\Stream\ThroughStream
     */
    protected function makeProgressStream($size, $fileName, $position)
    {
        $currentSize = 0;

        $progress = new \React\Stream\ThroughStream();
        $progress->on('data', function($data) use ($size, &$currentSize, $fileName, $position){
            $currentSize += strlen($data);
            echo str_repeat("\033[1A", $position), 
                "$fileName: ", number_format($currentSize / $size * 100), "%",
                str_repeat("\n", $position);
        });

        return $progress;
    }

    protected function runRequests()
    {
        foreach ($this->requests as $request) {
            $request->end();
        }

        $this->requests = [];

        $this->loop->run();
    }
}
{% endhighlight %}

Then we init an event loop and a client, pass them into the constructor and call `download()` method with a list of links:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

$files = [
    'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_1mb.mp4',
    'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_2mb.mp4',
    'http://www.sample-videos.com/video/mp4/720/big_buck_bunny_720p_5mb.mp4',
];

(new Downloader($client, $loop))->download($files);
{% endhighlight %}

The files are downloaded in parallel:

<p class="">
    <img src="/assets/images/posts/reactphp/http-client-download-parallel.gif" alt="http-client-mutliple-download" class="">
</p>

## Conclusion

When it comes to sending HTTP requests in an asynchronous way [ReactPHP HttpClient](https://github.com/reactphp/http-client) can be a very usefull component. It becomes really powerful when using it with streams and piping the data.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/http-client).

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
