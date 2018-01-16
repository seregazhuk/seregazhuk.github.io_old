---
title: "Managing ReactPHP Promises"
tags: [PHP, Promises]
description: "Managing ReactPHP Promises in PHP"
---

Asynchronous application is always a composition of independently executing things. In concurrency we are **dealing** with a lot of different things at once. Use can compare it with I\O driver in your OS (mouse, keyboard, display). They all are managed by the operating system, but each of them is an independent thing inside the kernel. So, to make concurrency work you have to create a communication between these independent parts to coordinate them. And here come promises. They are the basic unit of concurrency in an asynchronous applications. They are the blood of the asynchronous application and move the results between different tasks across the code. Sometimes it may be one single promise passed around between components, but you definitely will face the situation when you have to deal with several promises at once. Let's look at a few such examples.

## I don't know exactly what the resolver will give me

We have some code that expects a promise but we are not sure about the value we have received: if it is a promise or not. If not, we need somehow convert it to a promise:

`$promise = React\Promise\resolve(mixed $promiseOrValue);`

This function accepts both promise and simple value. In situation when it receives a promise it simply returns this promise as it is:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();
$promise = React\Promise\resolve($deferred->promise());

var_dump($deferred->promise() === $promise); // true
{% endhighlight %}

When receiving a value `React\Promise\resolve` returns a promise which resolves with this value:

{% highlight php %}
<?php

$promise = React\Promise\resolve($value = 'my-value');
$promise->then(function ($value) {
    echo $value . PHP_EOL;
});
{% endhighlight %}


In the snippet above we create a promise that resolves with `my-value` string.

## I want to reject a promise but without throwing an exception

We can immediately return a result for consumer, but it expects promise for us. Also, according to the consumer's request there may be results, may be not. But we can determine them at once, without any asynchronous code. Sounds very abstract? Ok, consider a cache. But a very simple in-memory cache, but it's clients expect a promise from it:

{% highlight php %}
<?php

class ArrayCache implements CacheInterface
{
    private $data = array();

    public function get($key)
    {
        if (!isset($this->data[$key])) {
            return Promise\reject();
        }

        return Promise\resolve($this->data[$key]);
    }
}
{% endhighlight %}

It's a perfect example for this use-case. Without any asynchronous code we can determine the result, but we need to convert it to a promise. So, if there is a value in the cache we create a promise that resolves with this value, using `Promise\resolve`. If not we create a rejected promise with `Promise\reject()` call. We can optionally specify a rejection reason: 

{% highlight php %}
<?php

public function get($key)
{
    if (!isset($this->data[$key])) {
        return Promise\reject(new Exception("Value with key $key not found"));
    }

    return Promise\resolve($this->data[$key]);
}
{% endhighlight %}

`Promise\reject()` function also accepts a promise. If it receives a promise its resolution value will be the rejection reason of the returned promise:

{% highlight php %}
<?php

$deferred = new \React\Promise\Deferred();
$deferred->resolve('my-value');

$promise = React\Promise\reject($deferred->promise());
$promise->then(null, function($reason){
    echo 'Promise was rejected with: ' . $reason . PHP_EOL;
});
{% endhighlight %}


## I want to run multiple tasks that don’t depend on each other and when they all finish do something else

So in other words you have a set of pending (but not necessarily) promises and want to do something when all of them become resolved:

`$promise = React\Promise\all(array $promisesOrValues);`

We can call all our resolvers, collect promises from them and them pass this set of promises to `Promise\all(array $promisesOrValues)`. The resulting promise resolves once all the items in `$promisesOrValues` array are resolved:

{% highlight php %}
<?php

$firstResolver = new \React\Promise\Deferred();
$secondResolver = new \React\Promise\Deferred();

$pending = [
    $firstResolver->promise(),
    $secondResolver->promise()
];

$promise = \React\Promise\all($pending)->then(function($resolved){
    print_r($resolved); // [10, 20]
});

$firstResolver->resolve(10);
$secondResolver->resolve(20);
{% endhighlight %}

## I have some pending tasks and ...

### I have some pending tasks and want to continue once the first one is done
In this use-case we don't care if the task was successfully finished or failed. Once we receive the first result we continue with it. In this case we can use `Promise\race(array $promisesOrValues)` function. It accepts a set of promises and initiates a competitive race that allows only one winner among them. The resulting promise resolves in the same way the winner promise settles. This is the setup code, where we collect promises and mix them into a new resulting promise:

{% highlight php %}
<?php

$firstResolver = new \React\Promise\Deferred();
$secondResolver = new \React\Promise\Deferred();

$pending = [
    $firstResolver->promise(),
    $secondResolver->promise()
];

$promise = \React\Promise\race($pending)
    ->then(function($resolved){
        echo 'Resolved with: ' $resolved . PHP_EOL; 
    }, function($reason) {
        echo 'Failed with: '. $reason . PHP_EOL;
    });
{% endhighlight %}

Now, when the first (quickest) pending promise resolves, our promise also resolves with the same resolution value:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$loop->addTimer(2, function() use ($firstResolver){
    $firstResolver->resolve(10);
});
$loop->addTimer(1, function () use ($secondResolver) {
    $secondResolver->resolve(20);
});
$loop->run();
{% endhighlight %}

This code will echo `Resolved with: 20`. 

{% highlight php %}
<?php
$loop = \React\EventLoop\Factory::create();
$loop->addTimer(2, function() use ($firstResolver){
    $firstResolver->resolve(10);
});
$loop->addTimer(1, function () use ($secondResolver) {
    $secondResolver->reject(20);
});
{% endhighlight %}

But int the snippet above our resulting promise rejects: `'Failed with: 20'`

## I have some pending tasks and want to continue once the first one is successfully finished
We have a replicated data storage and want to minimize latency by asking all the nodes and return the first result arrive. In this case we need to use `Promise\race(array $promisesOrValues)` function.
