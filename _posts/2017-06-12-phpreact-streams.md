---
title: "Event-Driven PHP with ReactPHP: Streams"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Event-Driven PHP with ReactPHP: Streams"
---

# Streams

[ReactPHP Stream Component](https://github.com/reactphp/stream)

In PHP streams represent a special resource type. The description given in php.net [documentation](http://php.net/manual/en/intro.stream.php):

> *Streams are the way of generalizing file, network, data compression, and other operations which share a common set of functions and uses. In its simplest definition, a stream is a resource object which exhibits streamable behavior. That is, it can be read from or written to in a linear fashion, and may be able to fseek() to arbitrary locations within the stream.*

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streams.jpg" alt="cgn-edit" class="">
</p>

Every stream at a low level is simply an `EventEmitter`, which implements some special methods. Depending on these methods the stream can be *Readable*, *Writable* or *Duplex* (both readable and writable). Readable streams allow to read the data from a source, while writable can be used to write some data to a destination. Duplex streams allow to read and to write data like TCP/IP connection does. 

Accordingly, Stream Component defines the following three interfaces:

- `ReadableStreamInterface`
- `WritableStreamInterface`
- `DuplexStreamInterface`

Every stream implementation implements `EventEmitterInterface` which allows to listen to certain events. There are some common events for all types of streams, and some specific events for every certain type.

## Readable Stream

Read-only streams are implemented by `ReadableStreamInterface`, which is also a readable side of duplex streams.

A readable stream is used only to read data from the source in a continuous fashion (for example, you cannot write to a downloading file). The source can be anything: a file on disk, a buffer in memory or even another stream. Use `ReadableResourceStream` class to create a readable stream:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$stream = new \React\Stream\ReadableResourceStream(fopen('index.php', 'r'), $loop);
{% endhighlight %}

To create an instance `ReadableResourceStream` you need to pass to the constructor a valid resource opened in a *read mode* and an object, which implements `LoopInterface`.

Readable streams are a great solution when you have to deal with large files and don't want to load them into the memory. For example, you have large log files and need programmatically gather some statistics from them. So, instead of this:

{% highlight php %}
<?php

$content = file_get_content($logFile);
// process the whole file at once ...

{% endhighlight %}

You can use something like this:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$stream = new \React\Stream\ReadableResourceStream(fopen($logFile, 'r'), $loop);

$stream->on('data', function($data){
    // process data *line by line*
});

$stream->on('end', function(){
    echo "finished\n";
});

$loop->run();
{% endhighlight %}

The code with ReactPHP looks too complex when compared with a one-line snippet with `file_get_contents`, but it's worth it. The problem with `file_get_contents` is that we cannot start processing the received data until we read the whole file. With this approach, we can have problems with really large files or high traffic.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/synchronous_streams.jpg" alt="cgn-edit" class="">
</p>

With streams, there is no need to keep the whole file in memory and we can process the data as soon it's been read. Another use case can be *live data* streams, whose volume is not predetermined.

### Events

All available stream events have intuitive names. For example, every time a readable stream receives *data* from its source it fires `data` event. If you want to process data from the stream you should listen to this event. When there is no more data available (the source stream has successfully reached the end) the `end` event is fired:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$stream = new \React\Stream\ReadableResourceStream(fopen($logFile, 'r'), $loop);

$stream->on('data', function($data){
    // process received data 
});

$stream->on('end', function(){
    echo "Finished\n";
});

$loop->run();
{% endhighlight %}

**Notice** that we have used `fopen` functon which creates a file handler, but there is no need to manually close the handler with `fclose`. Behind the scenes, when the stream will *end* it will automatically close the handler. Here is the source code of `ReadableResourceStream`:

{% highlight php %}
<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;

final class ReadableResourceStream extends EventEmitter implements ReadableStreamInterface
{
    /**
     * @var resource
     */
    private $stream;

    // ... 

    public function handleClose()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
{% endhighlight %}

The `close` event looks very similar to the `end` event, it will be emitted once the stream closes. The difference is that the `end` event always indicates a successful end, while `close` means only a termination of the stream:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$stream = new \React\Stream\ReadableResourceStream(fopen($logFile, 'r'), $loop);

$stream->on('data', function($data){
    // process received data 
});

$stream->on('close', function(){
    echo "The stream was closed\n";
});

$loop->run();
{% endhighlight %}

We can use `isReadable()` method to check if a stream is in a readable state (not closed):

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$stream = new \React\Stream\ReadableResourceStream(fopen($logFile, 'r'), $loop);

echo "Open\n";
var_dump($stream->isReadable());

$stream->on('data', function($data){
    // process received data 
});

$stream->on('end', function() use ($stream){
    echo "End\n";
    var_dump($stream->isReadable());
});

$stream->on('close', function() use ($stream){
    echo "Close\n";
    var_dump($stream->isReadable());
});

$loop->run();
{% endhighlight %}

The output will be the following. On the `end` event the stream is still *readable*, but on the `close` event it is in a non-readable mode:

{% highlight bash %}
$ php index.php
Open
bool(true)
End
bool(true)
Close
bool(false)
{% endhighlight %}

### Reading Control

Reading from a stream can be *paused* and later *continued* with `pause()` and `resume()` methods. When a stream is *paused* it stops emitting `data` events. Under the hood `pause()` method simply detaches the stream from the event loop. Here is an example of how to read from a file one byte per second:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$stream = new \React\Stream\ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);

$stream->on('data', function($data) use ($stream, $loop){
    echo $data, "\n";
    $stream->pause();

    $loop->addTimer(1, function() use ($stream) {
        $stream->resume();
    });
});

$loop->run();
{% endhighlight %}

The third argument of the `ReadableResourceStream` constructor is `$readChunkSize`. This parameter allows to control the maximum buffer size in bytes to read from the stream at a time.

<p class="">
    <img src="/assets/images/posts/reactphp/stream_pause_resume.gif" alt="cgn-edit" class="">
</p>

## Writable Stream

Writable streams allow only to write data to the destination (for example, you cannot read from `STDOUT`), they also represent a writable side of a duplex stream. Writable streams can be useful for logging some events or for taking user input data. These streams ensure that data chunks arrive in the correct order. 

Writable streams are represented by `WritableResourceStream` class which implements `WritableStreamInterface`. To create a writable stream you need a resource opened in a *writable mode* and an instance of the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}):

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$stream = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);
{% endhighlight %}

### Writing Control

The process of writing data is very simple, `WritableResourceStream` class has two methods:

 - `write($data)` to write some data into the stream
 - `end($data = null)` to successfully end the stream, you can optionally send some final data before ending.

This example outputs the content of the file to the console using a writable stream instead of `echo`:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$readable = new \React\Stream\ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
$output = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$readable->on('data', function($data) use ($output){
    $output->write($data);    
});

$readable->on('end', function() use ($output) {
    $output->end();
});

$loop->run();
{% endhighlight %}

**Notice** that things happen in an asynchronous way. That means that data is not actually *written* when you call `write($data)` method. It is placed in a buffer, and a listener is added to the event loop, so on the next tick, the data will be written. For example, when you don't run the loop nothing will be written:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$writable = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop, 1);

$writable->write('Hello world');
{% endhighlight %}

A writable stream has its own analog of the `isReadable()` method. Until the stream is not *ended* `isWritable()` returns `true`:

{% highlight php %}
<?php
$loop = React\EventLoop\Factory::create();

$readable = new \React\Stream\ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
$output = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

var_dump($output->isWritable());

$readable->on('data', function($data) use ($output){
    $output->write($data);
});

$readable->on('end', function() use ($output) {
    $output->end();
});

$loop->run();
var_dump($output->isWritable());
{% endhighlight %}

The code above outputs the following:

{% highlight bash %}
$ php index.php
bool(true)
Lorem ipsum
bool(false)
{% endhighlight %}

If we don't `end()` the stream it will stay *writable*. After stream is *ended* any further `write()` or `end()` calls have no effect:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$readable = new \React\Stream\ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
$output = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$readable->on('data', function($data) use ($output){
    $output->write($data);
});

$readable->on('end', function() use ($output) {
    $output->end();
    $output->write('Hello!');
});

$loop->run();
{% endhighlight %}

The last `write('Hello!')` call will provide no output to the console since the stream is already *ended*. 

Method `close()` can be used to force stream closing. Unlike the `end()` method which takes care of the existing buffers `close()` discards any buffer contents and *closes* the stream. Under the hood `end()` method calls `close()` internally:

{% highlight php %}
<?php

namespace React\Stream;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

final class WritableResourceStream extends EventEmitter implements WritableStreamInterface
{
    // ...

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->writable = false;

        // close immediately if buffer is already empty
        // otherwise wait for buffer to flush first
        if ($this->data === '') {
            $this->close();
        }
    }
}
{% endhighlight %}

### Events
Imagine that you are working with two streams with very different bandwidths. For example, you are uploading a local file to a slow server. The fast (local file) stream will emit data faster than the slow stram (socket on a web server) can consume it. In this situation, we have to keep the data in memory until the slow stream is ready to process it. For large files, it can become a problem. To avoid this `write($data)` method returns `false` when the buffer is full so we can stop writing. Then later the stream will emit `drain` event which indicates that the buffer is now ready to accept more data and we can continue writing.

To demonstrate this we can use the third parameter of the `WritableResourceStream` constructor. `$writeBufferSoftLimit` sets the maximum buffer size in bytes:
{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$writable = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop, 1);

var_dump($writable->write("Hello world\n"));

$writable->on('drain', function(){
    echo "The stream is drained\n";
});

$loop->run();
{% endhighlight %}

This code provides the following output:
{% highlight bash %}
$ php index.php
bool(false)
Hello world
The stream is drained
{% endhighlight %}

Although a writable stream has `end()` method there is no `end` event. You can listen only to `close` event:

{% highlight php %}
<?php 

$loop = \React\EventLoop\Factory::create();
$writable = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$writable->on('end', function(){
    echo "End\n"; // <-- this code will never be executed
});

$writable->on('close', function(){
    echo "Close\n"; 
});

$loop->addTimer(1, function() use ($writable) {
    $writable->end();
});

$loop->run();
{% endhighlight %}

Here is the output of the code above:

<p class="">
    <img src="/assets/images/posts/reactphp/wrtiable_has_no_end_event.gif" alt="cgn-edit" class="">
</p>

## Piping

We can chain events with the `pipe(WritableStreamInterface $dest, array $options = array())` method of the `ReadableResourceStream`. This method *connects* a readable stream to a writable one by piping all the data from the readable source into the given writable destination. We can rewrite an example with writing the output from a file to the console using `pipe()` method like this:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$readable = new React\Stream\ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
$output = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$readable->pipe($output);

$loop->run();
{% endhighlight %}

Only one line of code `$readable->pipe($output);` instead of listening to different events and manually processing the data flow:

{% highlight php %}
<?php

$readable->on('data', function($data) use ($output){
    $output->write($data);
});
{% endhighlight %}

Behind the scenes `pipe()` method subscribes all the required listeners and calls the appropriate methods to provide a continuous flow of data between the streams so that the destination stream isn’t overwhelmed by the readable one. This method also returns an instance of the writable stream, so we can build a chain of piped duplex (both readable and writable) streams:

{% highlight php %}
<?php

$source->pipe($decodeGzip)->pipe($dest);
{% endhighlight %}

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/pipe.jpg" alt="cgn-edit" class="">
</p>

By default `pipe()` will call `end()` method on the destination stream when the source stream emits `end` event. To disable this behavior use the second `$options` argument and set `end` to `false`:

{% highlight php %}
<?php

$source->pipe($dest, ['end' => false]);
{% endhighlight %}

This behavior only applies to the `end` event. You should handle `error` and manually emitted `close` events yourself.

## Duplex Stream

> *You don't get what you write. It is sent to another source.*

A duplex stream is one which is both readable and writable. It also may be a combination of two independent streams embedded in one. A concrete example of a duplex stream is a network socket or a file opened in a read-and-write mode:

{% highlight php %}
<?php 

$loop = \React\EventLoop\Factory::create();

$conn = stream_socket_client('tcp://google.com:80');
$stream = new \React\Stream\DuplexResourceStream($conn, $loop);

$stream->write('hello!');
$stream->end();

$loop->run();
{% endhighlight %}

Duplex streams are built on top of both `ReadableStreamInterface` and `WritableStreamInterface`, so they provide methods and emit events that are available in both interfaces. You can `resume()`, `pause()` and emit the `data` event and at the same time `write()` and emit `drain` event.

## Through Stream

> *You write something, it is transformed, then you read something.*

Class `ThroughStream` can be used as a *transfer* stream. It implements `DuplexStreamInterface` and simply passes any written data through to its readable end. It can be used to process data through the pipes. For example, we can use `ThroughStream` to *uppercase* the from a file and then output it to the console:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$readable = new \React\Stream\ReadableResourceStream(fopen('file.txt', 'r+'), $loop, 1);
$output = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$through = new \React\Stream\ThroughStream('strtoupper');
$readable->pipe($through)->pipe($output);

$loop->run();
{% endhighlight %}

You may consider `ThroughStream` as a readable/writable filter that transforms input and produces output.

## Composite Stream

> *Combine together readable and writable streams into a duplex one.*

The `CompositeStream` implements the `DuplexStreamInterface` and can be used to create a single duplex stream from two individual readable and writable streams:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();

$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);
$stdout = new \React\Stream\WritableResourceStream(STDOUT, $loop);
$composite = new \React\Stream\CompositeStream($stdin, $stdout);

$composite->on('data', function ($chunk) use ($composite) {
    $composite->write('You said: ' . $chunk);
});

$loop->run();
{% endhighlight %}

This snippet reads the data from the `STDIN`, prepends it with a string `You said: ` and then outputs it to the console:

<p class="">
    <img src="/assets/images/posts/reactphp/composite_stream.gif" alt="cgn-edit" class="">
</p>

## Error Handling

When an error occurs while reading or writing the `error` event will be emitted:

{% highlight php %}
<?php

$stream->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
{% endhighlight %}

This event receives an instance of the `Exception` for the occured error. For `DuplexStreamInterface` you should take care for both sides of the stream because an error may occur while either reading or writing the stream.

## Conclusion

[ReactPHP Streams](https://github.com/reactphp/stream) are very powerful tools when you need to create a stream instance from a stream resource. At the same time, they are a very low-level abstraction and you have to manage all the events and data flow by yourself. If you are writing low-level components streams may be a good choice for you. If not consider some higher-level components:

- [react/socket](https://github.com/reactphp/socket) if you want to accept incoming or establish outgoing plaintext TCP/IP or secure TLS socket connection streams.
- [react/http](https://github.com/reactphp/http) if you want to receive an incoming HTTP request body streams.
- [react/child-process](https://github.com/reactphp/child-process) if you want to communicate with child processes via process pipes such as STDIN, STDOUT, STDERR etc.
- [react/filesystem](https://github.com/reactphp/filesystem) if you want to read from/write to the filesystem.

<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/streams).

<strong>Other ReactPHP articles:</strong>

- [Event loop and timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Promises]({% post_url 2017-06-16-phpreact-promises %})
- [Chat on sockets: server]({% post_url 2017-06-22-reactphp-chat-server %}) and  [client]({% post_url 2017-06-24-reactphp-chat-client %})
- [UDP chat]({% post_url 2017-07-05-reactphp-udp %})
- [Video streaming server]({% post_url 2017-07-17-reatcphp-http-server %})
- [Parallel downloads with async http requests]({% post_url 2017-07-26-reactphp-http-client %})
- [Managing Child Processes]({% post_url 2017-08-07-reactphp-child-process %})
- [Cancelling Promises With Timers]({% post_url 2017-08-22-reactphp-promise-timers %})
