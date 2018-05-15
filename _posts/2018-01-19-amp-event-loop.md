---
title: "Introduction To Amp Event Loop"
tags: [PHP, AsyncPHP, Amp, Event Loop, Event-Driven Programming]
description: "Introduction to asynchronous PHP and Amp event loop"
image: "/assets/images/posts/amp-event-loop/logo.jpg" 
---

## Event Loop

All asynchronous *magic* would be impossible without Even loop. It is the core of any asynchronous application. We register events and handlers for them. When an event is fired the event loop triggers an appropriate handler. This allows a caller to instantiate an operation and continue without waiting for this operation to be completed. Later the caller will be notified about completion.

<p class="text-center image">
    <img itemprop="image" src="/assets/images/posts/amp-event-loop/logo.jpg" alt="event-loop-logo" class="">
</p>

Before we start I want to point that in JavaScript we have event loop *out-of-box*, that means that we even don't care that exists. But in PHP things are different. We have to create it manually. In Amp, event loop is available globally via static methods provided by `Amp\Loop` class.
 So, let's start with some *Hello world* examples.

## Defer Code

To run some code inside the loop we can use `Loop::run()` method. It accepts a callback. Then this callback deferred. 

{% highlight php %}
<?php 

use Amp\Loop;

echo 'Before event loop' . PHP_EOL;

Loop::run(function ()  {
    echo 'We are inside a loop' . PHP_EOL;
});

echo 'After event loop' . PHP_EOL;
{% endhighlight %}

This snippet is not really very interesting, the code here looks *synchronous* even if it uses an event loop. When running this script we receive an expected output:

{% highlight bash %}
Before event loop
We are inside a loop
After event loop
{% endhighlight %}

But it perfectly illustrates the integration of the event loop into a synchronous PHP script. Everything before the loop executes synchronously as it is. Then event loop receives flow control and executes everything inside it. When all schedules tasks are done (or you explicitly stop the loop with `Loop::stop()` call), the flow control leaves the loop and continues synchronously executing the script.

<p class="text-center image">
    <img src="/assets/images/posts/amp-event-loop/defer-logo.jpg" alt="event-loop-defer-logo" class="">
</p>

Now, let's try something more complicated:

{% highlight php %}
<?php

Loop::run(function ()  {
    Loop::defer(function () {
        echo 'deferred code' . PHP_EOL;
    });
    echo 'inside loop' . PHP_EOL;
});
{% endhighlight %}

With this script we now can see asynchronous execution and that the flow has changed:

{% highlight bash %}
inside loop
deferred code
{% endhighlight %}

That happens because when we schedule some code with `Loop::defer()` this code is deferred to execute in the next iteration of the event loop. In our example, the first iteration of the loop has one `echo 'inside loop' . PHP_EOL` call. The scheduled code will be executed when all code in the first iteration is done.  

Actually `Loop::run()` implicitly defers passed callback. This can be demonstrated by scheduling a callback before running the loop:

{% highlight php %}
<?php

Loop::defer(function () {
    echo 'first iteration' . PHP_EOL;
});

Loop::run(function ()  {
    Loop::defer(function () {
        echo 'third iteration' . PHP_EOL;
    });
    echo 'second iteration' . PHP_EOL;
});
{% endhighlight %}

The output shows that the first deferred callback is executed before the callback, which is passed to `Loop::run()` call:

{% highlight bash %}
first iteration
second iteration
third iteration
{% endhighlight %}

## Delay Code

<p class="text-center image">
    <img src="/assets/images/posts/amp-event-loop/timer-logo.jpg" alt="event-loop-timer-logo" class="">
</p>

Now its time to write Amp version of JavaScript `setTimeout()` call:

{% highlight js %}
setTimeout(function () {
    console.log('After timeout');
}, 1000);

console.log('Before timeout');
{% endhighlight %}

To delay some code event loop has `delay()` method. Like in JavaScript it accepts a number of milliseconds and a callback:

{% highlight php %}
<?php

Loop::run(function () {
    Loop::delay(1000, function () {
        echo date('H:i:s') . ' After timeout' . PHP_EOL;
    });
    echo date('H:i:s') . ' Before timeout' . PHP_EOL;
});
{% endhighlight %}

Execute it and we receive exactly the same results as with JavaScript! Asynchronous code, cool!


<p class="">
    <img src="/assets/images/posts/amp-event-loop/delay.gif" alt="delay" class="">
</p>

## Repeat Code

We can rewrite one more JavaScript function with Amp: `setInterval()`. It has the same set of arguments as `setTimeout()` does. It also schedules a specified callback, but instead of executing it once, this callback is being repeatedly executed after a specified period of time. 

<p class="text-center image">
    <img src="/assets/images/posts/amp-event-loop/repeat-logo.jpg" alt="event-loop-repeat-logo" class="">
</p>

To repeatedly execute some code event loop has `repeat()` method:

{% highlight php %}
<?php

Loop::run(function ()  {
    Loop::repeat(500, function () {
        echo 'Hello world' . PHP_EOL;
    });
});
{% endhighlight %}

This is Amp version of this JavaScript `setInterval()` call:

{% highlight js %}
setInterval(function () {
    console.log('Hello world'); 
}, 500);
{% endhighlight %}

If you run this code you will see that it endlessly spams your terminal with `Hello world` string. Why? 

<p class="">
    <img src="/assets/images/posts/amp-event-loop/repeat-endless.gif" alt="repeat-endless" class="">
</p>

Do you remember how event loop works? It takes the flow and executes all scheduled tasks. `Loop::repeat()` call will endlessly schedule a task until you explicitly cancel it. Behind the scenes, `Loop::repeat()` creates a timer watcher and returns its id. This id is also passed to a specified callback as a first argument. So, to cancel this timer you should explicitly call `Loop::cancel()` and provide a watcher's id:


{% highlight php %}
<?php

Loop::run(function () {
    Loop::repeat(500, function ($watcherId) {
        static $counter = 1;
        if($counter == 5) {
            Loop::cancel($watcherId);
        }
        echo 'Hello world' . PHP_EOL;
        $counter++;
    });
});

echo 'After the loop' . PHP_EOL;
{% endhighlight %}

The code above output `Hello world` five times and then cancels the timer. 

<p class="">
    <img src="/assets/images/posts/amp-event-loop/repeat-with-stop.gif" alt="repeat-with-stop" class="">
</p>

The same result can be achieved by stopping the loop:

{% highlight php %}
<?php 

Loop::run(function () {
    Loop::repeat(500, function () {
        static $counter = 1;
        if($counter == 5) {
            Loop::stop();
        }
        echo 'Hello world' . PHP_EOL;
        $counter++;
    });
});
{% endhighlight %}

>*Note, that `Loop::cancel()` or `Loop::stop()` doesn't immediately break the callback. That is why we can see exactly 5 `Hello world` messages.*

## Watchers
Every time when we schedule some code with `defer()`, `repeat()` or `delay()` behind the scenes event loop creates a timer watcher. This timer watcher contains information about callback, data associated with it, timer id, and the way this callback will be executed (once, once after a given time or repeatedly). All watchers can be canceled via `Loop::cancel()`, but in situations when you need to repeatedly cancel and register them it is preferred to pause and then resume the watcher.

`Loop::disable($watcherId)` method pauses a watcher with a specified id. To resume a paused watcher use `Loop::enable($watcherId)`:

{% highlight php %}
<?php 

$watcherId = Loop::repeat(500, function () {
    echo 'Repeat' . PHP_EOL;
});

Loop::delay(1500, function () use ($watcherId) {
    echo 'Pausing watcher' . PHP_EOL;
    Loop::disable($watcherId);
});

Loop::delay(2000, function () use ($watcherId){
    echo 'Resuming watcher' . PHP_EOL;
    Loop::enable($watcherId);
});

Loop::run();
{% endhighlight %}

In the snippet above we schedule code `echo 'Repeat' . PHP_EOL` to repeatedly execute every half a second. Then we set up two delays: the first one pauses our *repeated code*, then the second one resumes it. If you run this code you will see the following:

<p class="">
    <img src="/assets/images/posts/amp-event-loop/pause-resume-watchers.gif" alt="pause-resume-watchers" class="">
</p>


Scheduled `echo` statement executes twice and then the watcher is paused. Then we resume a watcher and in continues spamming with `Repeat` string.

>It’s important to always cancel persistent watchers once you’re finished with them or you’ll create memory leaks in your application. 

## What Is Hidden Behind Event Loop?

Do I need to install additional extensions to make all this magic work? Not necessary. You can download Amp via composer and start writing asynchronous code, no additional extensions are required. While there are several extensions with event-loop implementations: [pecl/ev](https://pecl.php.net/package/ev){:target="_blank"}, [pecl/event](https://pecl.php.net/package/event){:target="_blank"}, [php-uv](https://github.com/bwoebi/php-uv){:target="_blank"}, none of them is required. And Amp has drivers for all of them. Basically, the main difference between different loop implementations lays in performance characteristics. Behind the scenes, `Amp\Loop` is clever enough to detect your environment and to choose the best available driver for it. Also, it is OK, if you don't have any installed extensions, in this case, Amp will use `NativeDriver`. While each implementation of the event loop is different, your code should not depend on the particular loop implementation. 

## Conclusion
This was an introduction to event loop basics. We have started writing asynchronous code by scheduling some code. Event loop is a core of every asynchronous application. It registers events and when these events are fired it triggers appropriate handlers (callbacks). You may consider event loop as a task scheduler. When for example, we `delay()` some code, the event loop registers a timer watcher. When a timer is out (the event has happened) event loop dispatches an associated with this timer callback (our *delayed* code). Once there are no more registered events event loop is done and stops, the flow control returns back to a synchronous PHP script.


<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/learning-amphp/tree/master/event-loop){:target="_blank"}.
