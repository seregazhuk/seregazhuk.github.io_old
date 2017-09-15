---
title: "Event-Driven PHP with ReactPHP: Event Loop And Timers"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Event-Driven PHP with ReactPHP: Event Loop And Timers"
---
# What is ReactPHP

## The Problem
PHP was born in the 90s and was a very powerful tool for creating web pages. From its born it has a synchronous run-time,  that means that we start execution of some function, and the code flow is blocked until this function is being executed. And it was not considered as something bad. On the opposite many libraries consider that the blocked flow is normal. They assume that the PHP code is written in the imperative way when one command usually follows another and it is normal if something blocks the flow. For example, let's consider the traditional request-response cycle. The client opens a web page in the browser and the browser sends a request to the web server. The web server searches for the files that match the requested one. Then the web server looks into the file, if it finds PHP code there it processes this file. The PHP script itself may interact with the database to receive some data from it or to store some data. Then PHP produces the final HTML which is going to be returned to the client.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/request-response-cycle.jpg" alt="cgn-edit" class="">
</p>

So, when you make a request to your database and have to wait some seconds to get the results we can assume that it is OK because the next commands in the script need these results. In the request-response lifecycle, it can be considered OK. But this approach makes PHP *slow* because we have to wait.

But since the 90th the world has changed a lot. Now PHP is something more than a simple script, which is used to render a web page in the request-response cycle:

- Live Data (continuously auto-updating feed/chat)
- HTTP APIs (RESTful)
- Integration with 3rd party clients 
- Command Line Interface tools

And here [ReactPHP](http://reactphp.org) enters the game... The main idea behind React is: 

> *calculations are fast, input/output is slow.*

I/O operations are extremely slow compared with the CPU calculations. For example, when we talk about CPU operations we use *nanoseconds*, but when we deal with the network communication we often consider *milliseconds*. Simply try to ping google.com:

{% highlight bash %}
$ping google.com
PING google.com (188.43.61.187): 56 data bytes
64 bytes from 188.43.61.187: icmp_seq=0 ttl=58 time=8.183 ms
64 bytes from 188.43.61.187: icmp_seq=1 ttl=58 time=9.594 ms
64 bytes from 188.43.61.187: icmp_seq=2 ttl=58 time=8.997 ms
64 bytes from 188.43.61.187: icmp_seq=3 ttl=58 time=10.550 ms
64 bytes from 188.43.61.187: icmp_seq=4 ttl=58 time=8.305 ms
64 bytes from 188.43.61.187: icmp_seq=5 ttl=58 time=7.899 ms
{% endhighlight %}

The average latency here is about 8 milliseconds. While waiting for a new packet the CPU can perform more than 80 millions of cycles. The difference is really huge! So, the programs that are written in a synchronous and blocking way spend a lot of time waiting for disk/network operations to be finished:

{% highlight php %}
<?php

$statement = $dbh->prepare(
    'SELECT id, slug, name FROM categories WHERE is_active = 1'
);

$statement->execute(); 

// ... wait for database to execute the request

$categories = $statement->fetchAll();
{% endhighlight %}


But input/output is everywhere: API calls, filesystem operations, interaction with the database. So, we need some way to run these operations *in the background*, asynchronously.

**Threads**. We can perform all operations in different threads, but isolation and thread-safety come at a price. The problem with threads is a slow context switching and we also have to shuffle memory around the threads. The program itself becomes quite more complex because now we have to sync the threads.

**Pool of processes** can be another alternative to organizing asynchronous I/O operations. We can fork a bunch of processes and then run them concurrently. It is essentially the same as threading, except that it is happening within the language.

## Event-Driven Architecture

One more alternative is to use *non-blocking event-driven* I/O. ReactPHP is based on the [Reactor pattern](https://en.wikipedia.org/wiki/Reactor_pattern) which is one implementation technique of the event-driven architecture. We can listen to the events in a synchronous way and when an incoming event occurs, it is dispatched to a handler (callback), that can handle this event.

The idea is to start multiple I/O operations, and we don't need to wait till they will be finished. Instead, we will be notified when something interesting will happen: the response will be received or some operation will be finished and then we can *react* to this. There is no more need to waste the time. The I/O blocks will be executed as long as they require, but when the I/O block is ready we will be notified, and we have a callback for this event to handle the results. And because the whole program runs in the single thread we can handle and process all of these I/O blocks one by one: when one is ready, we handle it, then another block can be done and we continue handling the results. Comparing this approach to threads we can do much more at the same time. 

## Components
ReactPHP is not a framework, it is a [set of independent components](https://github.com/reactphp). You can take the parts you need and use only them.

Core components:

- [EventLoop](http://reactphp.org/event-loop/)
- [Stream](http://reactphp.org/stream/)
- [Promise](http://reactphp.org/promise/)

# Event Loop

[Event loop](http://reactphp.org/event-loop/) is the core of the ReactPHP, it is the most low-level component. Every other component uses it. Event loop runs in a single thread and is responsible for scheduling asynchronous operations. There is no other code being executed in parallel. Event loop is the only synchronous thing. Another words 

> *everything except your code runs in parallel.*

Event loop implements the Reactor Pattern. You register an event: subscribe to it and start listening. Then you get notified when this event is fired, so you can *react* to this event via a handler and execute some code. Every iteration of the loop is called a *tick*. When there are no more listeners in the loop, the loop finishes.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/event-loop.jpg" alt="cgn-edit" class="">
</p>

From the consumer point of view, you don't have to deal a lot with it, unless you are doing something special on top of the loop. You construct it once via the factory and then you just pass it along through the dependency injection to set up the other components, and then you simply run it once to start the loop.

1. You create it once at the beginning of the program. 
2. Set up it.
3. And run it once at the end of the program.

{% highlight php %}
<?php
$loop = React\EventLoop\Factory::create();

// some code that uses the instance of the loop event

$loop->run();
{% endhighlight %}

### Implementations

ReactPHP provides several implementations of the event loop depending on what extensions are available in the system. The most convenient and recommended way to create an instance of the loop is to use a factory:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
{% endhighlight %}

Under the hood this factory simply checks for available extensions and selects the appropriate implementation for the event loop:

{% highlight php %}
<?php
namespace React\EventLoop;

class Factory
{
    public static function create()
    {

        if (function_exists('event_base_new')) {
            return new LibEventLoop();
        }

        if (class_exists('libev\EventLoop', false)) {
            return new LibEvLoop;
        }

        if (class_exists('EventBase', false)) {
            return new ExtEventLoop;
        }

        return new StreamSelectLoop();
    }
}
{% endhighlight %}

There are four available implementations, each implementing `React\EventLoop\LoopInterface`:

- `StreamSelectLoop` - the only implementation which works out of the box with PHP. It does a simple `select` system call. It's not the most performant of loops but still does the job quite well. 
- `LibEventLoop` uses the `libevent` pecl extension. `libevent` itself supports a number of system-specific backends (epoll, kqueue). 
- `LibEvLoop` uses the `libev` pecl extension. It supports the same backends as libevent. 
- `ExtEventLoop` uses the `event` pecl extension. It supports the same backends as libevent. 

All of the loops support the following features:

- File descriptor polling 
- Timers 
- Deferred execution of callbacks

While each implementation of the event loop is different, the program itself should not depend on the particular loop implementation. There may be some differences in the exact timing of the execution or the order in which different types of events are executed. But the behavior of the program should not be affected by these differences.

# Timers

Timers can execute some code at a later time, a number of seconds in the future (Just like `setTimeout()` and `setInterval()` do in JavaScript). They are not the same as a `sleep` function, instead, they are *events* in the future. Timers will run as early as possible after the specified amount of time has passed.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/timers.jpg" alt="cgn-edit" class="">
</p>

**Notice**. *Asynchronous* is not the same as *parallel*. Asynchrony is the possibility of inconsistent code execution. Parallelism is the ability to execute the same code at one time. Event loop works asynchronously, but not in parallel. That means that timers are not time-accurate and can run a little late. Also, if you have several timers that are scheduled to execute at the same time, the order of their execution is not guaranteed. Any timer will be executed **not earlier than the specified time**. The code runs in the one thread and cannot be interrupted. That means that all timers are executed in the same thread as the event loop runs. In the situation when one timer is being executed too long, all the other timers will wait, until this timer will be done. Also, it is possible that some timers will **never** be executed.

## Periodic Timer
This timer schedules it's callback to be invoked repeatedly every specified number of seconds. Periodic timer can be added to the loop with `addPeriodicTimer($interval, callable $callback)` method. It accepts an interval in seconds and a callback, which will be executed at the end of this interval:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$counter = 0;

$loop->addPeriodicTimer(2, function() use(&$counter) {
    $counter++;
    echo "$counter\n";
});

$loop->run();
{% endhighlight %}

A periodic timer is registered with the event loop. Then we start event loop with `$loop->run()`, when a timer is fired the code flow leaves an event loop and a *timer* code is being executed. Every two seconds, the timer displays an increasing number. Event loop will run endlessly.

<p class="">
    <img src="/assets/images/posts/reactphp/periodic_timer.gif" alt="cgn-edit" class="">
</p>


A callback can accept an instance of the timer, in which this callback is executed:

{% highlight php %}
<?php
use \React\EventLoop\Timer\TimerInterface;

$loop->addPeriodicTimer(2, function(TimerInterface $timer) {
    // ...
});
{% endhighlight %}

## One-off Timer

The only difference with the periodic timer is that this timer will be executed only once and then will be removed from the timers storage.

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$loop->addTimer(2, function() {
    echo "Hello world\n";
});

$loop->run();
echo "finished\n";
{% endhighlight %}

In 2 seconds the script will output `Hello world` then the timer will be removed and event loop will stop.

<p class="">
    <img src="/assets/images/posts/reactphp/simple_timer.gif" alt="cgn-edit" class="">
</p>

## Controlling Timers

There are two more methods available in the loop object to control timers:

- `cancelTimer(TimerInterface $timer)` to detach the specified timer.
- `isTimerActive(TimerInterface $timer)` to check if the specified timer if attached to the event loop.

We can use a passed instance of the timer to detach it from the event loop:

{% highlight php %}
<?php

use \React\EventLoop\Timer\TimerInterface;

$loop = React\EventLoop\Factory::create();
$counter = 0;

$loop->addPeriodicTimer(2, function(TimerInterface $timer)  use(&$counter, $loop) {
    $counter++;
    echo "$counter\n";

    if($counter == 5) {
        $loop->cancelTimer($timer);
    }
});

$loop->run();
echo "Done\n";
{% endhighlight %}

After the fifth execution, this timer will be detached. When event loop is empty it stops:

<p class="">
    <img src="/assets/images/posts/reactphp/stop_timer.gif" alt="cgn-edit" class="">
</p>

Timers can interact with each other. Both methods `addTimer` and `addPeriodicTimer` return an instance of the attached timer. Then we can use this instance and pass it to the callback of the another timer. This way we can specify a timeout for some event:

{% highlight php %}
<?php
$loop = React\EventLoop\Factory::create();
$counter = 0;

$periodicTimer = $loop->addPeriodicTimer(2, function() use(&$counter, $loop) {
    $counter++;
    echo "$counter\n";
});

$loop->addTimer(5, function() use($periodicTimer, $loop) {
    $loop->cancelTimer($periodicTimer);
});

$loop->run();
{% endhighlight %}

In the snippet above the periodic timer will be executed only first 5 seconds, after that, it will be detached from the event loop. In the situations when we don't know exactly if the timer is running or not we can use `isTimerActive(TimerInterface $timer)` method on the event object:

{% highlight php %}
<?php

use \React\EventLoop\Timer\TimerInterface;

$loop = React\EventLoop\Factory::create();
$counter = 0;

$loop->addPeriodicTimer(2, function(TimerInterface $timer) use($loop, &$counter) {
    $counter++;
    echo "$counter ";

    if($counter == 5){
        $loop->cancelTimer($timer);    
    } 

    echo $timer->isActive() ? 
        'Timer active' : 
        'Timer detached';

    echo "\n";
});

$loop->run();
echo 'stop';
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/is_active_timer.gif" alt="cgn-edit" class="">
</p>


**Notice**. Since all the timers are executed in the same thread, you should be aware of blocking operations in the callbacks. One blocking timer can stop the whole event loop like this:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$i = 0;
$loop->addPeriodicTimer(1, function() use (&$i) {
    echo ++$i, "\n";
});

$loop->addTimer(2, function () {
    sleep(10);
});

$loop->run();
{% endhighlight %}

The first periodic timer will wait for 10 seconds until the second one-off timer will be executed.

Every instance of timer implements `React\EventLoop\Timer\TimerInteface`:

- `getLoop()` returns an event loop object with which this timer is associated.
- `isActive()` a wrapper over the loop `isTimerActive(TimerInterface $timer)` method.
- `cancel()` a wrapper overt the loop `cancelTimer(TimerInterface $timer)` method.
- `getInterval()`, `getCallback()` getters for the timer's interval and a callback accordingly.
- `getData()`, `setData($data)` can be usefull to set arbitrary data associated with timer.
- `isPeriodic()` returns `true` if the timer is periodic.

# Conclusion

Whenever you have to wait for something (network, filesystem input/output operations) - consider ReactPHP. You don't have to use it but consider. All these tools allow to start or to defer operations and to get a notification whenever something interesting happens. The main loop is the only thing that is going to be blocking. It has to check for the events, so it could react for the incoming data. When we execute for example `sleep(10)`, the loop will not be executed during these 10 seconds. And everything that loop is going to do during this time will be delayed by these seconds. Never block the loop, for situations when you need to wait, you should use timers.

Timers can be used to execute some code in a delayed future. This code *may be executed after* the specified interval. Each timer is being executed in the same thread as the whole event loop, so any timer can affect this loop. Timers can be useful for non-blocking operations such as I/O, but executing a long living code in them can lead to the unexpected results.

Also, everything that could take longer than about one millisecond should be reconsidered. When you cannot avoid using blocking functions the common recommendation is to fork this process, so you can continue running the event loop without any delays.

This post was inspired by [Christian Lück](https://twitter.com/another_clue) and his conference talks:

- [Pushing the limits with React PHP - PHP Unconference Hamburg](https://www.youtube.com/watch?v=-5ZdGUvOqx4)
- [T3DD16 Pushing the limits of PHP with React PHP with Christian Lück - TYPO3 Developer Days 2016](https://www.youtube.com/watch?v=giCIozOefy0)

<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/eventloop-and-timers).

<strong>Other ReactPHP articles:</strong>

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
