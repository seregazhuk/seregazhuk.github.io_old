---
title: "Simple Chat With ReactPHP: Server"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
---

In this article we are going to build something more serious than simple 10-lines examples. It will be a chat application based on [ReactPHP Socket Component](https://github.com/reactphp/socket). With this component we can build simple async, streaming plaintext TCP/IP and secure TLS socket server and client.

## Server

To build a server we need a socket for accepting the incoming connections. To create this socket we can use class `React\Socket\Server` and as always we need an instance of the event loop:

{% highlight php %}
<?php

require  'vendor/autoload.php';

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:8080', $loop);

$loop->run();
{% endhighlight %}
 
This server now is not very useful because everything it does is listening for the incoming connections on port `8080` of the localhost. The instance of the `Server` implements `EventEmitterInterface` that means that we can listen to different events and react to them. When we have a new incoming connection the `connection` event will be emitted. In the handler for this event we have an access to the entire connection which implmenets `ConnectionInterface`:

{% highlight php %}
<?php

require  'vendor/autoload.php';

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:8080', $loop);

$socket->on('connection', function(ConnectionInterface $connection){
    $connection->write('Hi');
});

$loop->run();
{% endhighlight %}

Now our server becomes more friendly and sends `Hi!` to every incoming connection. We can test it from console using telnet:

<p class="">
    <img src="/assets/images/posts/reactphp/server-hi.png" alt="cgn-edit" class="">
</p>

A connection object which is available in the handler also implements `EventEmitterInterface`, so we can start listeting for some interesting events. May be the most useful will be the `data` event, which is emitted when a client sends some data to the server. You can recieve this data in a handler:

{% highlight php %}
<?php

$connection->on('data', function($data) use ($connection){
    // ...  
});
{% endhighlight %}

A connection works like a duplex (both readable and writable) stream, we can read the data from it (listen to `data` event) and write to it (via `write($data)` method). To test the things we can simply uppercase this data and send it back to the client:

{% highlight php %}
<?php

require  'vendor/autoload.php';

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:8080', $loop);

$socket->on('connection', function(ConnectionInterface $connection){
    $connection->write('Hi!');
    $connection->on('data', function($data) use ($connection){
        $connection->write(strtoupper($data));
    });
});
$loop->run();
{% endhighlight %}

The server becomes more interractive:

<p class="">
    <img src="/assets/images/posts/reactphp/server-uppercase.gif" alt="cgn-edit" class="">
</p>

The next step is to pass the data between different clients. To achieve this we need somehow to store active connections in a pool. Then when we recieve `data` event, we can `write()` this data to all the other connections in the pool. That means that we need to implement a simple pool which can add connections to itself and register some hanlders. To store the connections we will use an instance of `SplObjectStorage`. When a new connection arrives we register the event handlers and then attach it to the pool. We are going to listen to two events: 

- `data` to send the received data from one connection to others
- `close` to remove the connection from the loop

Here is the source code for the `ConnectionsPool` class: 

{% highlight php %}
<?php

require  'vendor/autoload.php';

use React\Socket\ConnectionInterface;

class ConnectionsPool {

    /** @var SplObjectStorage  */
    protected $connections;

    public function __construct()
    {
        $this->connections = new SplObjectStorage();
    }

    public function add(ConnectionInterface $connection)
    {
        $connection->write("Hi\n");
        $this->initEvents($connection);
        $this->connections->attach($connection);

        $this->sendAll("New user enters the chat\n", $connection);
    }

    /**
     * @param ConnectionInterface $connection
     */
    protected function initEvents(ConnectionInterface $connection)
    {
        // On receiving the data we loop through other connections
        // from the pool and write this data to them
        $connection->on('data', function ($data) use ($connection) {
            $this->sendAll($data, $connection);
        });

        // When connection closes detach it from the pool
        $connection->on('close', function() use ($connection){
            $this->connections->detach($connection);
            $this->sendAll("A user leaves the chat\n", $connection);
        });
    }

    /**
     * Send data to all connections from the pool except
     * the specified one.
     *
     * @param mixed $data
     * @param ConnectionInterface $except
     */
    protected function sendAll($data, ConnectionInterface $except) {
        foreach ($this->connections as $conn) {
            if ($conn != $except) $conn->write($data);
        }
    }
}
{% endhighlight %}

The server now only listens to the `connection` event, when it is emitted we add a new connection to the pool. The pool attaches it to the storage, registeres the event handles and sends a message to other connections that a new user enters the chat. When a connection closes we also notify other connections that someone leaves the chat.

{% highlight php %}
<?php

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:8080', $loop);
$pool = new ConnectionsPool();

$socket->on('connection', function(ConnectionInterface $connection) use ($pool){
    $pool->add($connection);
});

echo "Listening on {$socket->getAddress()}\n";

$loop->run();
{% endhighlight %}

This is how it looks in action: