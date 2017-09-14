---
title: "Build A Simple Chat With ReactPHP Socket: Colored Output and Private Messages"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Continue building a simple chat with ReactPHP sockets, adding colored output and private messages"
---

The simple socket chat [that we have built before]({% post_url 2017-06-22-reactphp-chat-server %}) works fine but can be a little improved. Now there is no possibility to send a private message to someone in the chat. When someone sends a message everybody in the chat can see it. Also, it will be very nice to color different output messages in different colors according to their type. For example, when someone enters the chat this message is displayed in green color. When someone leaves - in red and so on. But before we start we should fix one bug. 

## Fixing Unique Names

We can't implement private messages before out chat allows two clients to have the same names. There is no way to identify a client and the private message will be sent to both clients if they have the same name:

<div class="row">
    <p class="image">
        <img src="/assets/images/posts/reactphp/socket-chat-names-duplicates.png" alt="socket-chat-names-duplicates" class="">
    </p>
</div>

The server should handle this situation and ask the client to enter a new name if the entered one already exists. 

>*You can find the source code for this server on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/socket/server.php).*

Let's refresh in memory how the server actually works. The main logic is placed in `ConnectionsPool` class, which handles all events related to the connections. All incoming connections are stored in `SplObjectStorage` property `$connections`. Every connection is actually an object which implements `use React\Socket\ConnectionInterface`. This object emits different events and the connection pool has two handlers for some events: 

- `data` when client sends some data to the server.
- `close` when client closes connection.

When we receive `data` event from the connection and there is no data in the `$connection` property associated with this connection we assume that the client is entering the chat. The data we receive from the client is his or her name. We add this connection to the storage and notify all other clients that a new member has entered the chat:

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
            // a users name.
            if(empty($connectionData)) {
                $this->sendJoinMessage($data, $connection);
                return;
            }

            $name = $connectionData['name'];
            $this->sendAll("$name: $data", $connection);
        });

        // ...
    }

    protected function sendJoinMessage($name, ConnectionInterface $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);
        $this->setConnectionData($connection, ['name' => $name]);
        $this->sendAll("User $name joins the chat\n", $connection);
    }
}
{% endhighlight %}

First of all `sendJoinMessage()` is not the best name for this method (we actually store a connection **and** send join message to the client). Let's rename it to `addNewMember()`:

{% highlight php %}
<?php

protected function addNewMember($name, ConnectionInterface $connection)
{
    $name = str_replace(["\n", "\r"], "", $name);
    $this->setConnectionData($connection, ['name' => $name]);
    $this->sendAll("User $name joins the chat\n", $connection);
}
{% endhighlight %}

Next step is to check if we already have a client with the specified name in a pool.
