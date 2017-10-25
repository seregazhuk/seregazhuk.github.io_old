---
title: "Building ReactPHP Memached Client: Errors And Connection Handling"
tags: [PHP, Event-Driven Programming, ReactPHP, Memcached]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client part 2: errors and connection handling"
---

In the [previous article]({% post_url 2017-10-09-memcached-reactphp-p1 %}) we have created a simple streaming Memcached client for ReactPHP ecosystem. It can connect to Memcached server, execute commands and asynchronously return results. In this article we are going to implement some improvements:

- connection handling
- errors handling

## Connection Closing

When the client is being created via factory it already receives an opened connection. But now, when we are done, there is no way for us to close the connection. Let's implement this. The will be actually to ways to close the connection: 

 - **gentle**: When we don't accept new requests but the connection will be closed when all the pending requests will be resolved.
 - **forced**: When we immediately close the stream and all pending requests become rejected. 

### Gentle closing

To implement both ways for closing the connection we need to store two flags in the state of the client:

- `isEnding` indicated that we are not accepting new requests, but waiting for pending requests to be resolve.
- `isClosed` indicated that the connection is closed.

{% highlight php %}
<?php

class Client
{
    /**
     * @var bool
     */
    protected $isClosed = false;

    /**
     * @var bool
     */
    protected $isEnding = false;

    // ...

    /**
     * Closes the connection when all requests are resolved
     */
    public function end()
    {
        // ...
    }

    /**
     * Closes the stream
     */
    public function close()
    {
        // ...
    }
}
{% endhighlight %}

When the client is instantiated both flags are set to `false`. Method `end()` will be used to close the connection in a *gentle way*:

{% highlight php %}
<?php

class Client
{
    /**
     * @var bool
     */
    protected $isClosed = false;

    /**
     * @var bool
     */
    protected $isEnding = false;

    // ...

    /**
     * Closes the connection when all requests are resolved
     */
    public function end()
    {
        $this->isEnding = true;

        if (!$this->requests) {
            $this->close();
        }
    }

    /**
     * Closes the stream
     */
    public function close()
    {
         if ($this->isClosed) {
            return;
        }

        $this->isClosed = true;

        $this->stream->close();
    }
}
{% endhighlight %}

We simply set `isEnding` flag to `true`. If there are no pending requests we immediately close the connection and set `isClosed` flag to `true`, otherwise, we should at first resolve these requests. That means that now we need to update two methods:

- `__call()` to stop accepting new incoming requests when the client *is ending*
- `resolveRequests()` to close the connection when the client *is ending* and we are done with all pending requests.

To freshen up your memory, that's how `__call()` method looks:

{% highlight php %}
<?php

class Client
{
    // ...

    /**
     * @param string $name
     * @param array $args
     * @return Promise|PromiseInterface
     */
    public function __call($name, $args)
    {
        $request = new Request($name);

        $query = $this->parser->makeRequest($name, $args);
        $this->stream->write($query);
        $this->requests[] = $request;

        return $request->getPromise();
    }

    // ...
}
{% endhighlight %}

It uses the called method's name and its arguments to create a raw Memcached command. Writes this command to the stream and save the pending requests in the state. All we need to do is to check if the client is *ending*. If so, we immediately reject the request, otherwise, we send it to Memcached as we did before:

{% highlight php %}
<?php

class Client
{
    // ...

    /**
     * @param string $name
     * @param array $args
     * @return Promise|PromiseInterface
     */
    public function __call($name, $args)
    {
        $request = new Request($name);

        if($this->isEnding) {
            $request->reject(new ConnectionClosedException('Connection closed'));
        } else {
            $query = $this->parser->makeRequest($name, $args);
            $this->stream->write($query);
            $this->requests[] = $request;
        }

        return $request->getPromise();
    }

    // ...
}
{% endhighlight %}

`__call()` method is done. Now we move on to `resolveRequests()`. Again, to refresh your memory, here is the source code of how it looks now:

{% highlight php %}
<?php

class Client
{
    /**
     * @param array $responses
     * @throws LogicException
     */
    protected function resolveRequests(array $responses)
    {
        if (empty($this->requests)) {
            throw new LogicException('Received unexpected response, no matching request found');
        }

        foreach ($responses as $response) {
            /* @var $request Request */
            $request = array_shift($this->requests);

            $parsedResponse = $this->parser->parseResponse($request->getCommand(), $response);
            $request->resolve($parsedResponse);
        }
    }
}
{% endhighlight %}

It receives an array of raw responses from the server and then resolves each pending request with an appropriate response. After `foreach` block, when all responses have been processed we need to check if some pending queries still remain. If all pending requests were resolved and the client *is ending* we can close the stream:

{% highlight php %}
<?php

class Client
{
    /**
     * @param array $responses
     * @throws LogicException
     */
    protected function resolveRequests(array $responses)
    {
        if (empty($this->requests)) {
            throw new LogicException('Received unexpected response, no matching request found');
        }

        foreach ($responses as $response) {
            /* @var $request Request */
            $request = array_shift($this->requests);

            $parsedResponse = $this->parser->parseResponse($request->getCommand(), $response);
            $request->resolve($parsedResponse);
        }

        if ($this->isEnding && !$this->requests) {
            $this->close();
        }
    }
}
{% endhighlight %}

With these changes now we can manually close the connection. When `end()` method is called, the client moves to *is ending* state. It rejects all new requests, resolves pending requests and then closes the stream:

{% highlight php %}
<?php


$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient('localhost:11211')->then(
    function (Client $client) {
        $client->set('name', 'test')->then(function() {
            echo "The value was stored\n";
        });

        $client->end();

        $client->get('name')->then(
            function($data) {
                var_dump($data);
                echo "The value was retrieved\n";
            }, 
            function(Exception $e) {
                echo $e->getMessage(), "\n";
            });
    },
    function(Exception $e) {
        echo $e->getMessage(), "\n";
    });

$loop->run();
{% endhighlight %}

This script outputs the following:
<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp-memcached/end.png" alt="end" class="">
    </p>
</div>

When we call `get()` this request is immediately rejected with *connection closed* exception. But the pending `set` request is resolved.

### Forced Connection Closing

There are situations when we don't want to wait for all pending requests to be resolved and want to immediately close the connection. Method `close()` already closes the connection and sets `isClosed` flag. We can update it a bit for our needs:

- also set `isEnding` flag, so the client will reject all new requests
- reject all pending requests

{% highlight php %}
<?php

class Client
{
    // ...

    /**
     * Closes the stream
     */
    public function close()
    {
         if ($this->isClosed) {
            return;
        }

        $this->isEnding = true;
        $this->isClosed = true;

        $this->stream->close();

        // reject all pending requests
        while($this->requests) {
            $request = array_shift($this->requests);
            /* @var $request Request */
            $request->reject(new ConnectionClosedException('Connection closed'));
        }
    } 
}
{% endhighlight %}

Done! Now check it with the same example. But now we call `close()` instead of `end()`. Also, add a rejection handler to the `set()` call's promise:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$factory->createClient('localhost:11211')->then(
    function (Client $client) {
        $client->set('name', 'test')->then(function() {
            echo "The value was stored\n";
        }, function(Exception $e) {
            echo 'set: ', $e->getMessage(), "\n";
        });

        $client->close();

        $client->get('name')->then(function($data) {
            var_dump($data);
            echo "The value was retrieved\n";
        }, function(Exception $e) {
            echo 'get: ', $e->getMessage(), "\n";
        });
    },
    function(Exception $e) {
        echo $e->getMessage(), "\n";
    });

$loop->run();
{% endhighlight %}

This script when being executed outputs this:

<div class="row">
    <p class="col-sm-9 pull-left">
        <img src="/assets/images/posts/reactphp-memcached/close.png" alt="close" class="">
    </p>
</div>
After we call `close()`, the `set()` request is rejected before the client receives the results from the server. Then a new `get()` request is also is rejected.
