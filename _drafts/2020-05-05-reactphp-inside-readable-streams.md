---

title: "ReactPHP Internals: Readable Stream"
layout: post
description: "Dig into ReactPHP readable stream."
tags: [PHP, ReactPHP, Stream]
image: "/assets/images/posts/reactphp-event-loop-timer/logo.jpg" 

---


Streams are closely connected with the event loop. They even require it in the constructor:

{% highlight bash %}
<?php

use React\EventLoop\Factory;
use React\Stream\ReadableResourceStream;

$loop = Factory::create();
$stream = new ReadableResourceStream($resource, $loop);
{% endhighlight %}

Why? OK, let's see what happens in the constructor with the loop:

{% highlight php %}
<?php

final class ReadableResourceStream extends EventEmitter implements ReadableStreamInterface
{
    private $stream;
    private $loop;
    private $bufferSize;
    private $closed = false;
    private $listening = false;

    public function __construct($stream, LoopInterface $loop, $readChunkSize = null)
    {
        // stream validation

        $this->stream = $stream;
        $this->loop = $loop;
        $this->bufferSize = ($readChunkSize === null) ? 65536 : (int)$readChunkSize;

        $this->resume();
    }
{% endhighlight %}

In the constructor we have a resource validation: if it is a readable resource that can be used in a non-blocking way. Then comes properties assignment and then method `resume()`. This method is used to *resume* reading incoming data events:

{% highlight php %}
<?php

public function resume()
{
    if (!$this->listening && !$this->closed) {
        $this->loop->addReadStream($this->stream, array($this, 'handleData'));
        $this->listening = true;
    }
}
{% endhighlight %}

The key word here is **events**. The as we know the event loop is responsible for handling incoming events. Here we check that the stream is not closed and is not listening (reading) for new events. The readable stream can not be listening for new events when it is *paused*. So, if the stream is not listening we call the event loop:

{% highlight php %}
<?php

$this->loop->addReadStream($this->stream, array($this, 'handleData'));
{% endhighlight %}

What happens inside `addReadStream()` method? Open `StreamSelectLoop` and let's see:

{% highlight php %}
<?php

final class StreamSelectLoop implements LoopInterface
{
    public function addReadStream($stream, $listener)
    {
        $key = (int) $stream;

        if (!isset($this->readStreams[$key])) {
            $this->readStreams[$key] = $stream;
            $this->readListeners[$key] = $listener;
        }
    }

}
{% endhighlight %}

We see that the loop has to internal arrays: one for readable streams and another - for corresponding listeners. The stream is actually a resource opened in a readable mode, while listener is any callable. What happens next? OK, we added the stream and its listener to the stream. But when the listener will be called? 

For example, we have the following code:

{% highlight bash %}
<?php

use React\EventLoop\Factory;
use React\Stream\ReadableResourceStream;

$loop = Factory::create();
$resource = fopen('test.txt', 'rb+');
$stream = new ReadableResourceStream($resource, $loop);
$stream->on(
    'data',
    function ($data) {
        echo $data;
    }
);
{% endhighlight %}

We open a file in a readable mode and use it as a resource for a stream. Then we start listening to `data` event and printing the contents of the file. But if we run this code nothing will happen. I mean nothing will be printed. Because we don't lunch the loop. If we add `$loop->run()` the contents of the file will be printed:

{% highlight php %}
<?php

$loop = Factory::create();
$resource = fopen('test.txt', 'rb+');
$stream = new ReadableResourceStream($resource, $loop);
$stream->on(
    'data',
    function ($data) {
        echo $data;
    }
);

$loop->run();
{% endhighlight %}

It means that the loop is responsible for handling incoming events and calling the listener in the stream. So, let's see how it works under the hood. Open `StreamSelectLoop` implementation of the loop and go to method `run()`:

{% highlight php %}
<?php

final class StreamSelectLoop implements LoopInterface
{
    public function run()
    {
        $this->running = true;

        while ($this->running) {
            // handling timers and ticks

            $this->waitForStreamActivity($timeout);
        }
    }
}
{% endhighlight %}

The last call inside `while` loop is `$this->waitForStreamActivity($timeout)`. The title of the method sounds like exactly what we need. Let's see.
