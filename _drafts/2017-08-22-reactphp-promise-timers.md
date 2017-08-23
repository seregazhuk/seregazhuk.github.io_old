---
title: "Cancelling ReactPHP Promises With Timers"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Cancelling asynchronous promises with timers with ReactPHP"
---
# The Problem

At first let's refresh in memory what is *Promise*. A promise represents a result of an asynchronous operation. You can add fulfillment and error handlers to a promise object and they will be envoked once this operation has completed or failed. Check [this article]({% post_url 2017-06-16-phpreact-promises %}) to learn more about promises.

Promise is a very powerful tool which allows us to pass around the code eventual results of some deffered operation. But there is one big probleme with promises: they dont give us much control.

> *Sometimes it may take too long for them to be resolved or rejected and we can't wait for it.*

To cancel a promise at first we need to go and create one. A promise can be created with the `React\Promise\Promise` class. Its constructor accepts two arguments: 

- `callable $resolver` - a handler being triggered emmidiately after creating a promise.
- `callable $canceller` - a hanlder being triggered when a promise is cancelled via `cancel()` method.

Both handlers accept `$resolve` and `$reject` arguments. `$resolve($value)` *fulfills* the promise with the `$value`, `$reject($reason)` simply *rejects* a promise.

{% highlight php %}
<?php

$resolve = function(callable $resolve, callable $reject) {
    return $resolve('Hello wolrd!');
};

$cancel = function(callable $resolve, callable $reject) {
    $reject(new \Exception('Promise cancelled!'));
};

$promise = new React\Promise\Promise($resolve, $cancel);
{% endhighlight %}

This is very trivial example. The promise above will be emmidiately resolved after creation. Not very useful. The simple timer can delay this resolving a bit. To run a timer we need to create an instance of the event loop and then `run()` it:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();

$resolve = function(callable $resolve, callable $reject) use ($loop) {
    $loop->addTimer(5, function() use ($resolve) {
        return $resolve('Hello wolrd!');
    });
};

$cancel = function(callable $resolve, callable $reject) {
    $reject(new \Exception('Promise cancelled!'));
};

$promise = new React\Promise\Promise($resolve, $cancel);
$loop->run();
{% endhighlight %}

Now the promise resolves only in 5 seconds. Exactly what we need. So, we can try to *cancel* this promise. For example, for some reason, we can't wait 5 seconds and if we havn't received the result in 2 seconds we don't care any more about this promise. How to handle this scenario?

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
