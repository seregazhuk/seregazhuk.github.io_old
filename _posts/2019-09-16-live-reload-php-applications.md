---

title: "Live Reloading PHP Applications With Nodemon"
layout: post
description: "How to reload your PHP application when changing its source code"
tags: [PHP, ReactPHP, Development]
image: "/assets/images/posts/live-reload-php-with-nodemon/logo-cool.jpg" 

---

When building a traditional web application in PHP we don't care about reloading it. We make some changes in the source code, save it, then make the request in the browser or some other client and we can see these changes. They have already applied automatically because of the nature of PHP, its request-response model. On every new request each time we bootstrap the whole application.

But what if we are building another sort of application: a long-running script. It may be an HTTP server or some other service listening for incoming requests. Having this kind of applications each time we make a change we need to reload the application: manually stop it and then run the script again. It's not very fun, so it would be nice to avoid having to do this work over and over again.

For this, we can use a package called [nodemon](https://github.com/remy/nodemon). Actually, this tool was built to help in developing NodeJs applications, but it can be easily used with PHP. It monitors the source and restarts the application, once we change something. Let's say we have an asynchronous ReactPHP HTTP server. Just a dummy example like this:

{% highlight php %}
<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

// init the event loop
$loop = Factory::create();

// set up the components
$server = new Server(function (ServerRequestInterface $request) {
    return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world");
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";

// run the application
$loop->run();
{% endhighlight %}

Each time we change something in this code, we need to manually restart the server for changes to take place. It is very annoying. So, we go ahead and install nodemon globally:

{% highlight bash %}
npm install -g nodemon
{% endhighlight %}

By default, nodemon works with JavaScript environment, but we can configure it and adapt it to PHP. Create a config file `nodemon.json`. Inside create a JSON object:

{% highlight js %}
{
    "verbose": false,
    "ignore": [
        ".git",
        ".idea"
    ],
    "execMap": {
        "php": "php"
    },
    "restartable": "r",
    "ext": "php"
}
{% endhighlight %}

What's happening in the snippet above? We don't need `verbose` mode and we ignore Git and PHPStorm files. Nodemon doesn't support PHP by default, so with `execMap`
I want to define my own default executables. Here we tell nodemon that all files with `.php` extension should use `php` as the executable. Then we specify the extension watch list, we monitor changes only in PHP files. Ok, the config file is done. 

Let's say we have an entry point to our long-running script `server.php`. Now, instead of typing `php server.php`, we type `nodemon server.php`. And internally it starts our server. 

{% highlight bash %}
> nodemon server.php
> [nodemon] 1.19.1
> [nodemon] to restart at any time, enter `r`
> [nodemon] watching: *.*
> [nodemon] starting `php server.php`
> Listening on http://127.0.0.1:8000
{% endhighlight %}

If I go back to the `server.php` file and try to change the response from a plain text to JSON:

{% highlight php %}
<?php

// ...

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(
        200, 
        ['Content-Type' => 'application/json'],  
        json_encode(["message" => "Hello world"])
    );
});

{% endhighlight %}

Now, if I save this file, you see that nodemon restarts the server.

{% highlight bash %}
> nodemon server.php
> [nodemon] 1.19.1
> [nodemon] to restart at any time, enter `r`
> [nodemon] watching: *.*
> [nodemon] starting `php server.php`
> Listening on http://127.0.0.1:8000
> [nodemon] restarting due to changes...
> [nodemon] starting `php server.php`
> Listening on http://127.0.0.1:8000

{% endhighlight %}

It watches the files and it has seen that I have changed `server.php`. It also works for files in subdirectories. So, this is really convenient, there is no need to manually stop the server and restart it, whenever we make a change.
