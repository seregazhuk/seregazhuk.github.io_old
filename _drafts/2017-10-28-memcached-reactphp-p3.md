---
title: "Building ReactPHP Memached Client: Emitting Events"
tags: [PHP, Event-Driven Programming, ReactPHP, Memcached]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client."
---

>This is the last article from the series about building from scratch a streaming Memcached PHP client for ReactPHP ecosystem. The library is already released and published, you can find it on [GitHub](https://github.com/seregazhuk/php-react-memcached).

In the [previous article]({% post_url 2017-10-14-memcached-reactphp-p2 %}) we have faced with a problem: how to deal with broken connection. Now, when the connection is closed all pending requests are rejected with the `ConnectionClosedException`. If we want to handle these situation we need to attach *onRejected* handlers to all promises, because we can't guess in advance which one will be the problem. So, this kind of code is going to look like this:

{% highlight php %}
<?php

$factory
    ->createClient('localhost:11211')
    ->then(function (Client $client) {
        $client->set('example', 'Hello world')
            ->then(null, function(ConnectionClosedException $e) {
                // connection was closed
            });

        $client->get('example')
            ->then(function ($data) {
                // handle data
            }, function(ConnectionClosedException $e) {
                // connection was closed
            });
});

$loop->run();
{% endhighlight %}

This code already looks too complex, but also there is no way to find out if the connection was broken or we have manually close it. So, it becomes clear that we need a completely different approach.

## Events
All ReactPHP components, that emit events use [Événement](https://github.com/igorw/evenement) library, which provides EventEmitter API similar to node.js. To stay consistent we also are going to use it. It is very simply in use, simply extend your class from `Evenement\EventEmitter` and you are done:

{% highlight php %}
<?php

// ...
 
use Evenement\EventEmitter;

// ... 

class Client extends EventEmitter
{
    // ...
}
{% endhighlight %}

Now we can call `emit()` method on the client to emit events and `on()` method to attach handlers to these events. First of all let's update the client constructor, where we attach handlers to the stream and attach a handler to `close` event of the stream:

{% highlight php %}
<?php

class Client 
{
    /**
     * @param DuplexStreamInterface $stream
     * @param Parser $parser
     */
    public function __construct(DuplexStreamInterface $stream, Parser $parser)
    {
        $this->stream = $stream;
        $this->parser = $parser;

        $stream->on('data', function ($chunk) {
            $parsed = $this->parser->parseRawResponse($chunk);
            $this->resolveRequests($parsed);
        });

        $stream->on('close', function() {
            if(!$this->isEnding) {
                $this->emit('error', [new ConnectionClosedException()]);
                $this->close();
            }
        });
    }
}
{% endhighlight %}

When the steam is closed but the client is not *ending* (we didn't close it manually) that indicates that something went wrong and the connection was broken. So, we can emit `error` event and then `close()` the client to reject all pending request and change its state to `isClosed`. Also, we can update `close()` method and emit `close` event:

{% highlight php %}
<?php

class Client 
{
    // ...

    /**
     * Forces closing the connection and rejects all pending requests
     */
    public function close()
    {
        if ($this->isClosed) {
            return;
        }

        $this->isEnding = true;
        $this->isClosed = true;

        $this->stream->close();
        $this->emit('close');

        // reject all pending requests
        while($this->requests) {
            $request = array_shift($this->requests);
            /* @var $request Request */
            $request->reject(new ConnectionClosedException());
        }
    }
}
{% endhighlight %}

Now, we can try it in action. For demonstration I have a simple script, that sets the timer and output Memcached version every second. I also add two event handlers:

- a handler to `error` event. This handler outputs the occurred problem and stops an event loop. 
- a handler to `close` event to simply debug when the connection was closed.

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory
    ->createClient('localhost:11211')
    ->then(function (Client $client) use ($loop){
        $loop->addPeriodicTimer(1, function() use ($client) {
            $client->version()->then(function($version){
                echo $version, "\n";
            });
        });

        $client->on('error', function(ConnectionClosedException $e) use ($loop) {
            echo 'Error: ', $e->getMessage(), "\n";
            $loop->stop();
        });

        $client->on('close', function() {
            echo "Connection closed\n";
        });
    });

$loop->run();
{% endhighlight %}

To simulate a broken connection simply stop the server:

<p class="">
    <img src="/assets/images/posts/reactphp-memcached/events.gif" alt="events" class="">
</p>

When the server stops and the connection is *broken* the client emits `error` event because we haven't close the client manually. Then the client is being closed and emits `close` event. So, the client consumers can handle these situations.
