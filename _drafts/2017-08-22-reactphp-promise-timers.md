---
title: "Cancelling ReactPHP Promises With Timers"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Cancelling asynchronous promises with timers with ReactPHP"
---
# The Problem

At first, let's refresh in memory what is *Promise*. A promise represents a result of an asynchronous operation. You can add fulfillment and error handlers to a promise object and they will be invoked once this operation has completed or failed. Check [this article]({% post_url 2017-06-16-phpreact-promises %}) to learn more about promises.

Promise is a very powerful tool which allows us to pass around the code eventual results of some deferred operation. But there is one big problem with promises: they don't give us much control.

> *Sometimes it may take too long for them to be resolved or rejected and we can't wait for it.*

To cancel a promise at first we need to go and create one. A promise can be created with the `React\Promise\Promise` class. Its constructor accepts two arguments: 

- `callable $resolver` - a handler being triggered immediately after creating a promise.
- `callable $canceller` - a handler being triggered when a promise is cancelled via `cancel()` method.

Both handlers accept `$resolve` and `$reject` arguments. `$resolve($value)` *fulfills* the promise with the `$value`, `$reject($reason)` simply *rejects* a promise.

{% highlight php %}
<?php

$resolve = function(callable $resolve, callable $reject) {
    return $resolve('Hello world!');
};

$cancel = function(callable $resolve, callable $reject) {
    $reject(new \Exception('Promise cancelled!'));
};

$promise = new React\Promise\Promise($resolve, $cancel);
{% endhighlight %}

This is a very trivial example. The promise above will be immediately resolved after creation. Not very useful. The simple timer can delay this resolving a bit. To run a timer we need to create an instance of the event loop and then `run()` it:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$resolve = function(callable $resolve, callable $reject) use ($loop) {
    $loop->addTimer(5, function() use ($resolve) {
        return $resolve('Hello world!');
    });
};

$cancel = function(callable $resolve, callable $reject) {
    $reject(new \Exception('Promise cancelled!'));
};

$promise = new React\Promise\Promise($resolve, $cancel);
$loop->run();
{% endhighlight %}

Now the promise resolves only in 5 seconds. Exactly what we need. So, we can try to *cancel* this promise. For example, for some reason, we can't wait 5 seconds and if we haven't received the result in 2 seconds we don't care anymore about this promise. How to handle this scenario?

## PromiseTimer

[PromiseTimer](http://reactphp.org/promise-timer/) is a nice component which provides timeouts implementation for promises. To set a timer for the promise there is a simple `\React\Promise\Timer\timeout()` function.

Function `timeout(PromiseInterface $promise, $time, LoopInterface $loop)` accepts three arguments:

- a `$promise` to be cancelled after timeout.
- `$time` to wait for a promise to be resolved or rejected.
- an instance of the event `$loop`.

The function itself returns a new promise (a wrapper over the the input promise). The relations between the principal promise and its wrapper are the following:

- When the principal promise resolves *before* the specified `$time`, the wrapper-promise also resolves with this fulfillment value. 
- If the principal promise rejects *before* the specified `$time` the wrapper also rejects with the same rejection value.
- And if the principal promise doesn't settle *before* the specified `$time` it will be *cancelled* and the wrapper promise is being rejected with the `TimeoutException`.

Knowing all of this we can try to cancel the promise from the previous example:

{% highlight php %}
<?php

use function \React\Promise\Timer\timeout;

// ... 

timeout($promise, 2, $loop);
{% endhighlight %}

Let's add some debug messages to figure out what is happening with our promise when it is cancelled:

{% highlight php %}
<?php

use function \React\Promise\Timer\timeout;

$loop = React\EventLoop\Factory::create();

$resolve = function(callable $resolve, callable $reject) use ($loop, &$timer) {
    $timer = $loop->addTimer(5, function() use ($resolve) {
        return $resolve('Hello world!');
    });
};

$cancel = function(callable $resolve, callable $reject) use (&$timer) {
    echo "cancelled\n";
};

$promise = new React\Promise\Promise($resolve, $cancel);

timeout($promise, 2, $loop);

$loop->run();
{% endhighlight %} 

Run this script and see what happens:

<p class="">
    <img src="/assets/images/posts/reactphp/promise-timer-simple.gif" alt="promise-timer-simple" class="">
</p>

The *cancel* handler has been triggered in 2 seconds as we expected, but it looks like the loop continues to run and stops only after 5 seconds. Seems like the timer from the *resolve* handler is still working, although we have cancelled the promise. Let's add debug message to the timer and run the script to check this:

{% highlight php %}
<?php

use function \React\Promise\Timer\timeout;

$loop = React\EventLoop\Factory::create();

$resolve = function(callable $resolve, callable $reject) use ($loop, &$timer) {
    $timer = $loop->addTimer(5, function() use ($resolve) {
        echo "resolved\n";
    });
};

$cancel = function(callable $resolve, callable $reject) use (&$timer) {
    echo "cancelled\n";
};

$promise = new React\Promise\Promise($resolve, $cancel);

timeout($promise, 2, $loop);

$loop->run();
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/promise-timer-simple-loop-resolved.gif" alt="promise-timer-simple-loop-resolved" class="">
</p>

The guess turned out to be right. But we have cancelled the promise, why is the timer still working? Here things come a bit tricky. 

The **cancellation** of the promise means that the `cancel()` method is being called on the promise. Dot. `timeout` function has no idea what is happening inside your promise, so it is your job to handle the cancellation of the promise. You should manually close opened resources like sockets or files, terminate processes and cancel timers. In our case, this means that we should manually `cancel()` the timer in the *cancel* handler. To use the timer in different handlers we can `use` it in these handlers by reference:

{% highlight php %}
<?php

use function \React\Promise\Timer\timeout;

$loop = React\EventLoop\Factory::create();

$resolve = function(callable $resolve, callable $reject) use ($loop, &$timer) {
    $timer = $loop->addTimer(5, function() use ($resolve) {
        echo 'resolved';
    });
};

$cancel = function(callable $resolve, callable $reject) use (&$timer) {
    echo 'cancelled';
    $timer->cancel();
};

$promise = new React\Promise\Promise($resolve, $cancel);

timeout($promise, 2, $loop);

$loop->run();
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/promise-timer-simple-timer-cancel.gif" alt="promise-timer-simple-timer-cancel" class="">
</p>

Now the timer is cancelled and so the promise is *truly* cancelled. The rule of thumb is:

>*The promise itself when being cancelled is responsible for cleaning up any resources like open network sockets or file handles or terminating external processes or timers. Otherwise, this promise can still be pending and continue consuming resources.*


