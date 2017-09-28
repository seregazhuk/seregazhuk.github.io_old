---
title: "Understanding ReactPHP Event Loop Ticks"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Understanding event loop ticks in ReactPHP."
---

## What Is Tick?

Actually, consider a *tick* as one loop iteration where every callback in the queues has been executed synchronously and in order. That means that a tick could be long, it could be short, but we want it to be as short as possible. So, don't place long-running tasks in callbacks, because they will block the loop. When a tick a being stretched out, the event loop won't be able to check the events, which means losing performance for your asynchronous code.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/ticks.png" alt="ticks" class="">
</p>

In ReactPHP ecosystem an event loop is the core component. Any other component requires it. ReactPHP provides several implementations of the `LoopInterface` according to extensions available in your system. At the time of writing this article there are four available implementations: 

- `StreamSelectLoop` - the only implementation which works out of the box with PHP. It does a simple `select` system call. It's not the most performant of loops but still does the job quite well. 
- `LibEventLoop` uses the `libevent` pecl extension. `libevent` itself supports a number of system-specific backends (epoll, kqueue). 
- `LibEvLoop` uses the `libev` pecl extension. It supports the same backends as libevent. 
- `ExtEventLoop` uses the `event` pecl extension. It supports the same backends as libevent. 

`LoopInterface` contains two methods to work with ticks:

- `public function nextTick(callable $listener);`
- `public function futureTick(callable $listener);`

Both methods can be used to schedule a callback to be invoked on a future iteration of the event loop. When being executed a callback receives an instance of the event loop as an argument. But then what's the difference between *next* and *future* ticks? Let's figure this out.

## Difference Between Future And Next Ticks

Each iteration of the event loop is called a *tick*. When we schedule a callback with `nextTick()` or `futureTick()` it will be executed
All four implementations of the event loop use two queues from `React\EventLoop\Tick` namespace: `FutureTickQueue` and `NextTickQueue`. When an event loop is being created it initialized both queues and stores them in properties:

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

Both queues have the same public interface and are wrappers over the `SplQueue` class. They are used to store callbacks but have some differences in executing them. You can `add()` a callback to the queue, check if the queue `isEmpty()` and the most interesting method is `tick()`. This method is responsible for executing callbacks from the queue. Here is the difference between `NextTickQueue` and `FutureTickQueue`.

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

At first, we schedule two callbacks. The first one outputs `Hello world` string and then schedules itself for the future tick. The second callback stops an event loop. When we use *future* ticks the callbacks are executed right in the order they have been scheduled. 

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

Callbacks that are scheduled with `nextTick()` or `futureTick()` are placed at the *head* of the event queue. They will be executed right after execution of the current execution context. The difference between *next* and *future* queues is that future queue executes only those callbacks that were added before it started processing them, while *next* queue executes all callbacks stored in the queue.

Why do I need all of this? What is the use case for these ticks? Actually ticks can be a solution to convert some synchronous to asynchronous code. We simply schedule a callback onto the next tick of the event loop. But, remember that an event loop runs in a single thread, so your callbacks are not going to run in parallel to other operations. We can simply delay some code to properly order how the operations run, for example when waiting for the events to bind to a new object. 




