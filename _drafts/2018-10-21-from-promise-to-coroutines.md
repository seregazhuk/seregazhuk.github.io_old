---
title: "Manage Concurrency: From Promises to Coroutines"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Different patterns of managing concurrency in php: from promises, generators, and coroutines"
---

What does concurrency mean? To put it simply, concurrency means the execution of multiple tasks over a period of time. PHP runs in a single thread, which means that at any given moment there is only one bit of PHP code that can be running. That may seem like a limitation, but it brings us a lot of freedom. We don't have to deal with all this complexity that comes with parallel programming and threaded environment. But at the same time we have a different set of problems. We have to deal with concurrency. We have to manage and to coordinate it. When we make concurrent requests we say that they *"are happening in parallel"*. Well that's all fine and that's easy to do, the problems comes when we have to sequence the responses. When one request need information from another one. So, it is the coordination of concurrency that makes our job difficult. And we have a number of different ways to coordinate the concurrency.

Promises. 
Promise is a representation of a future value, a time-independent container that we wrap around a value. It doesn't matter if the value is here or not. We continue to reason about the value the same way, regardless of whether it's here or not. Imagine that we have three concurrent HTTP requests running *"in parallel"*, so they will complete at the same time frame. But we want in some way to coordinate the responses. For example, we want to print these responses as soon as they come back but with one small constraint: don't print the second response until we receive the first one. I mean that if `$promise1` resolves we print it. But if `$promise2` comes back first, we don't print it yet, because `$promise1` hasn't come back. Consider it as we try to adapt these concurrent calls so they will look more performant to the user.

Well, how do we handle this task with promises? First of all we need a function that returns a promise. We can collect three promises, and then we can compose them together. Here is some dummy code for it:

{% highlight php %}
<?php
use React\Promise\Promise;

function fakeResponse(string $url, callable $callback) {
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

In the snippet above we start with `$promise1`. Once it is completed we print it


This is how we manage concurrency with promises.

Promises represent some-thing that may happen now or may happen in the future, but we can compose these things together as if they are all here right now:
