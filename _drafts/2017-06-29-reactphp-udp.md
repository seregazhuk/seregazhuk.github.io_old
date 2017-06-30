---
title: "UDP/Datagram Sockets with ReactPHP"
tags: [PHP, ReactPHP]
layout: post
description: "A quick tutorial on setting up a UDP server and client in PHP with ReactPHP"
---

# Streams vs Datagrams

When you send data through the sockets, there are situations when you really do not care if some packets are lost during the transition. For example, you are streaming a live video from your camera. The order of the frames is less important than timely delivery, you only want the frames to arrive at the clients as soon as possible. And it also does not matter if some of the frames are lost. You are not going to make a resend request just to make sure that all the frames are shown to the client in the correct order because otherwise your live video will be delayed. Nobody wants to be 10 seconds behind the present moment, so you simply skip the lost frames and move further. This is exactly how UDP (User Datagram Protocol) works. With UDP we send packets of data (datagrams) to some IP address and that is all. We have no guarantee that these packets will arrive, we also have no guarantee about their order.

TCP (Transmission Control Protocol) instead guarantees that your data arrives otherwise it will tell you that an error occurred. This protocol uses the principle of a reliable conneciton. This means that we are establishing a connection between server and client and then start transfering the data. This connection can be considered as a duplex stream which provides a reliable and sequenced communication. It looks like we write information to a file on one computer, and on the other computer you can read it from the same file. Also, the TCP connection can be considered a continuous stream of data - the protocol itself takes care of splitting the data into packets and sending them over the network.
Datagram, on the other hand, has no connection, only a source, and a destination. Communication via UDP cannot be considered as reliable and it doesn't provide any sequence.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp/tcpvsudp.jpg" alt="cgn-edit" class="">
</p>

**TCP:**

- Uses the principle of connections.
- Guarantees the delivery and sequence.
- Automatically splits information into the packets.
- Ensures that data is not sent too heavily (data flow control)

**UDP:**

- No connection between client and server.
- Not 100% reliable and may lose data.
- You need to manually split the data into datagrams and send them.
- Data sent/received order might not be the same.
 
## ReactPHP Datagram component

[ReactPHP Datagram component](https://github.com/reactphp/datagram) provides socket client and server for ReactPHP. There is one entry point to create both client and server: `React\Datagram\Factory`. This factory has two methods `createServer()` and `createClient()` and requires an instance of the event loop:

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);

$factory->createServer('localhost:1234');
{% endhighlight %}

Here we create a server socket listening on `localhost` and port `1234`. Method `createServer()` returns a [promise]({% post_url 2017-06-16-phpreact-promises %}). Then we can specify handlers when this promise becomes fulfilled and when it fails. Fulfilled handler accepts an instance of the server socker `React\Datagram\Socket`:

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
        function($error) {
            echo "ERROR: {$error->getMessage()}\n";
        });

echo "Listening on $address\n";
$loop->run();
{% endhighlight %}

We can listen to `message` event of the server to recieve the datagrams sent by the client. The handler accepts the message received from the client, the client address and an instance of the server:
{% highlight php %}
<?php

$factory->createServer('localhost:1234')
    ->then(
        function (React\Datagram\Socket $server) {
            $server->on('message', function($message, $address, $server) {
                echo "client $address:  $message\n";
            });
        },
        function($error) {
            echo "ERROR: {$error->getMessage()}\n";
        });   
{% endhighlight %}

To test the server we can use *netcat* from the command line:

{% highlight bash %}
$ nc -uv localhost 1234
Connection to localhost port 1234 [udp/search-agent] succeeded!
{% endhighlight %}

Nice! The server is working and listening for the incoming datagrams. Now its time to create a simple client. And again we create an event loop and a factory, and then use `createClient()` method, which also returns a promise:

{% highlight php %}

{% endhighlight %}