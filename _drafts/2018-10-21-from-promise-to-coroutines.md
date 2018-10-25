---
title: "Manage Concurrency: From Promises to Coroutines"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Different patterns of managing concurrency in php: from promises, generators, and coroutines"
---

What does concurrency mean? To put it simply, concurrency means the execution of multiple tasks over a period of time. PHP runs in a single thread, which means that at any given moment there is only one bit of PHP code that can be running. That may seem like a limitation, but it brings us a lot of freedom. We don't have to deal with all this complexity that comes with parallel programming and threaded environment. But at the same time we have a different set of problems. We have to deal with concurrency. We have to manage and to coordinate it. When we make concurrent requests we say that they *"are happening in parallel"*. Well that's all fine and that's easy to do, the problems comes when we have to sequence the responses. When one request need information from another one. So, it is the coordination of concurrency that makes our job difficult. And we have a number of different ways to coordinate the concurrency.

### Promises
Promise is a representation of a future value, a time-independent container that we wrap around a value. It doesn't matter if the value is here or not. We continue to reason about the value the same way, regardless of whether it's here or not. Imagine that we have three concurrent HTTP requests running *"in parallel"*, so they will complete at the same time frame. But we want in some way to coordinate the responses. For example, we want to print these responses as soon as they come back but with one small constraint: don't print the second response until we receive the first one. I mean that if `$promise1` resolves we print it. But if `$promise2` comes back first, we don't print it yet, because `$promise1` hasn't come back. Consider it as we try to adapt these concurrent calls so they will look more performant to the user.

Well, how do we handle this task with promises? First of all we need a function that returns a promise. We can collect three promises, and then we can compose them together. Here is some dummy code for it:

{% highlight php %}
<?php
use React\Promise\Promise;

function fakeResponse(string $url, callable $callback) {
    if ($url === 'url1') {
        sleep(1);
    }
    $callback("response for $url");
}

function makeRequest(string $url) {
    return new Promise(function(callable $resolve) use ($url) {
        fakeResponse($url, $resolve);
    });
}
{% endhighlight %}

I have two functions here:
- `fakeResponse(string $url, callable $callback)` has a hardcoded response and resolve a specified callback with it.
- `makeRequest(string $url)` returns a promise that uses `fakeResponse()` to signal that the request is completed.

Notice, that I've also put `sleep()` call, to simulate a delay of the first promise.

From the calling code we simply call `makeRequest()` function and receive back promises:

{% highlight php %}
<?php

$promise1 = makeRequest('url1');
$promise2 = makeRequest('url2');
$promise3 = makeRequest('url3');
{% endhighlight %}

It was easy, but now we need to somehow sequence these responses together. Once again, we want the second promise to be printed only once the first one is resolved. To handle that we can chain promises:

{% highlight php %}
<?php

$promise1
    ->then('var_dump')
    ->then(function() use ($promise2) {
        return $promise2;
    })
    ->then('var_dump')
    ->then(function () use ($promise3) {
        return $promise3;
    })
    ->then('var_dump')
    ->then(function () {
        echo 'Complete';
    });
{% endhighlight %}

In the snippet above we start with `$promise1`. Once it is completed we print its value. We don't care how much time does it take: less than a second, or an hour. As soon as it is done, we print its value. And then we wait for `$promise2`. And here we can have two scenarios:

- `$promise2` is already finished and we print its value.
- `$promise2` hasn't finished yet and wee keep waiting.

But because of chaining promises together we don't have to care about that detail, whether the promise is resolved or not. The promise is a time-independent wrapper and it hides these states from us.

This is how we manage concurrency with promises. And it looks great, the chain of promises is much better than a bunch of nested callbacks.

### Generators
Here is the same program but now we are putting promises and generators together:

{% highlight php %}
<?php

use Recoil\React\ReactKernel;

// ...

ReactKernel::start(
    function () {
        $promise1 = makeRequest('url1');
        $promise2 = makeRequest('url2');
        $promise3 = makeRequest('url3');

        var_dump(yield $promise1);
        var_dump(yield $promise2);
        var_dump(yield $promise3);
    }
);
{% endhighlight %}

We are still making three requests *"in parallel"*, but now we sequence responses with `yield` keyword. And again we print results as each promise finishes but only once the previous one is done.

>*To run this code I use [recoilphp/recoil](https://github.com/recoilphp/recoil) library, which provides this `ReactKernel::start()` call.*

Generator is a special function. We can literally locally pause this function to wait for some promise to finish. Inside this function everything stops, it's like a small blocking program. But outside of this program everything else continues running. That's the magic and power of generators. They hide the concurrency management from us, we just call `yield` when we want to pause a generator and that's it.

Generators have a really important side effect that we can use to manage concurrency, they solve the problem in async programming: 

>*asynchronous code is non-reasonable*

We can't reason about our code when we have to jump all over the place. But our brain is fundamentally very synchronous and single threaded. We plan our day very sequentially: do this, than do that and so on. But asynchronous code doesn't work the way our brain works. In this way we need a different pattern to manage concurrency. And generators very briefly are a way to may asynchronous code look sequential and synchronous. We are putting the best of both worlds together, we have this asynchronous and performant code but it looks like synchronous, linear and sequential.

The key idea is to have promises and generators together.
