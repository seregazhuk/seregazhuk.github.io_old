---
title: "Build A Simple Chat With ReactPHP Socket: Colored Output and Private Messages"
layout: post
tags: [PHP, Event-Driven Programming, ReactPHP]
description: "Continue building a simple chat with ReactPHP sockets, adding colored output and private messages"
---

>*This article is the continue of the previous articles where we were building a simple console chat on ReactPHP sockets ([server]({% post_url 2017-06-22-reactphp-chat-server %}) and [client]({% post_url 2017-06-24-reactphp-chat-client %})). If you skipped them it will be difficult to understand the examples and code snippets in this article.*


The simple socket chat [that we have built before]({% post_url 2017-06-22-reactphp-chat-server %}) works fine but can be a little improved. Now there is no possibility to send a private message to someone in the chat. When someone sends a message everybody in the chat can see it. Also, it will be very nice to color different output messages in different colors according to their type. For example, when someone enters the chat this message is displayed in green color. When someone leaves - in red and so on. But before we start we should fix one bug. 

## Fixing Unique Names

We can't implement private messages before out chat allows two clients to have the same names. There is no way to identify a client and the private message will be sent to both clients if they have the same name:

<p class="">
    <img src="/assets/images/posts/reactphp/socket-chat-names-duplicates.png" alt="socket-chat-names-duplicates" class="">
</p>

The server should handle this situation and ask the client to enter a new name if the entered one already exists. 

>*You can find the source code for this server on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/blob/master/socket/server.php).*

Let's refresh in memory how the server actually works. We create an instance of the event loop and a socket. Then the main logic is placed in `ConnectionsPool` class, which handles all events related to the connections. When a server receives a new connection we add it to a pool:

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

All incoming connections are stored in `ConnectionsPool` class in property `$connections` with type `SplObjectStorage`. Every connection is actually an object which implements `use React\Socket\ConnectionInterface`. This object emits different events and the connection pool has two handlers for some events: 

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
                $this->addNewMember($data, $connection);
                return;
            }

            $name = $connectionData['name'];
            $this->sendAll("$name: $data", $connection);
        });

        // ...
    }

    protected function addNewMember($name, ConnectionInterface $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);
        $this->setConnectionData($connection, ['name' => $name]);
        $this->sendAll("User $name joins the chat\n", $connection);
    }
}
{% endhighlight %}

Now, we need to add a check if there is already a client with a specified name in a pool:

{% highlight php %}
<?php

protected function checkIsUniqueName($name)
{
    foreach ($this->connections as $obj) {
        $data = $this->connections->offsetGet($obj);
        $takenName = $data['name'] ?? '';
        if($takenName == $name) return false;
    }

    return true;
}
{% endhighlight %}

We loop through all the stored connections. For each connection object grab the data associated with it and check if we already have a specified name. Then update `addNewMember()` method to use this check:

{% highlight php %}
<?php

protected function addNewMember($name, ConnectionInterface $connection)
{
    $name = str_replace(["\n", "\r"], "", $name);

    if(!$this->checkIsUniqueName($name)) {
        $connection->write("Name $name is already taken!\n");
        $connection->write("Enter your name: ");
        return;
    }

    $this->setConnectionData($connection, ['name' => $name]);
    $this->sendAll("User $name joins the chat\n", $connection);
}
{% endhighlight %}

If a specified name is already taken we return an appropriate message and ask to reenter the name:

<p class="">
    <img src="/assets/images/posts/reactphp/socket-chat-names-duplicates-fix.gif" alt="socket-chat-names-duplicates-fix" class="">
</p>

When issue is fixed we can continue on implementing new features.

## Colored Output

Let's add some colors to the output, to outline different events in the chat:

- Someone leaves the chat.
- Someone enters the chat.
- Warning that a specified username is already taken.

To color the output in the console we can use ANSI escape sequences. On most terminals (Linux and OSX) it is possible to colorize output using the \033 ANSI escape sequence. Each color has its own number. For example, to display a line in red we can simply echo this:

{% highlight php %}
<?php

echo "\033[31m some colored text \033[0m some white text \n";
{% endhighlight %}

First we use an escape `\` character to define an output color: `\033`. Then we open a *color statement* with `[31m]`. Everything after that will be outputted in a different color (red in our case). The last step is to close a *color statement* with `\033[0m]` to come back to a default console color.

I don't want to put this colors related logic into the `ConnectionsPool` class, so let's create a helper `Output` class. It is responsible for formatting messages and adding escape sequences to color the output:

{% highlight php %}
<?php

class Output {
    public static function warning($message)
    {
        return self::getColoredMessage("0;32", $message);
    }

    public static function info($message)
    {
        return self::getColoredMessage("1;33", $message);
    }

    private static function getColoredMessage($hexColor, $message)
    {
        return "\033[{$hexColor}m{$message}\033[0m";
    }
}
{% endhighlight %}

Now we can use this helper class to return colored messages to the client. At first when we have a new member trying to enter the chat. If a specified name is already taken we return to the client a warning message, otherwise we send an info message to other clients.

{% highlight php %}
<?php

class ConnectionsPool {
    // ...

    protected function addNewMember($name, ConnectionInterface $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);

        if(!$this->checkIsUniqueName($name)) {
            $connection->write(Output::warning("Name $name is already taken!") . "\n");
            $connection->write("Enter your name: ");
            return;
        }

        $this->setConnectionData($connection, ['name' => $name]);
        $this->sendAll(Output::info("User $name joins the chat") . "\n", $connection);
    }

    // ...
}
{% endhighlight %}

Then in `initEvents()` method we modify line where we send a message that a client has left the chat and replace this string with a warning message:

{% highlight php %}
<?php

class ConnectionsPool {
    // ...

    /**
     * @param ConnectionInterface $connection
     */
    protected function initEvents(ConnectionInterface $connection)
    {
        // ... 

        // When connection closes detach it from the pool
        $connection->on('close', function() use ($connection){
            $data = $this->getConnectionData($connection);
            $name = $data['name'] ?? '';

            $this->connections->offsetUnset($connection);
            $this->sendAll(Output::warning("User $name leaves the chat") . "\n", $connection);
        });
    }

    // ...
}

{% endhighlight %}

Now, let's try it in action:

<p class="">
    <img src="/assets/images/posts/reactphp/socket-chat-with-colors.gif" alt="socket-chat-with-colors" class="">
</p>

Awesome! Much better than the previous boring and gray chat!

## Private Messages

To send a private message to a user we are going to use `@` symbol. For example, to send a private message to a user with name `Mike` you should type the following:

{% highlight bash %}
@Mike: some secret message
{% endhighlight %}

We assume that everything between `@` and `:` symbols is a user's name. Everything after `:` is considered as a message. So this command will send a private message `some secret message` to a user with name `Mike`. Let's implement this. 

Before we start I would like to extract a method for retrieving a connection by a user name:

{% highlight php %}
<?php

class ConnectionsPool {

    // ...

    /**
     * @param string $name
     * @return null|ConnectionInterface
     */
    protected function getConnectionByName($name)
    {
        /** @var ConnectionInterface $connection */
        foreach ($this->connections as $connection) {
            $data = $this->connections->offsetGet($connection);
            $takenName = $data['name'] ?? '';
            if($takenName == $name) return $connection;
        }

        return null;
    }
}
{% endhighlight %}

Then we can remove `checkIsUniqueName()` method and use a new one to check if a user with a specified name already exists:

{% highlight php %}
<?php

class ConnectionsPool {

    // ...

    protected function addNewMember($name, ConnectionInterface $connection)
    {
        $name = str_replace(["\n", "\r"], "", $name);

        if(!$this->getConnectionByName($name)) {
            $connection->write(Output::warning("Name $name is already taken!") . "\n");
            $connection->write("Enter your name: ");
            return;
        }

        $this->setConnectionData($connection, ['name' => $name]);
        $this->sendAll(Output::info("User $name joins the chat") . "\n", $connection);
    }
}

{% endhighlight %}
