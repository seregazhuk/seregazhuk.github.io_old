---
title: "Resolving DNS Asynchronously With ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "How to asynchronously resolve DNS in PHP with ReactPHP"
---

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/dns-resolving.jpg" alt="dns-resolving" class="">
</p>

## Basic Usage
It is always much more convenient to use domain names instead of IPs addresses. [ReactPHP DNS Component](http://reactphp.org/dns/) provides this lookup feature for you. To start using it first you should create a resolver via factory `React\Dns\Resolver\Factory`. Its `create()` method accepts a nameserver and an instance of the event loop.

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();

$dns = $factory->create('8.8.8.8', $loop);
{% endhighlight %}

In the example above we have created a DNS resolver with Google nameserver.

>*Notice! Factory `create()` method loads your system `hosts` file. This method uses `file_get_contents()` function to load the contents of the file, which means that when being executed it blocks the loop. This may be an issue if you `hosts` file is too huge or is located on a slow device. So, a good practice is to create a resolver once before the loop starts, not while it is already running.*

Then to start resolving IP addresses we use method `resolve()` on the resolver. Because things happen asynchronously `resolve()` method returns a promise (read [this article]({% post_url 2017-06-16-phpreact-promises %}) if you are new to promises): 

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();

$dns = $factory->create('8.8.8.8', $loop);
$dns->resolve('php.net')
    ->then(function ($ip) {
        echo "php.net: $ip\n";
    });

$loop->run();
{% endhighlight %}

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/dns-resolve-success.png" alt="dns-resolve-success" class="">
    </p>
</div>

When a domain is resolved `onFulfilled` handler of the promise is called. It will receive a resolved IP address as an argument. If resolving fails `onRejected` handler is called. This handler receives an instance of the `React\Dns\RecordNotFoundException`:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$dns = $factory->create('8.8.8.8', $loop);

$dns = $factory->create('8.8.8.8', $loop);
$dns->resolve('some-wrong-domain')
    ->otherwise(function (\React\Dns\RecordNotFoundException $e) {
        echo "Cannot resolve: " . $e->getMessage();
    });
$loop->run();
{% endhighlight %}

The output of this script will be the following:

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/dns-resolve-fails.png" alt="dns-resolve-fails" class="">
    </p>
</div>

The full example (handling both success and failure) can be the following:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$dns = $factory->create('8.8.8.8', $loop);

$dns->resolve('php.net')
    ->then(function ($ip) {
        echo "php.net: $ip\n";
    })
    ->otherwise(function (\React\Dns\RecordNotFoundException $e) {
        echo "Cannot resolve: " . $e->getMessage();
    });

$loop->run(); 
{% endhighlight %} 

There may be situations when we don't want to wait too long for a pending request. For example, if we haven't received IP address in 2 seconds we don't care anymore. The `resolve()` method returns a promise, so we can use this object and later cancel it:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$dns = $factory->create('8.8.8.8', $loop);

$resolve = $dns->resolve('php.net')
    ->then(function ($ip) {
        echo "php.net: $ip\n";
    })
    ->otherwise(function (\React\Dns\RecordNotFoundException $e) {
        echo "Cannot resolve: " . $e->getMessage();
    });

// ...

$resolve->cancel();
{% endhighlight %}

You can also use [Promise Timeouts]({% post_url 2017-08-22-reactphp-promise-timers %}) for this example:

{% highlight php %}
<?php

$resolve = $dns->resolve('php.net')
    ->then(function ($ip) {
        echo "php.net: $ip\n";
    })
    ->otherwise(function (\React\Dns\RecordNotFoundException $e) {
        echo "Cannot resolve: " . $e->getMessage();
    });

\React\Promise\Timer\timeout($resolve, 2, $loop);
{% endhighlight %}

>*By default `resolve()` method tries to resolve a domain name twice for 5 seconds.*

## Caching
For situations when you are going to resolve the same domain many times you can use a *cached* resolver. It will store all results in memory and next time when you try to resolve a domain which has already been resolved it will return its IP address from a cache. No additional queries will be executed. 

You can use the same factory to create a cached resolver.  But this time `createCached()` method is being used:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$dns = $factory->createCached('8.8.8.8', $loop);
{% endhighlight %}

A script where the same domain has to looked up several times:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$dns = $factory->createCached('8.8.8.8', $loop);

$dns->resolve('php.net')
    ->then(function ($ip) {
        echo "php.net: $ip\n";
    });

// ...

$dns->resolve('php.net')
    ->then(function ($ip) {
        echo "php.net: $ip\n";
    });

$loop->run();
{% endhighlight %}

In the snippet above the second call will be served from a cache. By default, an in-memory (`React\Cache\Array`) cache is being used but you can specify your own implementation of the `React\Cache\CacheInterface`. It is an async, promise-based [cache interface](https://github.com/reactphp/cache). Then simply pass an instance of your own cache as a third argument to the `createCached()` method:

{% highlight php %}
<?php

$cache = new MyCustomAsyncCache();
$loop = React\EventLoop\Factory::create();
$factory = new React\Dns\Resolver\Factory();
$dns = $factory->createCached('8.8.8.8', $loop, $cache);
{% endhighlight %}

## Custom DNS queries

`React\Dns\Resolve\Resolver` doesn't make queries itself, instead, it proxies resolve calls to another *executor* class (`React\Dns\Query\Executor`). This class actually performs all queries. Let's create an instance of it. The constructor accepts four arguments:

 - an instance of the event loop
 - an instance of the `React\Dns\Protoco\Parser` class. This class is responsible for parsing raw binary data.
 - an instance of the `React\Dns\Protocol\BinaryDumper` class, which is used to convert the request to a binary data.
 - a timeout, which is currently **deprecated** and you should pass `null`.

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\Dns\Query\Executor;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;

$loop = Factory::create();
$executor = new Executor($loop, new Parser(), new BinaryDumper(), null);
{% endhighlight %}

Class `Executor` implements `React\Dns\Query\ExecutorInterface` which has only one public method `query($nameserver, Query $query)`. This method accepts a nameserver string and `React\Dns\Query` object. Under the hood, when you call `resolve()` on a resolver object, it creates an instance of the `Query` object and passes it to the executor:

{% highlight php %}
<?php

namespace React\Dns\Resolver;

// ...

class Resolver

    public function resolve($domain)
    {
        $query = new Query($domain, Message::TYPE_A, Message::CLASS_IN, time());
        $that = $this;

        return $this->executor
            ->query($this->nameserver, $query)
            ->then(function (Message $response) use ($query, $that) {
                return $that->extractAddress($query, $response);
            });
    }
}   
{% endhighlight %}

And here the customization comes. We can create our own custom `Query` object. In the constructor, the most interesting argument is the second one (`$type`). It is a string containing the types of records being requested. It requires some knowledge how DNS works. Here are some popular record types:

- `React\Dns\Model\Message::TYPE_A` The most frequently used is *address* or A type. This type of record maps an IPv4 address to a domain name.
- `React\Dns\Model\Message::TYPE_CNAME` The *canonical name* (CNAME) is used for aliases, for example when we have domain with and without *www*.
- `React\Dns\Model\Message::TYPE_MX` MX records point to a mail server. When you send email to `admin@mydomain.com`, the MX record tells your email server where to send the email.
- `React\Dns\Model\Message::TYPE_AAAA` is an equivalent of `TYPE_A` but for IPv6.

>*Class `React\Dns\Model\Message` contains 8 different constants related to DNS record types. Take a look at this class when you need to request some specific record.*

Now, let's get IPv6 address for php.net. First, we need to create a new `Query` object:

{% highlight php %}
<?php

use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Dns\Query\Executor;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;
use React\EventLoop\Factory;

$loop = Factory::create();
$executor = new Executor($loop, new Parser(), new BinaryDumper(), null);
$query = new Query('php.net', Message::TYPE_AAAA, Message::CLASS_IN, time());
{% endhighlight %}

Then pass this object to the executor `query()` method. This method returns a promise so we can add `onFulfilled` handler to receive the results:

{% highlight php %}
<?php

use React\Dns\Model\Message;
use React\Dns\Query\Query;
use React\Dns\Query\Executor;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;
use React\EventLoop\Factory;

$loop = Factory::create();
$executor = new Executor($loop, new Parser(), new BinaryDumper(), null);
$query = new Query('php.net', Message::TYPE_AAAA, Message::CLASS_IN, time());

$executor->query('8.8.8.8:53', $query)
    ->then(function(Message $message){
        foreach ($message->answers as $answer) {
            echo $answer->data, "\n";
        }
    });
$loop->run();
{% endhighlight %}

**Notice!** `onFulfilled` handler receives an instance of the `React\Dns\Model\Message` class. This class has a public property `$answers`, which is an array of `React\Dns\Model\Record` class instances. To get the actual address we can grab it from its public property `$data`. The result of this script:

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/dns-resolve-custom.png" alt="dns-resolve-custom" class="">
    </p>
</div>

## Resolver and Executor

You can notice that a handler for `Executor` receives `Message` object which contains an array of answers (DNS records) for a specified domain and type. But when we use `Resolver`, its handler receives only one address. Lets check on google.com.

Using `Resolver` and `Factory`:

{% highlight php %}
<?php

$dns = $factory->create('8.8.8.8', $loop);
$dns->resolve('google.com')
    ->then(function ($ip) {
        echo "google.com: $ip\n";
    });
{% endhighlight %}
<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/dns-resolver-google.png" alt="dns-resolver-google" class="">
    </p>
</div>

And using custom `Executor` and `Query`:

{% highlight php %}
<?php

$executor = new Executor($loop, new Parser(), new BinaryDumper(), null);
$query = new Query('google.com', Message::TYPE_A, Message::CLASS_IN, time());

$executor->query('8.8.8.8:53', $query)
    ->then(function(Message $message){
        foreach ($message->answers as $answer) {
            echo $answer->data, "\n";
        }
    });
{% endhighlight %}
<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp/dns-custom-google.png" alt="dns-custom-google" class="">
    </p>
</div>

Such different results are explained by the fact that under the hood, `Resolver` parses `Message` object and returns a random address from the `$answers` variable. Here is the source code of the `Resolver::extractAddress()` method:

{% highlight php %}
<?php

namespace React\Dns\Resolver;

class Resolver
{
    // ...

    public function extractAddress(Query $query, Message $response)
    {
        $answers = $response->answers;

        $addresses = $this->resolveAliases($answers, $query->name);

        if (0 === count($addresses)) {
            $message = 'DNS Request did not return valid answer.';
            throw new RecordNotFoundException($message);
        }

        $address = $addresses[array_rand($addresses)];
        return $address;
    }
    // ... 
}
{% endhighlight %}

Also, `Resolver` when being created by the `Factory` doesn't use only `Executor` class. The `Factory` wraps an instance of the `Executor` in several decorators before passing it to the `Resolver` constructor as a dependency:

- `TimeoutExecutor` which will cancel resolving in 5 seconds (by default). Uses [PromiseTimer Component]({% post_url 2017-08-22-reactphp-promise-timers %}) under the hood.
- `RetryExecutor` which tries twice (by default) to resolve a domain if `TimeoutException` was thrown.
- `HostsFileExecutor` which tries to resolve a domain from `hosts` file in your system.
- `CachedExecutor` is used only when creating a *cached* resolver via `createCached()` method.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/dns).

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
