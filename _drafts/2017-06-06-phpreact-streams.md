---
title: "Event-Driven PHP with ReactPHP: Streams"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
---

# Streams

[ReactPHP Stream Component](https://github.com/reactphp/stream)

In PHP streams represent a special resource type. Description from php.net [documentation](http://php.net/manual/en/intro.stream.php):

> *Streams are the way of generalizing file, network, data compression, and other operations which share a common set of functions and uses. In its simplest definition, a stream is a resource object which exhibits streamable behavior. That is, it can be read from or written to in a linear fashion, and may be able to fseek() to an arbitrary locations within the stream.*

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/streams.jpg" alt="cgn-edit" class="">
</p>

Every stream at a low level is simply an `EventEmitter`, which implements some special methods. Depending on these methods the stream can be *Readable*, *Writable* or *Duplex* (both readable and writable). Readable streams allow to read data from a source, while writable can be used to write some data to a destination. Duplex streams allow to read and to write data, like TCP/IP connection does. 

Accordingly, Stream Component defines the following three interfaces:

- [ReadableStreamInterface]()
- [WritableStreamInterface]()
- [DuplexStreamInterface]()

Every stream implementation implements `EventEmitterInterface` which allows to listen to certain events. There are some common events for all types of streams, and some specific events for every certain type.

## Readable Stream

Read-only streams are implemented by `ReadableStreamInterface`, which is also a readable side of duplex streams.

A readable stream is used only to read data from the source (for example, you cannot write to a downloading file) in a continuous fashion. The source can be anything: a file on disk, a buffer in memory or even another stream. To create a readable stream:

{% highlight php %}
<?php

use React\Stream\ReadableResourceStream;

$loop = React\EventLoop\Factory::create();

$stream = new ReadableResourceStream(fopen('index.php', 'r'), $loop);
{% endhighlight %}

To create an instance `ReadableResourceStream` you need to pass to the constructor a valid resource opened in *read mode* and an object, which implements `LoopInterface`.

Readable streams are a great solution when you have to deal with large files and don't want to load them into the memory. For example you have large log files and need programmatically gather the some statistics from it. So, instead of this:

{% highlight php %}
<?php

$content = file_get_content($logFile);
// process the whole file at once ...

{% endhighlight %}

We can use something like this:

{% highlight php %}
<?php

use React\Stream\ReadableResourceStream;

$loop = React\EventLoop\Factory::create();
$stream = new ReadableResourceStream(fopen($logFile, 'r'), $loop);

$stream->on('data', function($data){
    // process data *line by line*
});

$stream->on('end', function(){
    echo "finished\n";
});

$loop->run();
{% endhighlight %}

The code with ReactPHP looks too complex when compared with a one-line snippet with `file_get_contents`, but it's worth it. The problem with `file_get_contents` is that we cannot start processing the received data, until we read the whole file. With this approach we can have problems with really large files or high traffic.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/synchronous_streams.jpg" alt="cgn-edit" class="">
</p>

With streams there is no need to keep the whole file in memory and we can process the data as soon its been read. Another use case can be *live data* streams, whose volume is not predetermined.

### Events

All available stream events have intuitive names. For example, every time a readable stream recieves *data* from its source it fires `data` event. If you want to receive data from the stream you should listen to this event. When there is no more data available (the source stream has successfully reached the end) the `end` event is fired:

{% highlight php %}
<?php

use React\Stream\ReadableResourceStream;

$loop = React\EventLoop\Factory::create();
$stream = new ReadableResourceStream(fopen($logFile, 'r'), $loop);

$stream->on('data', function($data){
    // process received data 
});

$stream->on('end', function(){
    echo "Finished\n";
});

$loop->run();
{% endhighlight %}

**Note**. We have used `fopen` functon which creates a file handler, but there is no need to manually close the handler with `fclose`. Behind the scenes, when the stream will *end* it will automatically close the handler:

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

`close` event looks very similar to `end` event, it will be emitted once the stream closes. The difference is that `end` event always indicates a successful end, while `close` means only a termination of the stream:

{% highlight php %}
<?php

use React\Stream\ReadableResourceStream;

$loop = React\EventLoop\Factory::create();
$stream = new ReadableResourceStream(fopen($logFile, 'r'), $loop);

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

use React\Stream\ReadableResourceStream;

$loop = React\EventLoop\Factory::create();
$stream = new ReadableResourceStream(fopen($logFile, 'r'), $loop);

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

The output will be the following. On `end` event the stream is still *readable*, but on `close` it is in a non-readable mode:

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

Reading from a stream can be *paused* and later continued with `pause()` and `resume()` methods. When stream is *paused* it stoppes emmiting `data` events. Under the hood `pause()` method simply detaches the stream from the event loop. Here is an example of how to read from a file one byte per second:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$stream = new ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);

$stream->on('data', function($data) use ($stream, $loop){
    echo $data, "\n";
    $stream->pause();

    $loop->addTimer(1, function() use ($stream) {
        $stream->resume();
    });
});

$loop->run();
{% endhighlight %}

The third argument of the `ReadableResourceStream` constructor is `$readChunkSize`. This parameter allows to control the maximum buffer size in bytes to read at once from the stream.

<p class="">
    <img src="/assets/images/posts/reactphp/stream_pause_resume.gif" alt="cgn-edit" class="">
</p>

## Writable Stream

Writable streams allows only to write data to the destination (for example, you cannot read from `STDOUT`), they also represent a writable side of duplex stream. Writable streams can be useful logging some events or taking user input data. These streams ensures that data chunks arrive in the correct order. To create a writable stream you need a resource opened in writable mode and an instance of the event loop:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$stream = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);
{% endhighlight %}

### Writing Control

The process of writing is very simple, you have two methods:

 - `write($data)` to write some data into the stream
 - `end($data = null)` to successfully end the stream, you can optionally send some final data before ending.

This example outputs the content of the file to the console using a writable stream instead of `echo`:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$readable = new ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
$output = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $loop);

$readable->on('data', function($data) use ($output){
    $output->write($data);    
});

$readable->on('end', function() use ($output) {
    $output->end();
});

$loop->run();
{% endhighlight %}

Writable stream has its own analog of the `isReadable()` method. Until the stream is not *ended* `isWritable()` returns `true`:

{% highlight php %}
<?php
$loop = React\EventLoop\Factory::create();

$readable = new ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
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

If we don't `end` the stream it will stay `writable`. After stream is *ended* any further `write()` or `end()` calls have no effect:

{% highlight php %}
<?php

$readable = new ReadableResourceStream(fopen('file.txt', 'r'), $loop, 1);
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

Method `close()` can be used to force stream closing. Unlike the `end()` method which takes cares of the existing buffers `close()` discards any buffer contents and *closes* the stream. Behind the hood `end()` method calls `close()` internally:

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


## EventEmitter

Every stream class extends `EventEmitter` class, which consists of one trait `EventEmitterTrait`:

{% highlight php %}
<?php

namespace Evenement;

class EventEmitter implements EventEmitterInterface
{
    use EventEmitterTrait;
}
{% endhighlight %}

`EventEmitterTrait` implements basic methods to fire events and subscribe to them:

- `on($event, callable $listener)` subscribes a listener to the specified event. When event occurs a listener will be triggered. Adds listener to the end of the listeners array, there are no checks if this listener already has been added.
- `once($event, callable $listener)` adds a one-time listener to the event. The listener will be invoked only once the next time the event is fired, after that it is removed. It is a wrapper over the `on` method. It wraps a specified listener into the closure, which when is invoked it at first removes the listener from the subscribers and then invokes this listener.
- `emit($event, array $arguments = [])` fires an event. All listeners that are subscribed to this event will be invoked. `$arguments` array will be passed as an argument to every listener.
- `listeners($event)` returns an array of listeners for the specified event.
- `removeListener($event, callable $listener)` removes a listener from the array of listeners for the specified event.
- `removeAllListeners($event = null)` removes listeners of the specified event. If `$event` is `null` removes all listeners.
