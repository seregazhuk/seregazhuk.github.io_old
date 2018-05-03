---
title: "Having Fun With ReactPHP Child Processes"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Having Fun With ReactPHP Child Processes"
image: "/assets/images/posts/reactphp/child-process-ping-with-timer.gif"
---

Consider this simple HTTP server:

{% highlight php %}
<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";

$loop->run();
{% endhighlight %}

A very simple server that consists of one middleware, which just sends `200` `Hello world` responses to all incoming requests.

>*If you are not familiar with ReactPHP middleware check [this post]({% post_url 2017-12-20-reactphp-http-middleware %}).*

Now, let's say that we need to send an email from our asynchronous application. The most common example is an error notification system. When some error occurs the server sends you an email with an error. ReactPHP `React\Socket\Server` class emits `error` event when an exception is being thrown inside of one of its midlleware:

{% highlight php %}
<?php

// ...

$server->on('error', function (Exception $exception) {
    echo $exception->getMessage();
});
{% endhighlight %}

To check this let's throw an exception before returning a response:

{% highlight php %}
<?php

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    throw new Exception('Error');
    return new Response(200, ['Content-Type' => 'text/plain'],  "Hello world\n");
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);
$server->on('error', function (Exception $exception) {
    echo $exception->getMessage();
});

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";

$loop->run();
{% endhighlight %}

When you start this server and open its address in browser you will see `Error 500: Internal Server Error` message. But the terminal with a running server will have a logged error:

Actually, printing error messages in terminal is not very useful for production. Unlikely you will constantly look through the server logs. Instead it will be more productive to send an email with an error.

## Using SwiftMailer

In PHP we already have a popular package - [SwiftMailer](https://swiftmailer.symfony.com/docs/introduction.html). Install it via composer:

{% highlight bash %}
composer require "swiftmailer/swiftmailer"
{% endhighlight %}

Then to send a message we need to update the `error` event handler:

{% highlight php %}
<?php

$server->on('error', function (Exception $exception) {
    $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
        ->setUsername('username@gmail.com')
        ->setPassword('yourpassword');

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    // Create a message
    $message = (new Swift_Message('Wonderful Subject'))
        ->setFrom(['noreply@myhttpserver.com' => 'John Doe'])
        ->setTo(['username@gmail.com',])
        ->setBody($exception->getMessage());

    // Send the message
    $mailer->send($message);
});
{% endhighlight %}

I've provided a basic `SwiftMailer` setup for sending messages via GMail. 

>*For more detailed description about using `SwiftMailer` please visit its [official docs](https://swiftmailer.symfony.com/docs/introduction.html).*


But in our case we have one huge problem with this error handler. `SwiftMailer` library is blocking. That means that when you call `$mailer->send($message);` the loop and the whole server wait till your message is being sending. 

In asynchronous application there are two ways how we can handle blocking calls:
- we rewrite them using new non-blocking code
- we fork this calls and continue asynchronously

In this tutorial we consider the second approach: creating a child process and send email inside of it.

## Creating A Child Process

ReactPHP Child Processes is a separate package, so we need to install it:

{% highlight bash %}
composer require react/child-process
{% endhighlight %}

Before creating a child process we need to separate email-sending script from the server. Let's extract this code into a separate file `send-error.php`:

{% highlight php %}
<?php

require '../vendor/autoload.php';

$transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
    ->setUsername('username@gmail.com')
    ->setPassword('yourpassword');

// Create the Mailer using your created Transport
$mailer = new Swift_Mailer($transport);

// Create a message
$message = (new Swift_Message('Wonderful Subject'))
    ->setFrom(['noreply@myhttpserver.com' => 'John Doe'])
    ->setTo('username@gmail.com')
    ->setBody($exception->getMessage());

// Send the message
$mailer->send($message);
{% endhighlight %}

And for simplicity let's temporary replace `$exception->getMessage()` call with a hard-coded string (later it will be fixed). Now, in the server code we need to instantiate a child process and start it:

{% highlight php %}
<?php

$server->on('error', function (Exception $exception) use ($loop) {
    $process = new Process('php send-error.php');
    $process->start($loop);
});
{% endhighlight %}

Constructor of `React\ChildProcess\Process` class accepts a string which is an `sh` command that you want to fork. In our case we want to run `send-error.php` script with PHP:

{% highlight php %}
<?php

$process = new Process('php send-error.php'); 
{% endhighlight %}    

Then, to start the process we call method `start()` with an event loop.

Now, each time when an error occurs our server will start a child process with an `sh` command `php send-error.php` and nothing blocks the loop. That allows the server to continue processing incoming requests without waiting for email message to be sent. 

## Passing Data Between Parent And Child

The last thing we need to do is to pass the error message inside the child process. It can be achieved with environment variables. When creating a new child process via `new Process('some-command')` we can provide additional parameters to the constructor:

{% highlight php %}
<?php

namespace React\ChildProcess;

class Process extends EventEmitter 
{
   /**
    * Constructor.
    *
    * @param string $cmd     Command line to run
    * @param string $cwd     Current working directory or null to inherit
    * @param array  $env     Environment variables or null to inherit
    * @param array  $options Options for proc_open()
    * @throws RuntimeException When proc_open() is not installed
    */
    public function __construct($cmd, $cwd = null, array $env = null, array $options = array())
    {
        // ...
    }
}
{% endhighlight %}

Here, we are interested in the third parameter `$env`. By default the child process inherits environment variables from its parent, but this behavior may be changed. We can provide a custom array with our own environment variables and pass an exception message as `error` environment variable:

{% highlight php %}
<?php

$server->on('error', function (Exception $exception) use ($loop) {
    $process = new Process("php send-error.php", null, ['error' => $exception->getMessage()]);
    $process->start($loop);
});
{% endhighlight %}

In the child process you can get access to the passed environment variables via `$_ENV` super global. But depending on you [ini settings](http://us.php.net/manual/en/ini.core.php#ini.variables-order) it may be empty, in this case `$_SERVER` super global is more reliable:

{% highlight php %}
<?php

// send-error.php

// ...

$message = (new Swift_Message('Error in MyHTTPServer'))
    ->setFrom(['noreply@myhttpserver.com' => 'John Doe'])
    ->setTo('username@gmail.com')
    ->setBody($_SERVER['error']);
{% endhighlight %}

## Conclusion

And that's it. When an error occurs the server emits `error` event. `Error` event handler creates a new child process with an exception message as `error` environment variable. The process starts and the flow control moves back to the event loop which continues processing incoming requests. At the same moment inside the child process an email with error message is being sent. 
As a rule of thumb:
>*If you cannot rewrite something in an asynchronous way - fork it.*
