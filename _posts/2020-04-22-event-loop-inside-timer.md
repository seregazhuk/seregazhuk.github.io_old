---

title: "ReactPHP Internals: Timer"
layout: post
description: "Dig into ReactPHP event loop to see the way timers work."
tags: [PHP, ReactPHP, EventLoop]
image: "/assets/images/posts/reactphp-event-loop-timer/logo.jpg" 

---

For many of you, ReactPHP and especially its [EventLoop component](https://reactphp.org/event-loop/){:target="_blank"} looks like magic. We know that it handles concurrency, but the way it works inside is a sort of black box. In these tutorials I want to dig into it, to figure out how it works under the hood. How it handles all these events inside. For us, as programmers, it is important to understand how the tools we use work. Even if we will never use this low-level stuff, we need to know how it works. At least we need to understand why we cannot use blocking calls such as `time()` inside.

<div class="row">
    <p class="text-center image col-sm-12">
        <img src="/assets/images/posts/reactphp-event-loop-timer/logo.jpg">
    </p>
</div>

## Asynchronous "Hello World"

So, let's start with a simple example. A sort of asynchronous "hello world" application. But before running examples below you need to install [ReactPHP EventLoop component](https://reactphp.org/event-loop/){:target="_blank"}:

{% highlight bash %}
composer require react/event-loop
{% endhighlight %}

And here is a very simple asynchronous PHP program:

{% highlight php %}
<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$loop->addTimer(0, function () {
    echo ' world' . PHP_EOL;
});

echo 'Hello';

$loop->run();
{% endhighlight %}

What happens in the snippet above? We create an instance of the event loop. We use the factory because it instantiates the most appropriate implementation of the loop. Under the hood, the factory detects the configuration of PHP, checks available extensions, and according to them builds a corresponding event loop. Then we add a timer and schedule the execution of printing the word `world`. Then we print the word 'Hello' and at the end of the script, we run the loop.
The common sense here tells us that scheduling a call for zero seconds equals the immediate execution, right? And we think that this script has a bug and will print `world hello`. But things work a bit differently. Method `addTimer()` works *asynchronously*. It does exactly what it is supposed to do. It schedules a call and that's it. It executes nothing. We just set a timer saying that once the loop is ready it should execute this function. 
So, we set a timer. Then the script prints the word *Hello* and then the loop runs. The loop sees that it has an active timer and the time is up. Thus it executes the corresponding function and prints the word `world`. As a result of the script, we receive `Hello world` message. After that, the loop has no more tasks and quits. This is the end of the program.

Now when we know how the program behaves let's dig into it and see what happens under the hood. 

## Inside the loop

In this tutorial, we will look through the simplest implementation of the loop which is called [`StreamSelectLoop`](https://reactphp.org/event-loop/#streamselectloop){:target="_blank"}. It is available out of the box and doesn't require any extensions. Other available implementations of the event loop use different PHP extensions to manage timers. It's hard to cover all of them in one tutorial and actually there is no need for it. We can use a pure PHP implementation to understand the way timers work. Let's see.

When we call this:

{% highlight php %}
<?php

$loop->addTimer(1, function () {
    echo ' world' . PHP_EOL;
});
{% endhighlight %}

under the hood, the event loop creates an instance of `Timer` and adds it to the list of internal timers. 


{% highlight php %}
<?php

class StreamSelectLoop implements LoopInterface
{
    // ...

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);
        $this->timers->add($timer);

        return $timer;
    }
}
{% endhighlight %}

To create a timer the loop provides an interval to trigger the callback and the callback that should be executed. The last `false` argument says that the timer is going to be a one-off timer. Not a periodic one. Then we add this timer to a collection of timers. 

The collection of timers is responsible for so-called "time management". It handles all these "ticks" that happen inside the loop. Once we call method `run()` on the loop, it starts execution. Each iteration of the loop equals once tick. Inside this iteration, the loop has a lot of things to do: it handles different timers (one-off and periodic ones), streams, and signals. 

{% highlight php %}
<?php

public function run()
{
    $this->running = true;

    while ($this->running) {
        $this->futureTickQueue->tick();
        $this->timers->tick();

        // Calculate $timeout to wait for some activity
        
        $this->waitForStreamActivity($timeout);
    }
}
{% endhighlight %}

The loop "dispatches" a new tick and then calculates a timeout it needs to wait for a new activity. Now, we are interested only in timers. So, what happens when the loop calls `$this->timers->tick()`? Let's open class `Timers` and see.

## Timers

What is [`Timers`](https://github.com/reactphp/event-loop/blob/master/src/Timer/Timers.php){:target="_blank"}? At first glance, it looks like just a collection of timers. It stores all the timers and provides methods for managing them. But at the same time, it is a scheduler that is responsible for their execution. And it is the most interesting part. How can we understand that one of the timers should be called right now and the others are still waiting? 

Remember that class `Timers` is a collection and a scheduler at the same timer? What does it mean? When we add a new timer to the collection we also *schedule* it:

{% highlight php %}
<?php

class Timers 
{
    private $timers = array();
    private $schedule = array();
    private $sorted = true;

    // ...

    public function add(TimerInterface $timer)
    {
        $id = \spl_object_hash($timer);
        $this->timers[$id] = $timer;
        $this->schedule[$id] = $timer->getInterval() + $this->updateTime();
        $this->sorted = false;
    }
}
{% endhighlight %}

Each timer is an instance of `TimerInterface`. We get a unique identifier of the timer by calling [`spl_object_hash()`](https://www.php.net/manual/ru/function.spl-object-hash.php){:target="_blank"} function. Then this hash - an `$id` is used to manipulate the timer. Then we have two associative arrays: one for timer objects and another one for their schedule. By the schedule, I mean a number of seconds from now when this timer should be executed. How can we count it? It is obvious that we need to store the *"current time"* of the system.

{% highlight php %}
<?php

public function __construct()
{
    // prefer high-resolution timer, available as of PHP 7.3+
    $this->useHighResolution = \function_exists('hrtime');
}

public function updateTime()
{
    return $this->time = $this->useHighResolution ? \hrtime(true) * 1e-9 : \microtime(true);
}
{% endhighlight %}

Here we use different PHP functions according to PHP version. For PHP 7.3 and higher we use `hrtime()` otherwise we use `microtime()`. Actually, it doesn't matter for us now. We just need to know that in the `time` property class `Timers` stores the current time of the system. 

OK, we have a list of timers and their schedule. The list of timers contains an array of `TimerInterface` objects and the list with schedule contains corresponding timestamps. Both arrays have corresponding keys made with `spl_object_hash()`. It means that if we take a timer with a key of `1234hash567` from `Timers::$timers` associative array then we can find its timestamp like this:

{% highlight php %}
<?php

// $id = `1234hash567`;

$timer = $this->timers[$id];
$timestamp = $this->schedule[$id];
{% endhighlight %}

Now, we understand the way this collection/schedule works. We know how timers are stored and organized. But when do their callbacks execute? Do you remember that when the loop runs on each iteration it calls `$this->timers->tick()`. This is the place where the timers are "dispatched". Let's see what happens inside...

{% highlight php %}
<?php

final class Timers 
{
    public function tick()
    {
        if (!$this->sorted) {
            $this->sorted = true;
            \asort($this->schedule);
        }

        // ... 
    }
}
{% endhighlight %}

First of all we need to sort the schedule. Property `$schedule` contains an associative array, where keys are ids of stored timers and values are their corresponding intervals. So, we sort the schedule according to intervals with `asort()` function which maintains index association. We also mark the whole collection as sorted, because we don't want to waste time for unnecessary sorts. 

{% highlight php %}
<?php

public function tick()
{
    if (!$this->sorted) {
        $this->sorted = true;
        \asort($this->schedule);
    }

    $time = $this->updateTime();
    foreach ($this->schedule as $id => $scheduled) {
        // 
    }
}
{% endhighlight %}

So, we have a sorted schedule. Then we garb current time of the system and start processing the schedule:

{% highlight php %}
<?php

foreach ($this->schedule as $id => $scheduled) {
    // schedule is ordered, so loop until first timer that is not scheduled for execution now
    if ($scheduled >= $time) {
        break;
    }

    // skip any timers that are removed while we process the current schedule
    if (!isset($this->schedule[$id]) || $this->schedule[$id] !== $scheduled) {
        continue;
    }

    $timer = $this->timers[$id];
    \call_user_func($timer->getCallback(), $timer);

    // re-schedule if this is a periodic timer and it has not been cancelled explicitly already
    if ($timer->isPeriodic() && isset($this->timers[$id])) {
        $this->schedule[$id] = $timer->getInterval() + $time;
        $this->sorted = false;
    } else {
        unset($this->timers[$id], $this->schedule[$id]);
    }
}
{% endhighlight %}

The code above looks a bit complex but it covers a very simple flow. At first we check whether we have any timers that should be executed or not. If at this current moment the most recent timer is still "running" (its interval is in the future) we just quit `tick()` method and return to event the loop. 

Then there is a not obvious part. We check whether there is something in the schedule or not. Why should we check this thing? Let me explain. We have scheduled a timer, right? How can it disappear? You should remember that `Timers` is a collection and thus you can both add and remove something from it. It means that you can cancel (or remove) a timer. And furthermore, one timer can cancel another one. Here is the code for timer cancellation:

{% highlight php %}
<?php

public function cancel(TimerInterface $timer)
{
    $id = \spl_object_hash($timer);
    unset($this->timers[$id], $this->schedule[$id]);
}
{% endhighlight %}

It is just removed from both the schedule and the list. So, when we start execution of all the timers we should be prepared that they can change the schedule in the process. 

After that we get the timer object and execute its callback:

{% highlight php %}
<?php

$timer = $this->timers[$id];
\call_user_func($timer->getCallback(), $timer);
{% endhighlight %}

This is the place where the scheduled callback is executed. I think that for understanding the internals of ReactPHP this line is very important. You see that we we simply execute a callable. It means that PHP thread will stop and wait till this callback is executed. And only once the callback is done the execution flow continues. This is the reason why our callbacks, our own code that we want to execute inside the loop responding to different event should not contain any blocking and long-running calls. Our callbacks should be as fast as possible. Otherwise the event loop stops and waits for our code. It means that it doesn't handle other events that may occur at this moment. 

Then we check whether the timer is a periodic or not. If it is a periodic one and thus should be repeated we "reschedule" it otherwise it is removed from the collection:

{% highlight php %}
<?php
if ($timer->isPeriodic() && isset($this->timers[$id])) {
    $this->schedule[$id] = $timer->getInterval() + $time;
    $this->sorted = false;
} else {
    unset($this->timers[$id], $this->schedule[$id]);
}
{% endhighlight %}

What does "reschedule" mean? We count the time when the timer should be executed next and change this value in the schedule. Note, that when the timer is rescheduled it will be executed on the next iteration of the event loop, on the next "tick". 

## Conclusion

And that's it. No magic and actually nothing complex at all. Pretty straightforward PHP code. Now, you understand the way our asynchronous "hello world" application works. There are no child processes, no forking, only pure PHP code that is executed line by line. The asynchronous execution of code is implemented by scheduling pieces of code to be executed in the future. 
