---
title: "Event-Driven PHP with ReactPHP: Promises"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
---

# Promises

[ReactPHP Promise Component](https://github.com/reactphp/promise)

## The Basics

> *A promise represents a value that is not yet known while a deferred represents work that is not yet finished.*

A promise is a value that is not yet known
A deffered is a work that is not yet finished

A **promise** a placeholder for the initially unknown result of the asynchronous code while a **deffered** represents the code which is going to be executed to recieve this result. Every deffered has its own promise which works as a proxy for the future result. While a promise is a result returned by some asynchronous code, a deffered can be resolved or rejected by it's caller, so we can separate the promise from the resolver.

Create a deffered object:

{% highlight php %}
<?php
$deferred = new React\Promise\Deferred();
{% endhighlight %}

Deffered object has three main methods. Each one changes the state of the deffered object's promise.

- `resolve($value = null)` when the code executes successfully
- `reject($reason = null)` the code execution fails
- `notify($update = null)` to get the current progress

A promise for this deffered can be retreived with `promise()` method, which returns an instance of the `React\Promise\Promise` class:

{% highlight php %}
<?php

$deferred = new React\Promise\Deferred();
$promise = $deffered->promise();

{% endhighlight %}

The promise has three possible states:

- *unfulfilled* - the promise starts with this state, because the value of the deffered is yet unknown
- *fulfilled* - the promise is filled with the value returned from the deffered
- *failed* - the was an exception during the deffered execution.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/promises.jpg" alt="cgn-edit" class="">
</p>

Promises provide methods only to attach additional handlers for the appropriate states (`then`, `done`, `otherwise`, `always` and `progress`), but you cannot manually change the state of a promise. For example, we can attach *done* handler and then call it once the deffered is resolved:

{% highlight php %}
<?php

$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->done(function($data){
    echo 'Done: ' . $data . PHP_EOL;
});

$deferred->resolve('hello world');
{% endhighlight %}

To handle the *failed* state we can use `otherwise(callable $onRejected)` method to add a handler to a promise and `reject($reason = null)` method of the deffered object:

{% highlight php %}
<?php 

$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->otherwise(function($data){
    echo 'Fail: ' . $data . PHP_EOL;
});

$deferred->reject('no results');
{% endhighlight %}

A promise can change it's state from *unfulfilled* to either *fulfilled* or *failed* but not vice versa. After resolution or rejection all observes are notified. Once the promise has been resolved or rejected it cannot change it's state or the result value.

We can give a promise to any number of consumers and each of them will observe the resolution of the promise independently. A deffered can be given to any number of producers and the promise will be resolved by the one which first resolves it.

We can keep track of the progress of the deffered calling `notify($update = null)` method on it and attaching a handler to the promise with the `progress(callable $onProgress)`. The code below uses event loop to asynchronously update the progress:

{% highlight php %}
<?php

use React\EventLoop\Timer\TimerInterface;

$loop = React\EventLoop\Factory::create();
$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->progress(function($data){
    echo 'Progress: ' . $data . PHP_EOL;
});
$promise->done(function($data){
    echo 'Done: ' . $data . PHP_EOL;
});

$progress = 1;
$loop->addPeriodicTimer(1, function(TimerInterface $timer) use ($deferred, &$progress){
    $deferred->notify($progress++);

    if($progress > 10) {
        $timer->cancel();
        $deferred->resolve('Finished');
    }
});

$loop->run();
{% endhighlight %}

We add a periodic timer to change the progress of the deffered every second. When the progress is grater then 10 we stop the timer and resolve the deffered.

<p>
    <img src="/assets/images/posts/reactphp/promise-progress.gif" alt="cgn-edit" class="">
</p>

## Conclusion

The promise itself doesn't make your code execution asynchronous. It simply offers the ability to run your code when, then is runs some callback based on success or failure. Another words promises are the placeholders for the results of the asynchronous operations. 