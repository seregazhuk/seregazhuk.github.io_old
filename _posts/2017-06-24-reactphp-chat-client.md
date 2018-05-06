---
title: "Build A Simple Chat With ReactPHP Socket: Client"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Build a simple chat with ReactPHP sockets, creating a socket client"
---

In the [previous article]({% post_url 2017-06-22-reactphp-chat-server %}){:target="_blank"}, we have created a simple chat server based on [ReactPHP Socket](https://github.com/reactphp/socket){:target="_blank"} component. We have used a telnet client to connect to this server, now it's time to create our own PHP client, also based on sockets. The source code for the server is available [here on GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/socket/server.php){:target="_blank"}.

First of all, we need to create a streaming connection via `React\Socket\Connector` class. Its constructor requires an instance of the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}){:target="_blank"}:

{% highlight php %}
<?php

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop);
{% endhighlight %}

This class provides only a single method `connect($uri)` to connect to the server, which is listening on the specified URI. Remember that our [server]({% post_url 2017-06-22-reactphp-chat-server %}){:target="_blank"} is listening on `127.0.0.1:8080`. This method returns a [promise]({% post_url 2017-06-16-phpreact-promises %}){:target="_blank"}. When this promise is fulfilled you receive an instance of the streaming connection which implements `React\Socket\ConnectionInterface`. If the promise is rejected you get an exception:

{% highlight php %}
<?php 

$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop);

$connector
    ->connect('127.0.0.1:8080')
    ->then(
        function (ConnectionInterface $conn)  {
            echo "Connection established\n";
        },
        function (Exception $exception) use ($loop){
            echo "Cannot connect to server: " . $exception->getMessage();
            $loop->stop();
        });

$loop->run();
{% endhighlight %}

`ConnectionInterface` itself extends `React\Stream\DuplexStreamInterface` that means that we can use this connection as a [duplex stream]({% post_url 2017-06-12-phpreact-streams %}){:target="_blank"} (we can read and write data). For example, we can attach a handler to `data` event and then output everything we get from the server to the console:

{% highlight php %}
<?php 

// ...

$connector
    ->connect('127.0.0.1:8080')
    ->then(
        function (ConnectionInterface $conn)  {
            $conn->on('data', function($data){
                echo $data;
            });
        },
        function (Exception $exception) use ($loop){
            // reject handler
        });
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/simple-chat-server-client-connect.gif" alt="cgn-edit" class="">
</p>

To write data to the connection we can use `write($data)` method of the `React\Stream\DuplexStreamInterface`. But we need somehow to grab this data from the console and then write it to the connection. To read data from the console we can create an instance of the `ReadableResourceStream`:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);
{% endhighlight %}

And then attach a handler to the `data` event, so we can receive the input from the console and then write it to the connection:

{% highlight php %}
<?php

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop);
$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);

$connector
    ->connect('127.0.0.1:8080')
    ->then(
        function (ConnectionInterface $conn) use($stdin) {
            $conn->on('data', function($data){
                echo $data;
            });
            $stdin->on('data', function ($data) use ($conn) {
                $conn->write($data);
            });
        },
        function (Exception $exception) use ($loop){
            // reject 
        });

$loop->run();
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/simple-chat-server-client-readable.gif" alt="cgn-edit" class="">
</p>

We can also use a *writable* stream to output the data instead of *echoing* it. So, we create an instance of `\React\Stream\WritableResourceStream` class and pass it to the connection *onFulfilled* handler. Then every time we receive some data from the connection we can `write()` it to the stream:

{% highlight php %}
<?php 

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();

$connector = new React\Socket\Connector($loop);

$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);
$stdout = new \React\Stream\WritableResourceStream(STDOUT, $loop);

$connector
    ->connect('127.0.0.1:8080')
    ->then(
        function (ConnectionInterface $conn) use($stdin, $stdout) {
            $conn->on('data', function($data) use ($stdout){
                $stdout->write($data);
            });
            
            $stdin->on('data', function ($data) use ($conn) {
                $conn->write($data);
            });
        },
        function (Exception $exception) use ($loop){
           // reject
        });

$loop->run();
{% endhighlight %}

Of course, you can argue that we are over-engineering here. Now, instead of the simple `echo`, we create an object, pass it to the closure and then call a method on it. Looks complex, but we can continue refactoring with streams. Now, when we have both readable and writable streams, and the connection itself also is a duplex stream, we can *pipe* them together:

{% highlight php %}
<?php

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$connector = new React\Socket\Connector($loop);

$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);
$stdout = new \React\Stream\WritableResourceStream(STDOUT, $loop);

$connector
    ->connect('127.0.0.1:8080')
    ->then(
        function (ConnectionInterface $conn) use ($stdout, $stdin) {
            $stdin->pipe($conn)->pipe($stdout);
        },
        function (Exception $exception) use ($loop){
            // reject
        });

$loop->run();
{% endhighlight %}

Now the code looks much cleaner. Instead of listening to `data` events of the both connection and `$stdin` we simply create a chain of streams. When we receive the data from the console we write it to the connection. And at the same time when we receive the data from the connection, we output it on the console. And on this note, our chat client is done.

<hr>

You can find a source code for this client on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/socket/client.php){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
