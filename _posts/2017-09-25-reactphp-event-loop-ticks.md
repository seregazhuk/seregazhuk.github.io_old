---
title: "Understanding ReactPHP Event Loop Ticks"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Understanding event loop ticks in ReactPHP."
image: "/assets/images/posts/reactphp/ticks.png"
---

## What Is Tick?

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/ticks.png" alt="ticks" class="">
</p>

*Tick* is one loop iteration where every callback in the queues has been executed synchronously and in order. ReactPHP event loop implementation has a method to schedule a callback to be invoked on a future iteration of the event loop:

- `public function futureTick(callable $listener);`

When being executed a callback receives an instance of the event loop as an argument. 

ReactPHP provides several implementations of the `LoopInterface` according to extensions available in your system. At the time of writing this article, there are four available implementations, but all of them share the same future ticks logic. All four implementations of the event loop use a `React\EventLoop\Tick\FutureTickQueue` queue. When an event loop is being created it initialized this queue and stores it in a property:

{% highlight php %}
<?php

namespace React\EventLoop;

/**
 * A stream_select() based event-loop.
 */
class StreamSelectLoop implements LoopInterface
{

    private $futureTickQueue;

    // ...

    public function __construct()
    {
        $this->futureTickQueue = new FutureTickQueue();
    }

    // ...
}
{% endhighlight %}

Actually, `FutureTickQueue` is a wrapper over the `SplQueue` class. It is used to store and execute callbacks. You can `add()` a callback to the queue, check if the queue `isEmpty()` and execute them with `tick()`. 

>*In examples, I use `StreamSelectLoop` implementation of the `LoopInterface`, but there is no difference in ticks code between different event loop implementations. All implementations initialize queue in the constructor, have proxy methods to add callbacks to the queue and then execute these callbacks on each iteration.*

When calling `tick()` on the `FutureTickQueue` instance it executes only those callbacks that **were added before it started processing** them:

{% highlight php %}
<?php

namespace React\EventLoop\Tick;

class FutureTickQueue
{
    private $queue;

    // ...

    /**
     * Flush the callback queue.
     */
    public function tick()
    {
        // Only invoke as many callbacks as were on the queue when tick() was called.
        $count = $this->queue->count();

        while ($count--) {
            call_user_func(
                $this->queue->dequeue(),
            );
        }
    }

    // ...
}
{% endhighlight %}

So, when you call `futureTick()` on the event loop you simply add a callback to the *future* queue. This method of the event loop is just a wrapper over the queue `add()` method:

{% highlight php %}
<?php

namespace React\EventLoop;

/**
 * A stream_select() based event-loop.
 */
class StreamSelectLoop implements LoopInterface
{
    // ...


    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }

    // ...  
}
{% endhighlight %}

To see this queue in action let's try to schedule echoing a string:

{% highlight php %}
<?php

$eventLoop = \React\EventLoop\Factory::create();

$eventLoop->futureTick(function() {
    echo "Tick\n";
});

echo "Loop starts\n";

$eventLoop->run();

echo "Loop stops\n";
{% endhighlight %}

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/ticks-future-simple.png" alt="ticks-future" class="">
    </p>
</div>

A tick callback must be able to accept no arguments. So, if we want to access some variables within a callback we should bind them to a closure:

{% highlight php %}
<?php

$string = "Tick!\n";

$eventLoop->futureTick(function() use($string) {
    echo $string;
});
{% endhighlight %}

To see the queue in action and how it executes the scheduled callbacks let's try to recursively schedule them.

{% highlight php %}
<?php

use React\EventLoop\LoopInterface;

$eventLoop = \React\EventLoop\Factory::create();

$callback = function () use ($loop, &$callback) {
    echo "Hello world\n";
    $eventLoop->futureTick($callback);
};

$eventLoop->futureTick($callback);

$eventLoop->futureTick(function() use ($loop) {
    $eventLoop->stop();
});

$eventLoop->run();

echo "Finished\n";
{% endhighlight %}

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/ticks-future.png" alt="ticks-future" class="">
    </p>
</div>

At first, we schedule two callbacks. The first one outputs `Hello world` string and then schedules itself for the future tick. The second callback stops an event loop. When we use *future* ticks the callbacks are executed right in the order they have been scheduled. In our case, that means that the second callback stops the loop and a recursively scheduled callback will never be executed.

## Order of Execution

Here is an interesting example to see the actual order of callbacks execution in the event loop. Here is the code: 

{% highlight php %}
<?php 

$eventLoop = \React\EventLoop\Factory::create();

$writable = new \React\Stream\WritableResourceStream(fopen('php://stdout', 'w'), $eventLoop);
$writable->write("I\O");

$eventLoop->addTimer(0, function() {
    echo "Timer\n";
});

$eventLoop->futureTick(function() {
    echo "Future tick\n";
});

$eventLoop->run();
{% endhighlight %}

{% include reactphp-filesystem-note.html %}

We are going to schedule two callbacks: with `futureTick()` and with `addTimeout()`and also we will perform some I/O operation. Before running this script try to guess the expected output...


Then run the script and see what happens:

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/event-loop-order.png" alt="event-loop-order" class="">
    </p>
</div>

You can see the actual order in which the callbacks were executed:

- Future tick queue
- Timers
- I/O

When the event loop runs it executes scheduled callbacks from the *future tick* queue, then timers and then the I/O callbacks:

{% highlight php %}
<?php

namespace React\EventLoop;

/**
 * A stream_select() based event-loop.
 */
class StreamSelectLoop implements LoopInterface
{
    // ...
    public function run()
    {
        $this->running = true;

        while ($this->running) {
            $this->futureTickQueue->tick();

            // timers 

            // streams activity
        }
    }
}
{% endhighlight %}

## Conclusion

Consider a *tick* as one loop iteration where every callback in the queues has been executed synchronously and in order. That means that a tick could be long, it could be short, but we want it to be as short as possible. So, don't place long-running tasks in callbacks, because they will block the loop. When a tick a being stretched out, the event loop won't be able to check the events, which means losing performance for your asynchronous code.

Callbacks that are scheduled with `futureTick()` are placed at the *head* of the event queue. They will be executed right after execution of the current execution context. The queue executes only those callbacks that were added before it started processing them.

Why do I need all of this? What is the use case for these ticks? Actually, ticks can be a solution to convert some synchronous to asynchronous code. We simply schedule a callback onto the next tick of the event loop. But, remember that an event loop runs in a single thread, so your callbacks are not going to run in parallel to other operations. We can simply delay some code to properly order how the operations run, for example when waiting for the events to bind to a new object. Unlike timers, tick callbacks are guaranteed to be executed in the order they are enqueued.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/ticks){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
