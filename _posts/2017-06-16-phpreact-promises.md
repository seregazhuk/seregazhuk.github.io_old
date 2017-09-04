---
title: "Event-Driven PHP with ReactPHP: Promises"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Event-Driven PHP with ReactPHP: Promises"
---

# Promises

[ReactPHP Promise Component](https://github.com/reactphp/promise)

## The Basic Concepts

> *A promise represents a value that is not yet known while a deferred represents work that is not yet finished.*

A **promise** is a placeholder for the initially unknown result of the asynchronous code while a **deferred** represents the code which is going to be executed to receive this result. Every deferred has its own promise which works as a proxy for the future result. While a promise is a result returned by some asynchronous code, a deferred can be resolved or rejected by it's caller, so we can separate the promise from the resolver.

Create a deferred object:

{% highlight php %}
<?php
$deferred = new React\Promise\Deferred();
{% endhighlight %}

A promise for this deferred can be retrieved with `promise()` method, which returns an instance of the `React\Promise\Promise` class:

{% highlight php %}
<?php

$deferred = new React\Promise\Deferred();
$promise = $deferred->promise();

{% endhighlight %}

A promise has three possible states:

- *unfulfilled* - the promise starts with this state because the value of the deferred is yet unknown
- *fulfilled* - the promise is filled with the value returned from the deferred
- *failed* - there was an exception during the deferred execution.

A deferred object has two methods to change the state of its promise:

- `resolve($value = null)` when the code executes successfully, changes the state to *fulfilled*
- `reject($reason = null)` the code execution fails, changes the state to *failed*

Promises provide methods only to attach additional handlers to the appropriate states (`then`, `done`, `otherwise` and `always`), but you cannot manually change the state of a promise. For example, we can attach *onFulfilled* handler via `done()` method and then call it once the deferred is resolved:

{% highlight php %}
<?php

$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->done(function($data){
    echo 'Done: ' . $data . PHP_EOL;
});

$deferred->resolve('hello world');
{% endhighlight %}

To handle the *failed* state we can use `otherwise(callable $onRejected)` method to add a handler to a promise and `reject($reason = null)` method of the deferred object:

{% highlight php %}
<?php 

$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->otherwise(function($data){
    echo 'Fail: ' . $data . PHP_EOL;
});

$deferred->reject('no results');
{% endhighlight %}

**Notice** that we can use `done()` method to add all three handlers to the promise. For example, the previous example can be rewritten with `done()` method instead of `otherwise()`:

{% highlight php %}
<?php

$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->done(
    function($data){
        echo 'Done: ' . $data . PHP_EOL;
    },
    function($data){
        echo 'Reject: ' . $data . PHP_EOL;
    });

$deferred->reject('hello world');
{% endhighlight %}

Here we register handlers for both *fulfilled* and *failed* states.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/promises.jpg" alt="cgn-edit" class="">
</p>


A promise can change its state from *unfulfilled* to either *fulfilled* or *failed* but not vice versa. After resolution or rejection, all observers are notified. Once the promise has been resolved or rejected it cannot change its state or the result value.

We can give a promise to any number of consumers and each of them will observe the resolution of the promise independently. A deferred can be given to any number of producers and the promise will be resolved by the one which first resolves it.

## Promises Forwarding

Promises can be chained, when the return value of each promise is forwarded to the next promise in the chain. That means that the next promise in the chain will be invoked with this resolved value. 

Forwarding can be done with two methods:

- `then(callable $onFulfilled = null, callable $onRejected = null)` for resolution forwarding
- `otherwise(callable $onRejected)` for rejection forwarding

### Resolution Forwarding

Method `then()` registers new *fulfilled* and *rejection* handlers and returns a new promise. This promise will fulfill with the return value of either `$onFulfilled` or `$onRejected`, whichever is called or will reject with the thrown exception.

We can build a *pipe* of promises when each call to `then()` returns a new promise that will resolve with the return value of the previous handler:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();

$deferred->promise()
    ->then(function($data){
        // receives initial `hello` passed to $deferred->resolve()
        echo $data . PHP_EOL; 
        return $data . ' world';
    })
    ->then(function($data){
        // receives `hello world` returned from the previous promise
        echo $data . PHP_EOL;
        return strtoupper($data);
    })
    ->then(function($data){
        // receives `HELLO WORLD` returned from the previous promise
        echo $data . PHP_EOL;
    });

$deferred->resolve('hello');
{% endhighlight %}

The result of this code will be following:

{% highlight bash %}
$php resolution-forwarding.php
hello
hello world
HELLO WORLD
{% endhighlight %}

In each promise, `$onFulfilled` handler outputs the received value, changes it and then passes it to the next promise in the chain.

### Rejection Forwarding

Method `otherwise(callable $onRejected)` registeres a rejection handler for promise. Under the hood, this method is simply a shortcut for:

{% highlight php %}
<?php

$promise->then(null, $onRejected);
{% endhighlight %}

Rejected promises work like chained `try/catch` blocks. When you catch an exception, you must rethrow for it to the next promise:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();

$deferred->promise()
    ->otherwise(function($data){
        echo $data . PHP_EOL;

        throw new Exception('some ' . $data);
    })
    ->otherwise(function(\Exception $e){
        $message = $e->getMessage();
        echo $message . PHP_EOL;

        throw new Exception(strtoupper($message));
    })
    ->otherwise(function(\Exception $e){
        echo $e->getMessage() . PHP_EOL;
    });

$deferred->reject('error');
{% endhighlight %}

This example looks very similar to the previous one. But now we are throwing exceptions instead of returning the values. Notice that the first handler receives `mixed $data` from the `$deferred->reject()` method and not the exception. The output will be the following:

{% highlight bash %}
$php rejection-forwarding.php 
error
some error
SOME ERROR

{% endhighlight %}

Additionally, you can type hint the `$reason` argument of `$onRejected` handler to catch only specific errors:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();

$deferred->promise()
    ->otherwise(function($data){
        echo $data . PHP_EOL;

        throw new InvalidArgumentException('some ' . $data);
    })
    ->otherwise(function(InvalidArgumentException $e){
        $message = $e->getMessage();
        echo $message . PHP_EOL;

        throw new BadFunctionCallException(strtoupper($message));
    })
    ->otherwise(function(InvalidArgumentException $e){   // <-- This handler will be skipped
        echo $e->getMessage() . PHP_EOL;                 // because in the previous promise
    });                                                  // we have thrown a LogicException

$deferred->reject('error');
{% endhighlight %}

In this snippet the third handler will be skipped:

{% highlight bash %}
$php php rejection-forwarding-typehints.php
error
some error
{% endhighlight %}


### Mixed Forwarding

You can also mix resolution and rejection forwardings like this:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();

$deferred->promise()
    ->then(function($data){
        echo $data . PHP_EOL;
        return $data . ' world';
    })
    ->then(function($data){
        throw new Exception('error: ' . $data);
    })
    ->otherwise(function(Exception $e){
        return $e->getMessage();
    })
    ->then(function($data){
        echo $data . PHP_EOL;
    });

$deferred->resolve('hello');
{% endhighlight %}

The code above outputs the following:

{% highlight bash %}
$php mixed-forwarding.php
hello
error: hello world
{% endhighlight %}

### Then vs Done

The rule of thumb is:

> *Either return your promise or call `done()` on it.*

At a first glance, both `then()` and `done()` look very similar, but there is a significant difference between them.

Method `then()` transofrms a promise's value and returns a new promise for this transformed value. So, we can chain `then()` calls. This method also allows to recover from or propagate intermediate errors. Any errors that are not handled will be caught by the promise and used to reject the promise returned by `then()`.

Method `done()` consumes the promise's value or handles the error. `done()` always returns `null`. When we call `done()`  all responsibility for errors lies on us. Any error (either a thrown exception or returned rejection) in the `$onFulfilled` or `$onRejected` handlers will be rethrown in an uncatchable way causing a fatal error:

{% highlight php %}
<?php

$deferred = new React\Promise\Deferred();

$promise = $deferred->promise();
$promise->done(function($data){
    throw new Exception('error'); // <-- PHP Fatal error:  Uncaught Exception
});

$deferred->resolve('no results');
{% endhighlight %}

## Conclusion

The promise itself doesn't make your code execution asynchronous. A promise is a placeholder for a result which is initially unknown while a deferred represents the computation that results in the value. A deferred can be resolved or rejected by the caller, so the promise is separated from the resolver. With promises you can write your asynchronous code in a synchronous way to make it more readable, this means that instead of using callbacks we can return a value (promise).

<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/promises).

<strong>Other ReactPHP articles:</strong>

- [Event loop and timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Streams]({% post_url 2017-06-12-phpreact-streams %})
- [Chat on sockets: server]({% post_url 2017-06-22-reactphp-chat-server %}) and  [client]({% post_url 2017-06-24-reactphp-chat-client %})
- [UDP chat]({% post_url 2017-07-05-reactphp-udp %})
- [Video streaming server]({% post_url 2017-07-17-reatcphp-http-server %})
- [Parallel downloads with async http requests]({% post_url 2017-07-26-reactphp-http-client %})
- [Managing Child Processes]({% post_url 2017-08-07-reactphp-child-process %})
- [Cancelling Promises With Timers]({% post_url 2017-08-22-reactphp-promise-timers %})
- [Resolving DNS Asynchronously]({% post_url 2017-09-03-reactphp-dns %})
