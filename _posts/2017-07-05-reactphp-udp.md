---
title: "UDP/Datagram Sockets with ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "A quick tutorial on setting up a UDP chat in PHP with ReactPHP"
---

# Streams vs Datagrams

When you send data through the sockets, there are situations when you really do not care if some packets are lost during the transition. For example, you are streaming a live video from your camera. The order of the frames is less important than timely delivery, you only want the frames to arrive at the clients as soon as possible. And it also does not matter if some of the frames are lost. You are not going to make a resend request just to make sure that all the frames are shown to the client in the correct order because otherwise your live video will be delayed. Nobody wants to be 10 seconds behind the present moment, so you simply skip the lost frames and move further. This is exactly how UDP (User Datagram Protocol) works. With UDP we send packets of data (datagrams) to some IP address and that is all. We have no guarantee that these packets will arrive, we also have no guarantee about their order.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/udp-joke.jpg" alt="cgn-edit" class="">
</p>

TCP (Transmission Control Protocol) instead guarantees that your data arrives otherwise it will tell you that an error occurred. This protocol uses the principle of a reliable connection. This means that we are establishing a connection between server and client and then start transferring the data. This connection can be considered as a duplex stream which provides a reliable and sequenced communication. It looks like we write information to a file on one computer, and on the other computer, you can read it from the same file. Also, the TCP connection can be considered as a continuous stream of data - the protocol itself takes care of splitting the data into packets and sending them over the network.
Datagram, on the other hand, has no connection, only a source, and a destination. Communication via UDP cannot be considered as reliable and it doesn't provide any sequence.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/tcpvsudp.jpg" alt="cgn-edit" class="">
</p>

**TCP:**

- Uses the principle of connections.
- Guarantees the delivery and sequence.
- Automatically splits information into the packets.
- Ensures that data is not sent too heavily (data flow control).

**UDP:**

- No connection between client and server.
- Not 100% reliable and may lose data.
- You need to manually split the data into datagrams and send them.
- Data sent/received order might not be the same.
 
## Simple Echo Server

[ReactPHP Datagram component](https://github.com/reactphp/datagram) provides socket client and server for ReactPHP. There is one entry point to create both client and server: `React\Datagram\Factory`. This factory has two methods `createServer()` and `createClient()` and requires an instance of the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}):

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);

$factory->createServer('localhost:1234');
{% endhighlight %}

Here we create a server socket listening on `localhost` and port `1234`. Method `createServer()` returns a [promise]({% post_url 2017-06-16-phpreact-promises %}). Then we can specify handlers when this promise becomes fulfilled and when it fails. Fulfilled handler accepts an instance of the datagram socket `React\Datagram\Socket`:

{% highlight php %}
<?php
$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);

$address = 'localhost:1234';
$factory->createServer($address)
    ->then(
        function (React\Datagram\Socket $server) {
            // ... 
        },
        function(Exception $error) {
            echo "ERROR: " . $error->getMessage() . "\n";
        });

echo "Listening on $address\n";
$loop->run();
{% endhighlight %}

We can listen to `message` event to receive the datagrams sent by the client. The handler accepts the message received from the client, the client address and an instance of the datagram socket:

{% highlight php %}
<?php

$factory->createServer('localhost:1234')
    ->then(
        function (React\Datagram\Socket $server) {
            $server->on('message', function($message, $address, $socket) {
                echo "client $address: $message\n";
            });
        },
        function(Exception $error) {
            echo "ERROR: " . $error->getMessage() . "\n";
        });   
{% endhighlight %}

To test our server we can use *netcat* from the command line:

{% highlight bash %}
$ nc -zu localhost 1234
Connection to localhost port 1234 [udp/search-agent] succeeded!
{% endhighlight %}

Nice! The server is working and listening for the incoming datagrams. Now it's time to create a simple client. And again we create an event loop and a factory, and then use the factory's `createClient()` method, which also returns a promise:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);

$factory->createClient('localhost:1234')
    ->then(
        function (React\Datagram\Socket $client) {
            // fulfilled handler ...
        },
        function(Exception $error) {
            echo "ERROR: " . $error->getMessage() . "\n";
        });

$loop->run();
{% endhighlight %}

The fulfilled handler accepts an instance of the datagram socket we have connected to. We are already familiar with this socket, which we have used in the fulfilled handler of the server. 

Now we are implementing simple *echo UDP server/client*, so our client will take the input from the console and send it to the server. The server will receive it and send it back to the client. When the client receives the data from the server it outputs it to the console. At first, we modify the server to send a data received from a client back to this client:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);
$address = 'localhost:1234';

$factory->createServer($address)
    ->then(
        function (React\Datagram\Socket $server) {
            $server->on('message', function ($message, $address, $server) {
                $server->send($address . ' echo: ' . $message, $address);
                echo 'client ' . $address . ': ' . $message . PHP_EOL;
            });
        },
        function(Exception $error) {
            echo "ERROR: " . $error->getMessage() . "\n";
        });

echo "Listening on $address\n";
$loop->run();
{% endhighlight %}

To send data via UDP socket we can use `send($data, $remoteAddress = null)`. We build a message and send it back to the address, from which we have received the incoming message. We also log some data to the console. The client part will be a bit more complex. We need an instance of a [readable stream]({% post_url 2017-06-12-phpreact-streams %}) to get the input from the console.

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);

$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);

$factory->createClient('localhost:1234')
    ->then(
        function (React\Datagram\Socket $client) use ($stdin) {
            $stdin->on('data', function($data) use ($client) {
                $client->send(trim($data));
            });
        },
        function(Exception $error) {
            echo "ERROR: " . $error->getMessage() . "\n";
        });

$loop->run();
{% endhighlight %}

In the snippet above we create an instance of the `\React\Stream\ReadableResourceStream` class. Then we pass this instance to the fulfilled handler. Then when we receive the input from the console, we `trim` it to remove a new line character and then send this data to the server.

<p class="">
    <img src="/assets/images/posts/reactphp/echo-udp-server-client-1.gif" alt="cgn-edit" class="">
</p>

The last step now is to receive the data from the server on the client side. Like we did it with the server we can listen to the `message` event:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);
$stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);

$factory->createClient('localhost:1234')
    ->then(
        function (React\Datagram\Socket $client) use ($stdin) {
            $client->on('message', function($message){
                echo $message . "\n";
            });

            $stdin->on('data', function($data) use ($client) {
                $client->send(trim($data));
            });
        },
        function(Exception $error) {
            echo "ERROR: " . $error->getMessage() ."\n";
        });

$loop->run();
{% endhighlight %}

Now we have a simple echo UDP server and a client that sends data to this server:

<p class="">
    <img src="/assets/images/posts/reactphp/echo-udp-server-client-2.gif" alt="cgn-edit" class="">
</p>

## Simple UDP Chat

### Server
UDP has no such thing as a connection between server and client, so we should implement some events ourselves. For example, the server doesn't know when a client enters or leaves the chat (connects and disconnects). The server only receives data from clients. We have a client's address and some data. So, it is a client's job to notify a server what client is going to do: *to enter a chat*, *to leave it*, or simply *to write a message*. Looks like we have three different types of data: `enter`, `leave` and `message`. So, let's start coding with a server.

{% highlight php %}
<?php

class UdpChatServer 
{
    protected $socket;

    public function process($data, $address)
    {
        // ...
    }

    public function run()
    {
        $loop = React\EventLoop\Factory::create();
        $factory = new React\Datagram\Factory($loop);
        $address = 'localhost:1234';

        $factory->createServer($address)->then(
                function (React\Datagram\Socket $server) {
                    $this->socket = $server;
                    $server->on('message', [$this, 'process']);
                }, function (Exception $error) {
                    echo "ERROR: " . $error->getMessage() . "\n";
                });

        echo "Listening on $address\n";
        $loop->run();
    }
}

(new UdpChatServer())->run();
{% endhighlight %}

This is what we have built in the *echo* section of this article but now encapsulated in the class. The main logic of our server will be implemented in the handler for the `message` event. We assume that clients send JSON-encoded arrays to us. Each array has three fields: 

 - `type` - for the type of the action.
 - `name` - a client's name.
 - `message` - a message from the client (can be empty for example, when a client enters/leaves the chat).

Then according to the `type`, we should perform different actions:

{% highlight php %}
<?php

class UdpChatServer 
{
    // ...

    public function process($data, $address)
    {
        $data = json_decode($data, true);

        if ($data['type'] == 'enter') {
            // ... a client enters the chat
            return;
        }

        if ($data['type'] == 'leave') {
            // a client leaves the chat
            return;
        }

        // a client sends a message
    }
{% endhighlight %}

Next step is to implement each of these actions. When a new client arrives we store its address in the `$clients` array. We also notify all other clients that we have a new member in the chat:

{% highlight php %}
<?php 

protected function addClient($name, $address)
{
    if (array_key_exists($address, $this->clients)) return;
        
    $this->clients[$address] = $name;

    $this->broadcast("$name enters chat", $address);
}
{% endhighlight %}

When we receive a `leave` typed message, we `unset` this client's address and notify other clients that this client has left the chat:

{% highlight php %}
<?php

protected function removeClient($address)
{
   $name = $this->clients[$address] ?? '';

    unset($this->clients[$address]);

    $this->broadcast("$name leaves chat");        
}
{% endhighlight %}

Otherwise, we have a `message` data, so we simply send this message to other clients. We format a message to include a client's name in it and then send this message to everybody except this current client:

{% highlight php %}
<?php

protected function sendMessage($message, $address)
{
    $name = $this->clients[$address] ?? '';

    $this->broadcast("$name: $message", $address);
} 

protected function broadcast($message, $except = null)
{
    foreach ($this->clients as $address => $name) {
        if ($address == $except) continue;

        $this->socket->send($message, $address);
    }
}
{% endhighlight %}

Now our main `process` method will look like this. Very simple stuff:

{% highlight php %}
<?php

public function process($data, $address)
{
    $data = json_decode($data, true);

    if ($data['type'] == 'enter') {
        $this->addClient($data['name'], $address);
        return;
    }

    if ($data['type'] == 'leave') {
        $this->removeClient($address);
        return;
    }

    $this->sendMessage($data['message'], $address);
}
{% endhighlight %}

The server part now is ready. You can find a full source of it on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/datagram/chat/server.php).

### Client
The client grabs the input from the console and then sends some data to the server. To interact with the console we are going to use a [readable stream]({% post_url 2017-06-12-phpreact-streams %}). So let's start implementing the client:

{% highlight php %}
<?php

class UdpChatClient
{
    /** @var  React\EventLoop\LoopInterface; */
    protected $loop;

    /** @var React\Stream\ReadableStreamInterface;  */
    protected $stdin;

    /** @var  React\Datagram\Socket  */
    protected $socket;

    protected $name = '';

    public function run()
    {
        $this->loop = React\EventLoop\Factory::create();
        $factory = new React\Datagram\Factory($this->loop);

        $this->stdin = new React\Stream\ReadableResourceStream(STDIN, $this->loop);
        $this->stdin->on('data', [$this, 'processInput']);

        $factory->createClient('localhost:1234')
            ->then(
                [$this, 'initClient'],
                function (Exception $error) {
                    echo "ERROR: " . $error->getMessage() . "\n";
                });

        $this->loop->run();
    }

    public function initClient(React\Datagram\Socket $client)
    {
        $this->socket = $client;

        // ...
    }

    public function processInput($data)
    {
        // ...
    }
}

(new UdpChatClient())->run();

{% endhighlight %}

There are two main methods here. `initClient` to setup main socket event handlers and `processInput` which is responsible for taking a user input, processing it and then sending to a server. The first method will be very simple. We are going to listen to several events: `message` to output the received data and `close` to stop the event loop. Also when we are connected to a socket we ask a user to enter the name:

{% highlight php %}
<?php

public function initClient(React\Datagram\Socket $client)
{
    $this->socket = $client;

    $this->socket->on('message', function ($message) {
        echo $message . "\n";
    });

    $this->socket->on('close', function () {
        $this->loop->stop();
    });

    echo "Enter your name: ";
}
{% endhighlight %}

The next method `processInput($data)` is responsible for the main logic of the client. When we grab the input we need to perform some checks. First of all, if the `$name` property is empty that means that we have just connected to a socket. So, we assume that a user's input is his or her name, we store it in the `$name` property and then we send a data with type `enter` indicating that a new user enters the chat. But how can we determine that a client leaves that chat? I'm going to use a *vim-like* command here. For example, when a user enters `:exit` string that means that we are leaving a chat. In all other cases we consider that a user has entered a simple message:

{% highlight php %}
<?php

public function processInput($data)
{
    $data = trim($data);

    if (empty($this->name)) {
        $this->name = $data;
        $this->sendData('', 'enter');
        return;
    }

    if ($data == ':exit') {
        $this->sendData('', 'leave');
        $this->socket->end();
        return;
    }

    $this->sendData($data);
}

protected function sendData($message, $type = 'message')
{
    $data = [
        'type'    => $type,
        'name'    => $this->name,
        'message' => $message,
    ];

    $this->socket->send(json_encode($data));
}
{% endhighlight %}
A full source code for a client can be found on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/datagram/chat/client.php).

Now we can test our chat in action. This is how it looks:

<p class="">
    <img src="/assets/images/posts/reactphp/udp-chat.gif" alt="cgn-edit" class="">
</p>

# Conclusion

Of course, the goal of this article was not to build a robust chat but to show how you can use [ReactPHP Datagram component](http://reactphp.org/datagram/) to work with UDP sockets. We have also covered the difference between TCP and UDP sockets and in which situations each of them will be more preferable. A simple chat here was a sort of a real world application built on UDP sockets to demonstrate their usage.

<hr>

You can find a source code for this client on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/datagram).

<strong>Other ReactPHP articles:</strong>

- [Event loop and timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Streams]({% post_url 2017-06-12-phpreact-streams %})
- [Promises]({% post_url 2017-06-16-phpreact-promises %})
- [Chat on sockets: server]({% post_url 2017-06-22-reactphp-chat-server %}) and  [client]({% post_url 2017-06-24-reactphp-chat-client %})
- [Video streaming server]({% post_url 2017-07-17-reatcphp-http-server %})
- [Parallel downloads with async http requests]({% post_url 2017-07-26-reactphp-http-client %})
- [Managing Child Processes]({% post_url 2017-08-07-reactphp-child-process %})
- [Cancelling Promises With Timers]({% post_url 2017-08-22-reactphp-promise-timers %})
- [Resolving DNS Asynchronously]({% post_url 2017-09-03-reactphp-dns %})
