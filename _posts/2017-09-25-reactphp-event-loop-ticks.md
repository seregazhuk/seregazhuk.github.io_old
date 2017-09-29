---
title: "Understanding ReactPHP Event Loop Ticks"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Understanding event loop ticks in ReactPHP."
---

## What Is Tick?

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/ticks.png" alt="ticks" class="">
</p>

*Tick* is one loop iteration where every callback in the queues has been executed synchronously and in order. ReactPHP event loop implementation has two main methods to work with ticks:

- `public function nextTick(callable $listener);`
- `public function futureTick(callable $listener);`

Both methods can be used to schedule a callback to be invoked on a future iteration of the event loop. When being executed a callback receives an instance of the event loop as an argument. But then what's the difference between *next* and *future* ticks? Let's figure this out.

## Difference Between Future And Next Ticks

ReactPHP provides several implementations of the `LoopInterface` according to extensions available in your system. At the time of writing this article there are four available implementations, but all of them share the same next/future ticks logic. All four implementations of the event loop use two queues from `React\EventLoop\Tick` namespace: `FutureTickQueue` and `NextTickQueue`. When an event loop is being created it initialized both queues and stores them in properties:

{% highlight php %}
<?php

namespace React\EventLoop;

/**
 * A stream_select() based event-loop.
 */
class StreamSelectLoop implements LoopInterface
{

    private $nextTickQueue;
    private $futureTickQueue;

    // ...

    public function __construct()
    {
        $this->nextTickQueue = new NextTickQueue($this);
        $this->futureTickQueue = new FutureTickQueue($this);
    }

    // ...
}
{% endhighlight %}

Both queues share the same public interface and are wrappers over the `SplQueue` class. They are used to store callbacks but have some differences in executing them. You can `add()` a callback to the queue, check if the queue `isEmpty()` and the most interesting method is `tick()`. This method is responsible for executing callbacks from the queue. Here is the difference between `NextTickQueue` and `FutureTickQueue`.

>*In examples, I use `StreamSelectLoop` implementation of the `LoopInterface`, but there is no difference in ticks code between different event loop implementations. All implementations initialize queues in the constructor, have proxy methods to add callbacks to the queues and then execute these callbacks on each iteration.*

When calling `tick()` on the `NextTickQueue` instance it executes **all** callbacks stored in the queue:

{% highlight php %}
<?php

 /**
  * Flush the callback queue.
  */
public function tick()
{
    while (!$this->queue->isEmpty()) {
        call_user_func(
            $this->queue->dequeue(),
            $this->eventLoop
        );
    }
}
{% endhighlight %}

While the future queue executes only those callbacks that **were added before it started processing** them. Here is `tick()` implementation in `FutureTickQueue` class:

{% highlight php %}
<?php

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
            $this->eventLoop
        );
    }
}
{% endhighlight %}

So, when you call `nextTick()` or `futureTick()` on the event loop you simply add a callback to the *next* of *future* queue accordingly. These event loop methods are just wrappers over the queue `add()` method:

{% highlight php %}
<?php

namespace React\EventLoop;

/**
 * A stream_select() based event-loop.
 */
class StreamSelectLoop implements LoopInterface
{
    // ...

    public function nextTick(callable $listener)
    {
        $this->nextTickQueue->add($listener);
    }

    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }

    // ...  
}
{% endhighlight %}

When the event loop runs it executes *next tick* callbacks and then *future tick* ones:

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
            $this->nextTickQueue->tick();

            $this->futureTickQueue->tick();

            // ...
        }
    }
}
{% endhighlight %}

To see the actual order of callbacks execution run this example. We are going to schedule three different callbacks, one with `nextTick()`, one with `futureTick()` and one with `addTimeout()`. Add some output and see what happens:

{% highlight php %}
<?php 

$eventLoop = \React\EventLoop\Factory::create();

$eventLoop->addTimer(0, function(){
    echo "Timer\n";
});

$eventLoop->futureTick(function(){
    echo "Future tick\n";
});

$eventLoop->nextTick(function(){
    echo "Next tick\n";
});

$eventLoop->run();
{% endhighlight %}

When we run this script the output is the following:

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/ticks-order.png" alt="ticks-order" class="">
    </p>
</div>

You can see the actual order in which the callbacks were executed. 

But to feel the real difference between *next* and *future* ticks we need a bit more complex example. Let's try to recursively schedule a callback with `futureTick()`:

{% highlight php %}
<?php

use React\EventLoop\LoopInterface;

$eventLoop = \React\EventLoop\Factory::create();

$callback = function (LoopInterface $eventLoop) use (&$callback) {
    echo "Hello world\n";
    $eventLoop->futureTick($callback);
};

$eventLoop->futureTick($callback);
$eventLoop->futureTick(function(LoopInterface $eventLoop){
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

At first, we schedule two callbacks. The first one outputs `Hello world` string and then schedules itself for the future tick. The second callback stops an event loop. When we use *future* ticks the callbacks are executed right in the order they have been scheduled. In our case, that means that a recursively scheduled callback will never be executed.

Now, let's use *next* ticks and see what happens:

{% highlight php %}
<?php

$eventLoop = \React\EventLoop\Factory::create();

$callback = function (LoopInterface $eventLoop) use (&$callback) {
    echo "Hello world\n";
    $eventLoop->nextTick($callback);
};

$eventLoop->nextTick($callback);
$eventLoop->nextTick(function(LoopInterface $eventLoop){
    $eventLoop->stop();
});

$eventLoop->run();

echo "Finished\n";

{% endhighlight %}

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/ticks-next.gif" alt="ticks-next" class="">
    </p>
</div>

You can see that the output is an infinite loop of the *next tick* callback calls. In this way, we actually never reach the second registered callback. Every time the first callback is being executed we recursively schedule a new one. An event loop processes the next tick queue **until the queue is empty**. 

This introduces a problem: **starvation**. When recursively/repeatedly filling up the next tick queue using `nextTick()` method forces the event loop to keep processing next tick queue indefinitely without moving forward. This will cause I/O and other queues to starve forever because an event loop cannot continue without emptying the next tick queue (just like `while(true)` loop).

## Conclusion

Consider a *tick* as one loop iteration where every callback in the queues has been executed synchronously and in order. That means that a tick could be long, it could be short, but we want it to be as short as possible. So, don't place long-running tasks in callbacks, because they will block the loop. When a tick a being stretched out, the event loop won't be able to check the events, which means losing performance for your asynchronous code.

Callbacks that are scheduled with `nextTick()` or `futureTick()` are placed at the *head* of the event queue. They will be executed right after execution of the current execution context. The difference between *next* and *future* queues is that future queue executes only those callbacks that were added before it started processing them, while *next* queue executes all callbacks stored in the queue.

Why do I need all of this? What is the use case for these ticks? Actually ticks can be a solution to convert some synchronous to asynchronous code. We simply schedule a callback onto the next tick of the event loop. But, remember that an event loop runs in a single thread, so your callbacks are not going to run in parallel to other operations. We can simply delay some code to properly order how the operations run, for example when waiting for the events to bind to a new object. 


<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/ticks).

<strong>Other ReactPHP articles:</strong>

- [Event loop and timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Streams]({% post_url 2017-06-12-phpreact-streams %})
- [Promises]({% post_url 2017-06-16-phpreact-promises %})
- [Chat on sockets: server]({% post_url 2017-06-22-reactphp-chat-server %}) and  [client]({% post_url 2017-06-24-reactphp-chat-client %})
- [UDP chat]({% post_url 2017-07-05-reactphp-udp %})
- [Video streaming server]({% post_url 2017-07-17-reatcphp-http-server %})
- [Parallel downloads with async http requests]({% post_url 2017-07-26-reactphp-http-client %})
- [Managing Child Processes]({% post_url 2017-08-07-reactphp-child-process %})
- [Cancelling Promises With Timers]({% post_url 2017-08-22-reactphp-promise-timers %})
- [Resolving DNS Asynchronously]({% post_url 2017-09-03-reactphp-dns %})
- [Promise-Based Cache]({% post_url 2017-09-15-reactphp-cache %})

