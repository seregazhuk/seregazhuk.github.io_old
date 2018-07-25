---
title: "Amp Promises: From Generators To Coroutines"
tags: [PHP, AsyncPHP, Amp, Event-Driven Programming]
description: "Amp promises: coroutines and generators in asynchronous PHP."
image: "/assets/images/posts/amp-promises/logo.jpg" 
---

## Generators

Generators become available in PHP since version 5.5.0. The main idea is to provide a simple way to create iterators but without creating a class that implements [Iterator](http://php.net/manual/en/class.iterator.php){:target="_blank"} interface. A generator function looks like a normal function, except that instead of returning the result once, a generator can `yield` as many times as it needs to in order to provide the values to be iterated over.

A simple example can be reading a file into an array of lines (reimplementing native PHP `file()` function):

{% highlight php %}
<?php

function getLinesFromFile($fileName) {
    if (!$fileHandle = fopen($fileName, 'r')) {
        return;
    }
 
    while (false !== $line = fgets($fileHandle)) {
        yield $line;
    }
 
    fclose($fileHandle);
}
 
$lines = getLinesFromFile($fileName);
foreach ($lines as $line) {
    // do something with $line
}
{% endhighlight %}

In this function, every new line from the file is `yield`ed up to the calling code. So, you can consider `getLinesFromFile()` function as *interruptible*, because generators work by passing control back and forth between the generator and the calling code. 

### Sending values into generator

One more interesting thing about generators is that the calling code can `send()` some data into a generator. `send($value)` will set `$value` as the return value of the current `yield` expression and resume the generator. 

>*Values are always sent by-value. The reference modifier `&` only affects yielded values, not the ones sent back to the coroutine.*

Maybe all of this sounds a bit confusing, so we definitely need an example here. Let's create *echo*-function that yields a value that was sent to it. And then try to `send()` some value:

{% highlight php %}
<?php

$echoLogger = function () {
    echo 'Log: ' . yield . "\n";
};

$logger->send('Hello ');
$logger->send('world'); 
{% endhighlight %}


The result of execution of this script doesn't look nice. We have `PHP Fatal error:  Uncaught Error: Call to undefined method Closure::send()`. Why? Because placing a `yield` expression doesn't immediately convert your function into generator. Actually `yield` expression itself when being executed returns a generator. So, let's fix it. Instead of anonymous function we can create a named function then execute it and assign the returned value to `$echoLogger`. Or we can add `call_user_func()` which immediately invokes a specified function:

{% highlight php %}
<?php

/** @var Generator $logger */
$logger = call_user_func(function() {
    echo 'Log: ' . yield . "\n";
});

$logger->send('Hello ');
$logger->send('world');
{% endhighlight %}

Now, it works... but still not as we expect:

{% highlight bash %}
Log: Hello 
{% endhighlight %}

It logs only the first `Hello` string. But the second `send()` call doesn't work. Why? Because there is no code after `yield` expression in our `$logger` function. And a generator is closed. To fix it we can add the second `echo 'Log: ' . yield . "\n"` or wrap it into the endless `while` loop:

{% highlight php %}
<?php

/** @var Generator $logger */
$logger = call_user_func(function() {
    while(true) {
        echo 'Log: ' . yield . "\n";
    }
});

$logger->send('Hello ');
$logger->send('world');
{% endhighlight %}

Now, this generator accepts as many `send()` calls as we provide to it:

{% highlight bash %}
Log: Hello 
Log: world
{% endhighlight %}


### Throwing into generator

Generators themselves can handle exceptions from the calling code. Besides the data, we can also *send* an exception into a generator via `throw()` method. Calling `throw()` method on a generator is equivalent to replacing `yield` expression with a `throw` statement and resuming a generator. In this case, an exception is thrown in the generator's execution context. Again it sounds too complicated and requires an example. We can continue with our `echo` generator from the previous section. But now we wrap `yield` expression into `try/catch` block:

{% highlight php %}
<?php

/** @var Generator $logger */
$logger = call_user_func(function() {
    while(true) {
        try {
            echo 'Log: ' . yield . "\n";
        } catch (Exception $e) {
            echo 'Caught exception ' . $e->getMessage();
        }
    }
});

$logger->send('Hello world');
$logger->throw(new Exception('something wrong'));
{% endhighlight %}


When executing this script and exception is thrown through the generator. It works! The output is the following:

{% highlight bash %}
Log: Hello world
Caught exception: something wrong
{% endhighlight %}


One thing should be noticed here. Try to remove `while` loop and run the script. It will fail with `Uncaught Exception`. This happens because when a generator is closed the exception will be thrown in the calling code context, which is equivalent to replacing the `throw()` call with a `throw` statement. And this is exactly what we have. After `$logger->send('Hello world')` there is no more code in generator and it closes.

So, let's wrap up what we know about generators:

- interruptible/resumable functions
- use `yield` rather than (or in addition to) `return`
- values/exceptions can be send/thrown into
- behave like iterator

All these generator features are used in [Amp](https://amphp.org){:target="_blank"} to implement coroutines.

## What Is Coroutine?

Coroutine is a way of splitting an operation or a process into chunks with even execution in each chunk. As a result, it turns out that instead of executing the whole operation an once (which will cause a noticeable application freeze), it will be done little by little, until the whole required volume of actions is completed. 

In the example above with `getLinesFromFile()` instead of reading the whole file into memory and return a complete array, we split this task into chunks. Every time a new line from the file is being read we return the control flow up to the calling code, allowing other tasks to be run, such as I/O handlers, timers, or other coroutines. Then the client code decides itself when to continue with coroutine to receive a new line from a file.

Now, having interruptible and resumable functions, we can use them to write asynchronous code but in a more natural synchronous way with Promises. 

## Promises

When dealing with asynchronous code we can't wait for the result of some operation. The event loop can't be blocked. But what if this result is required for the next operation to be executed? How can we handle this situation? The answer - is Promise. Consider promise as an object that represents the eventual result of an asynchronous operation. It is a sort of placeholder for this result. So, instead of waiting for some operation to be completed, we get a promise from it and continue the loop. Other tasks that depend on the result of another operation get a promise from it and deal with it.

The promise (as any eventual result) can be in three states:
- Success (operation succeed and returned the result)
- Failure (operation failed)
- Pending (operation is still in process)

The traditional NodeJs approach for dealing with asynchronous code was - callbacks. When we have an asynchronous task we always provide a callback to handle the result (or failure):

{% highlight js %}
function isUserBanned(id, callback) {
  openDatabase(function(db) {
    getCollection(db, 'users', function(col) {
      find(col, {'id': id},function(result) {
        result.filter(function(user) {
          callback(user.is_banned)
        })
      })
    })
  })
}
{% endhighlight %}

Callbacks and promises are not fundamentally different. Everything you can do using callbacks you can do with promises, but in a more readable and nicer way. 

Now with PHP generators and promises we completely can avoid writing callbacks. The idea is when you `yield` a promise the coroutine subscribes to it. The coroutine pauses and waits for promise to be settled (resolve or fail). Once the promise is settled the coroutine continues. On successful resolution, the coroutine sends the resolution value back into the generator context using `Generator::send($value)`. If promise fails the coroutine throws an exception through the generator  using `Generator::throw()`. Without callbacks, we can write asynchronous code almost like a synchronous one. 

This is how it works:

1. The promise is `yield`ed from the generator
2. Coroutine subscribes to this promise
2. Event loop skips coroutine until the promise is resolved
4. Coroutine `send()`s promise result into generator
5. Repeat from 1 until coroutine is complete (return)

## Deferred
`Deferred` is an object that is responsible for resolving a promise. Each deferred object has a promise it is responsible for. Deferred can resolve or fail the promise. Asynchronous components communicate with each other via promises. When one component is asked for some value, it creates an `Amp\Deferred` object and returns its promise. When the component is ready with the asked value it resolves its deferred, which in turn resolves its promise. This way of communication between components allow separate a creator of promise from its consumer, so only a creator can resolve or fail the promise. Consider promise as a placeholder for the initially unknown result of the asynchronous code, while a deferred represents the code which is going to be executed to receive this result. 

Consider this resolver's code:

{% highlight php %}
<?php
$deferred = new Amp\Deferred;
return $deferred->promise();
{% endhighlight %}


Nothing really special here, we create a deferred object and return its promise.

>*Under the hood when you call `$deferred->promise()` it returns an object of the anonymous class that implements `Amp\Promise`.*

What should a consumer do with this promise? Well, `Amp\Promise` interface is really simple and consists of one `onResolve(callable $onResolve)` method. This method allows to register a handler for a promise. This handler is a callback with two arguments. The first one is an error, which will be set with an exception if the promise fails. The second one is a resolution value if the promise succeeds. Consumer's code:

{% highlight php %}
<?php

$promise->onResolve(function (Throwable $error = null, $result = null) {
    if ($error) {
      echo 'Something went wrong: ' . $error->getMessage();
      return;
    }

  echo 'Promise was resolved with: ' . $result;
});
{% endhighlight %}

Now if the resolver calls `$deferred->resolve('my value')` a resolution handler will be triggered and you will receive `Promise was resolved with: my value` message. But if the resolver calls `$deferred->fail(new Exception('some error'))`, the same handler will be triggered but now its `$error` argument will contain an instance of `Exception` with `some error` message.

You can add ass many consumers as you want. When the promise is resolves or fails all registered `onResolve` callbacks are triggered:

{% highlight php %}
<?php

// first consumer
$promise->onResolve(function (Throwable $error = null, $result = null) {
  // ...
});


// second consumer
$promise->onResolve(function (Throwable $error = null, $result = null) {
  // ... 
});
{% endhighlight %}


## Difference With Promises/A+
JavaScript has an open standard for promises called [Promises/A+](https://promisesaplus.com). The idea is that every promise has `then()` and `catch()` methods, so you can chain promises and optionally catch an error. Amp’s Promise interface does not provide `then()` nor `catch` methods. Amp consider promise callbacks as *error-first*.  Why? Consider this JavaScript example:

```javascript
function isUserBanned(id) {
  return openDatabase()
    .then(getCollection)
    .then(find.bind(null, {'id': id}))
    .then(function(user) {
      return !user.is_banned;
    });
}
```

Do you see error handling here? Yes, we don't have it here. When a promise in the chain fails, the control jumps to the closest rejection handler down the chain. And what happens if we simply forget to add `catch` block? The failed promise will be ignored, and any thrown exceptions will disappear into the void. In Amp, we know that every asynchronous call can fail, so error handling should be a first-class citizen, not something which can be added as an optionally chained callback.

<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/learning-amphp/tree/master/promise){:target="_blank"}.
