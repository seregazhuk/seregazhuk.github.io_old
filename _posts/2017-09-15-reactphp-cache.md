---
title: Promise-Based Cache With ReactPHP
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Using asynchronous promise-based cache in PHP"
image: "/assets/images/posts/reactphp/async-cache.jpg"
---

In the previous article, we have [already touched]({% post_url 2017-09-03-reactphp-dns %}){:target="_blank"} caching (when caching DNS records). It is an asynchronous promise-based [Cache Component](https://github.com/reactphp/cache){:target="_blank"}. The idea behind this component is to provide a promise-based `CacheInterface` and instead of waiting for a result to be retrieved from a cache the client code gets a promise. If there is a value in a cache the fulfilled with this value promise is returned. If there is no value by a specified key the rejected promise returns.

<p class="text-center image">
    <img itemprop="image" src="/assets/images/posts/reactphp/async-cache.jpg" alt="async-cache" class="">
</p>

## Interface

The component has one simple in-memory `ArrayCache` implementation of `CacheInterface`. The interface is pretty simple and contains three methods: `get($key)`, `set($key, $value)` and `remove($key)`:

{% highlight php %}
<?php

namespace React\Cache;

interface CacheInterface
{
    // @return React\Promise\PromiseInterface
    public function get($key);

    public function set($key, $value);

    public function remove($key);
}
{% endhighlight %} 

## Set/Get

Let's try it to see how it works. At first, we put something in cache:

{% highlight php %}
<?php

$cache = new React\Cache\ArrayCache();
$cache->set('foo', 'bar');
{% endhighlight %}

Method `set($key, $value)` simply sets the value of the key `foo` to `bar`. If this key already exists the value will be overridden.

The next step is to get `foo` value back from the cache. Before calling `get($key)` take a look at the `CacheInterface`:

{% highlight php %}
<?php
 
// @return React\Promise\PromiseInterface
public function get($key);
{% endhighlight %} 

Notice, that `get($key)` method doesn't return the value from cache, instead, it returns a promise. Which means that we should use promise `done()` method to attach *onFulfilled* handler and actually retrieve the value from cache:

{% highlight php %}
<?php

$cache = new React\Cache\ArrayCache();
$cache->set('foo', 'bar');

$cache->get('foo')
    ->done(function($value){
        var_dump($value); // outputs 'bar'
    });
{% endhighlight %}

>*In the previous example actually, there is no need to create a callback simply to call `var_dump` function inside. You can pass a string right in `done()` method and everything will work exactly the same:*

{% highlight php %}
<?php

$cache = new React\Cache\ArrayCache();
$cache->set('foo', 'bar');

// outputs 'bar'
$cache->get('foo')->done('var_dump'); 
{% endhighlight %}

## Fallback

It may occur that there is no value in a cache. To catch this situation we should use promise `otherwise()` method and attach an *onRejected* handler:

{% highlight php %}
<?php

$cache = new React\Cache\ArrayCache();

$cache->set('foo', 'bar');

$cache
    ->get('baz')
    ->otherwise(function(){
        echo "There is no value in cache";
    });
{% endhighlight %}

The last two examples can be merged and rewritten with one promise `then()` call which accepts both *onFulfilled* and *onRejected* handlers:

{% highlight php %}
<?php

$cache = new React\Cache\ArrayCache();

$cache->set('foo', 'bar');

$cache->get('baz')->then(
    function($value) {
        var_dump($value);
    },
    function() {
        echo "There is no value in cache";
    });
{% endhighlight %}

With this approach, we can easily provide a fallback value for situations when there is no value in a cache:

{% highlight php %}
<?php

$cache = new React\Cache\ArrayCache();
$cache->set('foo', 'bar');

$data = null;

$cache->get('baz')
    ->then(
        function($value) use (&$data) {
            $data = $value;
        },
        function() use (&$data) {
            $data = 'default';
        }
    );

echo $data; // outputs 'default'
{% endhighlight %}

If there is a value in a cache the first callback is triggered and this value will be assigned to `$data` variable, otherwise the second callback is triggered and `$data` variable gets `'default'` value. 

### Fallbacks chain

The *onRejected* handler can itself return a promise. Let's create a callback with a new promise. For example, this callback tries to fetch some data from a database. On success the promise is fulfilled with this data. If there is no required data in a database we return a some default value. Here is a new fallback callback:

{% highlight php %}
<?php

$getFromDatabase = function() {
    $resolver = function(callable $resolve, callable $reject) {
        return $resolve('some data from database');
    };

    return new React\Promise\Promise($resolver);
};
{% endhighlight %}

A quick overview. Our promise has a *resolver* handler. This handler accepts two callbacks: one to fulfill the promise with some value, and another - to reject a promise. In our example we immediately fulfill the promise with a string `'some data from database'`.

The next step is to replace the *onRejected* handler for a promise which was return when we call `get($key)` method:

{% highlight php %}
<?php

$cache->get('baz')
    ->then(
        function($value) use (&$data) {
            $data = $value;
        }, 
        $getFromDatabase
    );
{% endhighlight %}

In the snippet above `$getFromDatabase` callback is triggered when there is no value in cache. This callback returns a promise, so we can continue chaining and attach one more `then()` method:

{% highlight php %}
<?php

$data = null;

$cache->get('baz')
    ->then(
      function($value) use (&$data) {
        $data = $value;
      }, 
      $getFromDatabase
    )
    ->then(function($value) use (&$data) {
        $data = $value;
    });

echo $data;
{% endhighlight %}
As you remember our promise is fulfilled with `'some data from database'` string. This value will be passed into the last `then` callback. As a result, this string will be printed by `echo`.

## Remove 

To remove something from cache simply call `remove($key)` method and specify a key to be removed.

## Conclusion

The Cache Component comes with one simple in-memory `ArrayCache` implementation. It simply stores all values in array in memory:

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

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function remove($key)
    {
        unset($this->data[$key]);
    }
}
{% endhighlight %}

But there are several more implementations:

- [WyriHaximus/react-cache-redis](https://github.com/wyrihaximus/reactphp-cache-redis){:target="_blank"} uses Redis.
- [WyriHaximus/react-cache-filesystem](https://github.com/wyrihaximus/reactphp-cache-filesystem){:target="_blank"} uses filesystem.

<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/cache){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
