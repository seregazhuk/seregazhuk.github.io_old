---
title: "ReactPHP PromiseStream: From Promise To Stream And Vice Versa"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "How to convert a stream to a promise and vice versa in ReactPHP"
image: "/assets/images/posts/reactphp/promise-stream.png"
---

>*[ReactPHP PromiseStream Component](https://reactphp.org/promise-stream/){:target="_blank"} is a link between promise-land and stream-land*

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/promise-stream.png" alt="promise-stream" class="">
</p>

## From Stream To Promise

One of the patterns that are used to deal with streams is *spooling*: we need the entire resource data available before we start processing it. One approach is to collect each chunk of data received from the stream:

{% highlight php %}
<?php

use React\Stream\ReadableResourceStream;

$loop = React\EventLoop\Factory::create();
$spool = "";

$stream = new ReadableResourceStream(fopen('file.txt', 'r'), $loop);

$stream->on('data', function($data) use (&$spool) {
    $spool .= $data;
});

$stream->on('end', function() use (&$spool) {
    echo $spool . PHP_EOL;
    echo 'Done' . PHP_EOL;
});

$loop->run();
{% endhighlight %}

But, imagine that we have some client code that wants to process some data from a file. It doesn't care about the streams, it only needs to receive the entire data from the file. With this approach, this code should be called inside the callback for the `end` event of the stream. So, the client code should now about streams, events, and callbacks. But sometimes it's impossible. Consider this pretty simple example:

{% highlight php %}
<?php

class Processor {
    public function process($data)
    {
        echo $data . PHP_EOL;
        echo 'Done' . PHP_EOL;
    }
}

class Provider {
    public function get($path, LoopInterface $loop)
    {
        $spool = "";
        $stream = new ReadableResourceStream(fopen($path, 'r'), $loop);

        $stream->on('data', function($data) use (&$spool) {
            $spool .= $data;
        });

        $stream->on('end', function() use (&$spool) {
            // ???
        });
    }
}

$loop = \React\EventLoop\Factory::create();

$processor = new Processor();

// how to get data from the stream
// and pass it to the processor?
(new Provider())->get('file.txt', $loop);

$loop->run();
{% endhighlight %}

We have two separate classes:

 - `Processor` for processing data from the file
 - `Provider` for collecting this data from the file

Once the data is completely collected we need to pass it to the `Processor`, but how? How can we encapsulate this *stream logic* and provide the client code only with the data from the stream? The answer is - promises.

We can hide spooling logic behind the promise. When all data is being read from the file we can resolve the promise with this data.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/stream-to-promise.png" alt="stream-to-promise" class="">
</p>

### buffer()

`\React\Promise\Stream\buffer()` function creates a promise which resolves with the stream data. All data chunks from the stream will be concatenated and once the stream closes the promise resolves. Now, let's rewrite the previous example:

{% highlight php %}
<?php

class Processor {
    public function process(PromiseInterface $promise)
    {
        $promise->then(function($data) {
            echo $data . PHP_EOL;
            echo 'Done' . PHP_EOL;
        });
    }
}

class Provider {
    /**
     * @param string $path
     * @param LoopInterface $loop
     * @return PromiseInterface
     */
    public function get($path, LoopInterface $loop)
    {
        $stream = new ReadableResourceStream(fopen($path, 'r'), $loop);
        return \React\Promise\Stream\buffer($stream);
    }
}

$loop = \React\EventLoop\Factory::create();

$processor = new Processor();
$provider = new Provider();

$processor->process($provider->get('file.txt', $loop));

$loop->run();
{% endhighlight %}

Now, we can easily pass the data from the stream via promises. The `Processor` knows nothing about the source of the data. Also, the `Provider` only cares about collecting the entire resource data. The promise becomes a clue between these two classes. Promises also allow to create chains of callbacks (like we did with `pipe()` method for streams), when every next promise receives the data from the previous one:

{% highlight php %}
<?php

class Processor {
    /**
     * @param PromiseInterface $promise
     * @return PromiseInterface
     */
    public function process(PromiseInterface $promise)
    {
        return $promise
          ->then('trim')
          ->then(function($string) {
            return str_replace(' ', '-', $string);
          })
          ->then('strtolower');
    }
}

// ...

$loop = \React\EventLoop\Factory::create();

$processor = new Processor();
$provider = new Provider();

$processor
    ->process($provider->get('file.txt', $loop))
    ->then(function($data) {
        echo $data . PHP_EOL;
        echo 'Done' . PHP_EOL;
    });

$loop->run();
{% endhighlight %}

In the example above, we collect the data from the stream, then return a promise that resolves with this data. Then this data is trimmed, then we replace all spaces with dashes and lowercase all characters. And at last, we output this data.

### all()

If you need to deal with chunks of data and not with a concatenated content you can use `\React\Promise\Stream\all();` function. It accepts an instance of the stream (readable or writable) and a name of the event. When a specified event occurs the function collects its data. The promise resolves with an array of whatever all events emitted or `null` if the events didn't pass any data.

By default, it collects data chunks from the `data` event, but you can manually specify the name of the event you are interested in as the second argument: `all($stream, 'event-name')`. 

In case of chunks, the previous example looks the following:

{% highlight php %}
<?php

class Processor {
    /**
     * @param PromiseInterface $promise
     * @return PromiseInterface
     */
    public function process(PromiseInterface $promise)
    {
        return $promise->then(function(array $chunks) {
            echo 'Total chunks: ' . count($chunks) . PHP_EOL;

            foreach ($chunks as $index => $chunk) {
                echo 'Chunk ' . ($index + 1) . ': ' . $chunk . PHP_EOL;
            }
        });
    }
}

class Provider {
    /**
     * @param string $path
     * @param LoopInterface $loop
     * @return PromiseInterface
     */
    public function get($path, LoopInterface $loop)
    {
        $stream = new ReadableResourceStream(fopen($path, 'r'), $loop);
        return \React\Promise\Stream\all($stream);
    }
}

$loop = \React\EventLoop\Factory::create();

$processor = new Processor();
$provider = new Provider();

$processor
    ->process($provider->get('file.txt', $loop))
    ->then(function() {
        echo 'Done' . PHP_EOL;
    });

$loop->run();
{% endhighlight %}

The `Provider` returns a stream wrapped in a promise with `all()` function. Then in `Processor`, this promise resolves with an array of data chunks from the stream. 

#### Resolving and rejection
1. The promise will resolve with an array once the stream closes.
2. If the stream is already closed the promise resolves with an empty array.
3. If the stream emits an error the promise rejects.

### first()

Let's update our `Provider`, so it can return both data and error from the stream. To return error we can use 
`\React\Promise\Stream\first()` function. It creates a Promise which resolves once the given event triggers for the first time. Once `error` event is emitted the stream closes, so there will be no more `error` events. That's why `first()` function is exactly what we need in this way: 

{% highlight php %}
<?php

class Provider {
    /**
     * @var ReadableResourceStream
     */
    protected $stream;

    /**
     * @param string $path
     * @param LoopInterface $loop
     */
    public function __construct($path, LoopInterface $loop)
    {
        $this->stream = new ReadableResourceStream(fopen($path, 'r'), $loop);
    }

    /**
     * @return PromiseInterface
     */
    public function getData()
    {
        return \React\Promise\Stream\buffer($this->stream);
    }

    /**
     * @return PromiseInterface
     */
    public function getError()
    {
        return \React\Promise\Stream\first($this->stream, 'error');
    }
}
{% endhighlight %}

Via constructor, it accepts a path to a file and an instance of the event loop. Then it has two simple methods that both return promises. `getData()` returns a promise which resolves with contents from the stream. `getError()` resolves with an exception when an error occurs. Then we can simply pass the promise from `getError()` method to a logger:

{% highlight php %}
<?php

class Logger {
    /**
     * @param PromiseInterface $promise
     * @return PromiseInterface
     */
    public function log(PromiseInterface $promise)
    {
        return $promise->then(function(Exception $error) {
            echo 'Error ' . $error->getMessage() . PHP_EOL;
        });
    }
}

$loop = \React\EventLoop\Factory::create();

$logger = new Logger();
$provider = new Provider('file.txt', $loop);

$logger->log($provider->getError());

$loop->run();
{% endhighlight %}

`first()` function returns a promise which rejects if:

 - the stream emits an error - unless you're waiting for the *error* event, in which case it will resolve
 - the stream closes - unless you're waiting for the *close* event, in which case it will resolve.
 - the stream is already closed.
 - it is canceled.

## From Promise To Stream

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/promise-to-stream.png" alt="promise-to-stream" class="">
</p>

### unwrapReadable()

When a promise resolves with a stream we can extract it or *unwrap* this stream. For readable streams we can use `\React\Promise\Stream\unwrapReadable()` function. This function returns an instance of a stream that implements `ReadableStreamInterface`. Consider this stream as a proxy for the future promise resolution:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();
$stream = \React\Promise\Stream\unwrapReadable($deferred->promise());

$stream->on('data', function($data) {
    echo 'Received: ' . $data . PHP_EOL;
});

$loop = \React\EventLoop\Factory::create();
$readable = new \React\Stream\ReadableResourceStream(fopen('php://stdin', 'r'), $loop);

$deferred->resolve($readable);
$loop->run();
{% endhighlight %}

In the example above when a readable stream receives data from the standard input (console), this data is piped to the resulting stream:
<div class="row">
    <p class="text-center image col-sm-9">
        <img src="/assets/images/posts/reactphp/unwrap-readable.png" alt="unwrap-readable" class="">
    </p>
</div>

If the promise is either rejected or fulfilled with anything but an instance of `ReadableStreamInterface`, then the resulting stream will emit an `error` event and close:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();
$stream = \React\Promise\Stream\unwrapReadable($deferred->promise());

$stream->on('data', function($data) {
    echo 'Received: ' . $data . PHP_EOL;
});

$stream->on('error', function(Exception $error) {
    echo 'Error: ' . $error->getMessage() . PHP_EOL;
});

$deferred->resolve('Hello!');
{% endhighlight %}

<div class="row">
    <p class="text-center image col-sm-9">
        <img src="/assets/images/posts/reactphp/unwrap-readable-error.png" alt="unwrap-readable-error" class="">
    </p>
</div>

To receive the `error` event the promise **should** be pending when calling `unwrapReadable()` function. If the promise is already settled and does not resolve with an instance of `ReadableStreamInterface`, no events will be emitted. For example, this code doesn't emit `error` event:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();
$deferred->resolve('Hello!');
$stream = \React\Promise\Stream\unwrapReadable($deferred->promise());

$stream->on('data', function($data) {
    echo 'Received: ' . $data . PHP_EOL;
});

$stream->on('error', function(Exception $error) {
    echo 'Error: ' . $error->getMessage() . PHP_EOL;
});
{% endhighlight %}

You can `close()` the resulting stream at any time. As a result, this will `cancel()` the pending promise and also `close()` the underlying stream.

### unwrapWritable()

This function can be used to unwrap a promise which resolves with a `WritableStreamInterface`. It returns an instance of the `WritableStreamInterface` which acts as a proxy for the future promise resolution. Any data you wrote to this resulting stream will be piped to an internal writable stream:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();
$stream = \React\Promise\Stream\unwrapWritable($deferred->promise());

$loop = \React\EventLoop\Factory::create();
$writable = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$deferred->resolve($writable);

$stream->write('Hello world!');

$loop->run();
{% endhighlight %}

In this simple example, the promise resolves with an instance of a writable stream, which writes data to the console. Then we create a proxy for this stream. When we `write` data to the proxy it is also written to the unwrapped stream:

<div class="row">
    <p class="text-center image col-sm-9">
        <img src="/assets/images/posts/reactphp/unwrap-writable.png" alt="unwrap-writable" class="">
    </p>
</div>

`unwrapWritable()` follows the same rules as `unwrapReadable()` does:

 - If the promise is either rejected or fulfilled with anything but an instance of `WritableStreamInterface`, then the resulting stream will emit an `error` event and close.
 - To receive the `error` event the promise **should** be pending when calling `unwrapWritable()` function.
 - You can `close()` the resulting stream at any time. And this will `cancel()` the pending promise and also `close()` the underlying stream.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/promise-stream){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
