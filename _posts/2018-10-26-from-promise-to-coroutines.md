---
title: "Managing Concurrency: From Promises to Coroutines"
tags: [PHP, Event-Driven Programming, Coroutines, Promises, Generators]
layout: post
description: "Different patterns of managing concurrency in php: from promises, generators, and coroutines"
image: "/assets/images/posts/managing-concurrency/simpsons.jpg"
---

<p class="text-center image">
    <img src="/assets/images/posts/managing-concurrency/simpsons.jpg">
</p>

What does concurrency mean? To put it simply, concurrency means the execution of multiple tasks over a period of time. PHP runs in a single thread, which means that at any given moment there is only one bit of PHP code that can be running. That may seem like a limitation, but it brings us a lot of freedom. We don't have to deal with all this complexity that comes with parallel programming and threaded environment. But at the same time, we have a different set of problems. We have to deal with concurrency. We have to manage and to coordinate it. For example, when we make concurrent HTTP requests we say that they *"are happening in parallel"*. Well, that's all fine and that's easy to do, the problems come when we have to sequence the responses. When one request needs information from another one. So, it is the coordination of concurrency that makes our job difficult. And we have a number of different ways to coordinate the concurrency.

>*Currently, PHP doesn't have high-level abstractions to manage concurrency and we have to use third-party libraries, such as [ReactPHP](https://reactphp.org){:target="_blank"} or [Amp](https://amphp.org){:target="_blank"}. In examples of this artice, I use ReactPHP promises.*

## Promises

To understand the idea behind promises we need an example from a daily life. Imagine that you are in McDonald's and you want to make an order. You pay some money for it thus you start a transaction. In a response to this transaction, you expect to get a hamburger and French fries. But the cashier doesn't return you the food. Instead, you receive a receipt with an order number on it. Consider this receipt as *a promise* for the future order. Now you can get this receipt and start thinking about your tasty hamburger and French fries. You don't have them yet so you stand and wait until your order is done. Once its number appears on the screen you exchange your receipt for your order. That is a promise:

>*a placeholder for a future value.*

Promise is a representation of a future value, a time-independent container that we wrap around the value. It doesn't matter if the value is here or not. We continue to reason about it the same way, regardless of whether it's here or not. Imagine that we have three concurrent HTTP requests running *"in parallel"*, so they will complete at the same time frame. But we want in some way to coordinate the responses. For example, we want to print these responses as soon as they come back but with one small constraint: don't print the second response until we receive the first one. I mean that if `$promise1` resolves we print it. But if `$promise2` comes back first, we don't print it yet, because `$promise1` hasn't come back. Consider it as we try to adapt these concurrent calls so they will look more performant to the user.

Well, how do we handle this task with promises? First of all, we need a function that returns a promise. We can collect three promises, and then compose them together. Here is some dummy code for it:

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
- `fakeResponse(string $url, callable $callback)` has a hardcoded response and resolves a specified callback with it.
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

In the snippet above we start with `$promise1`. Once it is completed we print its value. We don't care how much time does it take: less than a second, or an hour. As soon as it is done, we print its value. And then we wait for `$promise2`. And here we can have two scenarios:

- `$promise2` is already finished and we print its value.
- `$promise2` hasn't finished yet and wee keep waiting.

But because of chaining promises together we don't have to care about that detail, whether the promise is resolved or not. The promise is a time-independent wrapper and it hides these states from us.

This is how we manage concurrency with promises. And it looks great, the chain of promises is much better than a bunch of nested callbacks.

## Generators

In PHP generators provide language-level support for functions that can be paused and then resumed. Inside this generator everything stops, it's like a small blocking program. But outside of this program everything else continues running. That's the magic and power of generators. 

We can literally locally pause the generator to wait for some promise to finish. The key idea is to have promises and generators together. They hide the concurrency management from us, we just call `yield` when we want to pause a generator and that's it. Here is the same program but now we are putting promises and generators together:

{% highlight php %}
<?php

use Recoil\React\ReactKernel;

// ...

ReactKernel::start(function () {
    $promise1 = makeRequest('url1');
    $promise2 = makeRequest('url2');
    $promise3 = makeRequest('url3');

    var_dump(yield $promise1);
    var_dump(yield $promise2);
    var_dump(yield $promise3);
});
{% endhighlight %}

>*To run this code I use [recoilphp/recoil](https://github.com/recoilphp/recoil) library, which provides this `ReactKernel::start()` call. Recoil provides the glue that lets us use PHP generators to perform asynchronous operations.*

We are still making three requests *"in parallel"*, but now we sequence responses with `yield` keyword. And again we print results as each promise finishes but only once the previous one is done.

## Coroutines 

Coroutine is a way of splitting an operation or a process into chunks with some execution in each chunk. As a result, it turns out that instead of executing the whole operation an once (which will cause a noticeable application freeze), it will be done little by little, until the whole required volume of actions is completed.

Now, having interruptible and resumable generators, we can use them to write asynchronous code but in a more natural synchronous way with Promises. With PHP generators and promises, we can completely avoid writing callbacks. The idea is when you yield a promise the coroutine subscribes to it. The coroutine pauses and waits for a promise to be settled (resolve or fail). Once the promise is settled the coroutine continues. On successful resolution, the coroutine sends the resolution value back into the generator context using `Generator::send($value)`. If promise fails the coroutine throws an exception through the generator using `Generator::throw()`. Without callbacks, we can write asynchronous code almost like a synchronous one. 

### Sequential execution

With coroutines the order of execution matters. The code runs to the exact location of the `yield` keyword and then pauses until the promise resolves. Consider the following line of code:

{% highlight php %}
<?php

use Recoil\React\ReactKernel;

// ...

ReactKernel::start(function () {
    echo 'Response 1: ', yield makeRequest('url1'), PHP_EOL;
    echo 'Response 2: ', yield makeRequest('url2'), PHP_EOL;
    echo 'Response 3: ', yield makeRequest('url3'), PHP_EOL;
});
{% endhighlight %}

Here, the code will print `'Response 1: `, then it pauses and waits. Once the first promise from `makeRequest('url1')` is resolved we print its result and move to the next line.

### Errors handling

[Promises/A+](https://promisesaplus.com) standard for promises says that every promise has `then()` and `catch()` methods. This interface allows to chain promises and optionally catch errors. Consider this code:

{% highlight php %}
<?php

operation()->then(function ($result) {
    return anotherOperation($result);
})->then(function ($result) {
    return yetAnotherOperation($result);
})->then(function ($result) {
    echo $result;
});
{% endhighlight %}

Here we have a chain of promises passing the result of each promise into the next promise in the chain. But there is no `catch()` block, no error handling here. When a promise in the chain fails, the control jumps to the closest rejection handler down the chain. In our case, that means that the failed promise will be ignored, and any thrown exceptions will disappear into the void. With coroutines errors become first-class citizens. If any asynchronous operation fails the exception will be thrown:

{% highlight php %}
<?php

use Recoil\React\ReactKernel;
use React\Promise\RejectedPromise;

// ...

function failedOperation() {
    return new RejectedPromise(new RuntimeException('Something went wrong'));
}

ReactKernel::start(function () {
    try {
        yield failedOperation();
    } catch (Throwable $error) {
        echo $error->getMessage() . PHP_EOL;
    }
});
{% endhighlight %}

## Making asynchronous code readable

Generators have a really important side effect that we can use to manage concurrency, they solve the problem of async programming:  **asynchronous code is non-reasonable**. We can't reason about our code when we have to jump all over the place. But our brain is fundamentally very synchronous and single threaded. We plan our day very sequentially: do this then do that and so on. But the asynchronous code doesn't work the way our brain works. Even the simple chain of promises doesn't look very readable:

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

We have to mentally parse it to understand what is going on here. In this way, we need a different pattern to manage concurrency. And generators very briefly are a way to may asynchronous code look sequential and synchronous.

Promises and generators are putting the best of both worlds together, we have this asynchronous and performant code but it looks like synchronous, linear and sequential. Coroutines hide away asynchronicity. The asynchronicity becomes an implementation detail. And what we write and what we reason about, the flow control now looks very sequential and linear like our brain works. 

Talking about ReactPHP you can use [RecoilPHP](https://github.com/recoilphp) to rewrite promise chains into coroutines so they will start looking like a traditional synchronous code.
