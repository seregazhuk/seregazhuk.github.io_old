---
title: "Build A Simple Chat With ReactPHP Socket: Server"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Build a simple chat with ReactPHP sockets, creating a socket server"
---


In this article, we are going to build a simple chat server based on [ReactPHP Socket Component](https://github.com/reactphp/socket). With this component, we can build simple async, streaming plaintext TCP/IP or a secure TLS socket server.

## Socket
> 
*A socket is one endpoint of a two-way communication link between two programs running on the network.*

There are client and server sockets. The server is bound to a specific port number and just waits listening on this port. The client knows the host of the server and the port on which the server is listening. When the connection between server and client is established, the data exchange begins.


<p class="text-center image">
    <img src="/assets/images/posts/reactphp/sockets.jpg" alt="cgn-edit" class="">
</p>

## Listening for New Connections

To build a server we need a socket for accepting the incoming connections. To create this socket we can use class `React\Socket\Server`. Its constructor accepts a server `$uri` and an instance of the event loop:

{% highlight php %}
<?php

require  'vendor/autoload.php';

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:8080', $loop);

echo "Listening on {$socket->getAddress()}\n";

$loop->run();
{% endhighlight %}
 
This server now is not very useful because everything it does is listening for the incoming connections on port `8080` of the `localhost`. But we are ready to accept incoming connections.

The instance of the `Server` implements `EventEmitterInterface` that means that we can listen to different events and react to them. When we have a new incoming connection the `connection` event will be emitted. In the handler for this event we have an access to the instance of the entire connection which implements `ConnectionInterface`:

{% highlight php %}
<?php

require  'vendor/autoload.php';

use React\Socket\ConnectionInterface;

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server('127.0.0.1:8080', $loop);

$socket->on('connection', function(ConnectionInterface $connection){
    $connection->write('Hi');
});

echo "Listening on {$socket->getAddress()}\n";

$loop->run();
{% endhighlight %}

Now our server becomes more friendly and sends `Hi!` to every incoming connection. We can test it from the console using telnet:

<p class="">
    <img src="/assets/images/posts/reactphp/server-hi.png" alt="cgn-edit" class="">
</p>

A connection object which is available in the handler also implements `EventEmitterInterface`, so we can start listening for some interesting events. Maybe the most useful will be the `data` event, which is emitted when a client sends some data to the server. You can receive this data in a handler:

{% highlight php %}
<?php

$connection->on('data', function($data) {
    // ...  
});
{% endhighlight %}

## Sending and Receiving Data

A connection works like a duplex (both readable and writable) stream, we can read the data from it (listen to `data` event) and we can write some data to it (via `write($data)` method). To test the things we can simply uppercase the incoming data and send it back to the client:

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

echo "Listening on {$socket->getAddress()}\n";

$loop->run();
{% endhighlight %}

The server becomes more interactive:

<p class="">
    <img src="/assets/images/posts/reactphp/server-uppercase.gif" alt="cgn-edit" class="">
</p>

The next step is to pass the data between different clients. To achieve this we need somehow to store active connections in a pool. Then when we receive `data` event, we can `write()` this data to all other connections in the pool. So we need to implement a simple pool which stores active connections and registers some event handlers on them. We will use an instance of `SplObjectStorage` to store incoming connections. When a new connection arrives we register the event handlers and then attach it to the pool. We are going to listen to two events: 

- `data` to send the received data from one connection to others
- `close` to remove the connection from the loop


<p class="text-center image">
    <img src="/assets/images/posts/reactphp/chat.jpg" alt="cgn-edit" class="">
</p>

Here is the source code of the `ConnectionsPool` class: 

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

The server itself now only listens to the `connection` event, when it is emitted we add a new connection to the pool. The pool attaches it to the storage, registers event handlers and sends a message to other connections that a new user enters the chat. When a connection closes we also notify other connections that someone leaves the chat.

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

<p class="">
    <img src="/assets/images/posts/reactphp/simple-chat-server.gif" alt="cgn-edit" class="">
</p>


## Storing Users Names

Now our chat is сompletely anonymous: we don't know who enters the chat, who leaves it, and event who writes messages. A сompletely anonymous chat isn't a very convenient way to communicate. That's why the next step is to ask a user the name when he or she connects and then use this name when sending data from this connection to other clients.

To achieve this we can store some data received from the connection. Instead of `SplObjectStorage::attach()` we can use `SplObjectStorage::offsetSet()` method to store the some data associated with a connection:

{% highlight php %}
<?php

use React\Socket\ConnectionInterface;

class ConnectionsPool {

    /** @var SplObjectStorage  */
    protected $connections;

    // ...

    protected function setConnectionData(ConnectionInterface $connection, $data)
    {
        $this->connections->offsetSet($connection, $data);
    }

    protected function getConnectionData(ConnectionInterface $connection)
    {
        return $this->connections->offsetGet($connection);
    }
}
{% endhighlight %}

Then we need to modify adding a new connection to the pool. For every new connection, we keep an empty array and send a user a message asking for the name:

{% highlight php %}
<?php

class ConnectionsPool {

    // ...

    public function add(ConnectionInterface $connection)
    {
        $connection->write("Enter your name: ");
        $this->initEvents($connection);
        $this->setConnectionData($connection, []);
    }

    // ...

}

{% endhighlight %}

The last step is to modify `data` and `close` handlers. When we receive some data from a connection we check if we already have a name associated with this connection. If there is no name we assume that this data is the name, save it and send a message to all other connections that a user with this name has entered the chat:

{% highlight php %}
<?php

/**
 * @param ConnectionInterface $connection
 */
protected function initEvents(ConnectionInterface $connection)
{
    // On receiving the data we loop through other connections
    // from the pool and write this data to them
    $connection->on('data', function ($data) use ($connection) {
        $connectionData = $this->getConnectionData($connection);

        // It is the first data received, so we consider it as
        // a user's name.
        if(empty($connectionData)) {
            $this->sendJoinMessage($data, $connection);
            return;
        }

        $name = $connectionData['name'];
        $this->sendAll("$name: $data", $connection);
    });

    // ... close handler   
});

protected function sendJoinMessage($name, $connection)
{
    $name = str_replace(["\n", "\r"], "", $name);
    $this->setConnectionData($connection, ['name' => $name]);
    $this->sendAll("User $name joins the chat\n", $connection);
}
{% endhighlight %}

When a connection closes we get the name associated with this connection, detach this connection from the pool and send to all other connections a message that a user with this name has left a chat:

{% highlight php %}
<?php

/**
 * @param ConnectionInterface $connection
 */
protected function initEvents(ConnectionInterface $connection)
{
    // ... data handler

    // When connection closes detach it from the pool
    $connection->on('close', function() use ($connection){
        $data = $this->getConnectionData($connection);
        $name = $data['name'] ?? '';

        $this->connections->offsetUnset($connection);
        $this->sendAll("User $name leaves the chat\n", $connection);
    });
});

{% endhighlight %}

Here is a full source code of the `ConnectionsPool` class. The code for the server stays the same:

{% highlight php %}
<?php

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
        $connection->write("Enter your name: ");
        $this->initEvents($connection);
        $this->setConnectionData($connection, ['name' => '']);
    }

    /**
     * @param ConnectionInterface $connection
     */
    protected function initEvents(ConnectionInterface $connection)
    {
        // On receiving the data we loop through other connections
        // from the pool and write this data to them
        $connection->on('data', function ($data) use ($connection) {
            $connectionData = $this->getConnectionData($connection);

            // It is the first data received, so we consider it as
            // a user's name.
            if(empty($connectionData)) {
                $this->sendJoinMessage($data, $connection);
                return;
            }

            $name = $connectionData['name'];
            $this->sendAll("$name: $data", $connection);
        });

        // When connection closes detach it from the pool
        $connection->on('close', function() use ($connection){
            $data = $this->getConnectionData($connection);
            $name = $data['name'] ?? '';

            $this->connections->offsetUnset($connection);
            $this->sendAll("User $name leaves the chat\n", $connection);
        });
    }

    protected function sendJoinMessage($name, $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);
        $this->setConnectionData($connection, ['name' => $name]);
        $this->sendAll("User $name joins the chat\n", $connection);
    }

    protected function setConnectionData(ConnectionInterface $connection, $data)
    {
        $this->connections->offsetSet($connection, $data);
    }

    protected function getConnectionData(ConnectionInterface $connection)
    {
        return $this->connections->offsetGet($connection);
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

The same chat in action but now with names:

<p class="">
    <img src="/assets/images/posts/reactphp/simple-chat-server-with-names.gif" alt="cgn-edit" class="">
</p>


## Conclusion

It was a quick introduction to [ReactPHP Socket Component](https://github.com/reactphp/socket) and its two classes: `React\Socket\Server` and `React\Socket\Connection`. We have created a very simple chat server to demonstrate their basic usage and how to handle such basic events, such as `data`, `connection`, and `close`. Our server accepts new connections and stores them in the pool. Each connection has some data associated with it. Also when a new connection arrives we register some handlers on it. Sockets allow us to react and to handle these events separately for each connection.

Of course, the *server part* of this component is not limited to these two classes. For example, you can create a [TCP server](https://github.com/reactphp/socket#tcpserver) for accepting plaintext TCP/IP connections, or a [secure TLS server](https://github.com/reactphp/socket#secureserver). Use the [documentation](https://github.com/reactphp/socket#advanced-server-usage) for more advanced examples of the server socket.

Continue reading with a [chat client on sockets]({% post_url 2017-06-24-reactphp-chat-client %}).
<hr>
You can find s source code of this server on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/socket/server.php).

<strong>Other ReactPHP articles:</strong>

- [Event Loop And Timers]({% post_url 2017-06-06-phpreact-event-loop %})
- [Streams]({% post_url 2017-06-12-phpreact-streams %})
- [Promises]({% post_url 2017-06-16-phpreact-promises %})
- [Sockets: client]({% post_url 2017-06-24-reactphp-chat-client %})
