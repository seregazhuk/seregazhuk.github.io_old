---
title: "Managing Child Processes With ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Managing Child Processes With ReactPHP"
image: "/assets/images/posts/reactphp/child-process-ping-with-timer.gif"
---

ReactPHP [Child Process Component](http://reactphp.org/child-process/#child-process-component){:target="_blank"} enables an access to Operating System functionalities by running any system command inside a child process. We have access to that child process input stream and can listen to its output stream. For example, we can pass arguments to the command or *pipe* its output to another command as its input.

First of all, let's create an instance of the `React\ChildProcess\Process` class. Our first command is going to ping Google: `ping 8.8.8.8`. 

{% highlight php %}
<?php
use React\ChildProcess\Process;

$process = new Process('ping 8.8.8.8');
{% endhighlight %}

There are two basic operations you can perform on a process: you can start or terminate it. To start a process we need an instance of the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}){:target="_blank"} to pass it to the `start()` method:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('ping 8.8.8.8');

$process->start($loop);
$loop->run();
{% endhighlight %}

As it was said earlier ReactPHP takes care of the process input and output streams. So even if we run this example from the command line there will be no output of the `ping` command in the terminal.

## Process I/O
We need to setup the process input and output streams ourselves. Let's start with some basic stuff and simply echo the process output to the console. An instance of the `Process` class has three public properties for managing the basic I/O of the process:

{% highlight php %}
<?php

namespace React\ChildProcess;

class Process extends EventEmitter
{
    public $stdin;
    public $stdout;
    public $stderr;

    // ...
}
{% endhighlight %}

Each of this properties is an instance of the `React\Stream\Stream` so we can use them as [ReactPHP streams]({% post_url 2017-06-12-phpreact-streams %}){:target="_blank"}. For `stdout` we can listen for `data` event and `echo` it:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('ping 8.8.8.8');

$process->start($loop);
$process->stdout->on('data', function($data) {
    echo $data;
});
$loop->run();
{% endhighlight %}

Now it is a working example of a child process implemented with ReactPHP. We have a running `ping` command and its output is being placed to the terminal via PHP:

<p class="">
    <img src="/assets/images/posts/reactphp/child-process-ping.gif" alt="child-process-ping" class="">
</p>

The process can also receive data from the parent. We can use `stdin` property and `write()` some data directly to the child process input stream. This is a simple *Hello world* example where we start PHP interactive shell and then *type* a code string which is immediately executed:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('php -a');

$process->start($loop);
$process->stdout->on('data', function($data){
    echo $data;
});

$process->stdin->write("echo 'Hello World';\n");
$loop->run();
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/child-process-input.png" alt="child-process-input" class="">
</p>

>**Notice** that these I/O properties will be populated with `React\Stream\Stream` objects only after calling `start()` on the `Process` instance.

Here is a bit of the `Process` class source code:

{% highlight php %}
<?php

namespace React\ChildProcess;

class Process extends EventEmitter
{
    public $stdin;
    public $stdout;
    public $stderr;

    // ...

    public function start(LoopInterface $loop, $interval = 0.1)
    {
        // ... 
        $this->stdin  = new Stream($this->pipes[0], $loop);
        // ...
        $this->stdout = new Stream($this->pipes[1], $loop);
        // ...
        $this->stderr = new Stream($this->pipes[2], $loop);
        // ...
    }
}
{% endhighlight %}

So, if you try to access these properties before calling `start()` you will get an error:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('ping 8.8.8.8');

// !!! stdout is not initialized here !!!
$process->stdout->on('data', function($data) {
    echo $data;
});

$process->start($loop);
$loop->run();
{% endhighlight %}

The code above will cause a fatal error: `Uncaught Error: Call to a member function on() on null`. Keep this in mind when working with a child process I/O.

Since a child process `stdin` property is a writable stream and `stdout` property is a readable one we can `pipe()` input/output of multiple processes on each other. We use one command's output as an input for another command. 

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$ls = new Process('ls'); // list files in current directory
$wc = new Process('wc -l'); // counts number of lines

$ls->start($loop);
$wc->start($loop);

$ls->stdout->pipe($wc->stdin);

$wc->stdout->on('data', function($data) {
    echo "Total number of files and folders: " . $data;
});

$loop->run();
{% endhighlight %}

In the example above, the child process executes `ls` command which lists all files and folders in the current directory. Then we pipe it's output into another child process `wc -l` command which counts a number of lines. When executed this code prints a total number of files and folders in the current directory.

>Since PHP uses the shell wrapper for all commands we can use the shell syntax to execute the command. So the previous example can be rewritten like this:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('ls | wc -l');

$process->start($loop);

$process->stdout->on('data', function($data){
    echo "Total number of files and folders :" . $data;
});

$loop->run();

{% endhighlight %}

## Termination

`ping` command is going to execute until we stop it. To stop a child process from its parent we call `terminate()` method. To demonstrate it we use a simple timer like this:

{% highlight php %}
 <?php

 $loop->addTimer(3, function() use ($process) {
    $process->terminate();
});
{% endhighlight %} 

An instance of the `Process` class implements `EventEmitter` interface. This means that we can register handlers for events on this object. When a process is terminated it emits `exit` event. In the next example we execute `ping` command during 3 seconds, then when the child process is being finished, the event loop stops and the parent script exits:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('ping 8.8.8.8');

$process->start($loop);
$process->stdout->on('data', function($data){
    echo $data;
});

$loop->addTimer(3, function() use ($process) {
    $process->terminate();
});

$process->on('exit', function($exitCode, $termSignal) {
    echo "Process exited";
});

$loop->run();
{% endhighlight %}

<p class="">
    <img src="/assets/images/posts/reactphp/child-process-ping-with-timer.gif" alt="child-process-ping-with-timer" class="">
</p>

The handler for the `exit` event gives us the `$exitCode` for the child process and the `$termSignal`. The `$termSignal` variable is `null` when the child process exits normally.

## Process PID

Often when we deal with child processes it is useful to know their PID (process identifier). `Process` class has `getPid()` method, which can be used for sending signals to it. For example, one more way to terminate a process - is to send a `kill` signal to it:

{% highlight php %}
<?php

$loop = Factory::create();
$process = new Process('ping 8.8.8.8');

$process->start($loop);
$process->stdout->on('data', function($data) use ($loop) {
    echo $data;
});

$process->on('exit', function($exitCode, $termSignal) use ($loop) {
    echo "Process exited with signal: $termSignal";
    $loop->stop();
});

$loop->addTimer(3, function() use ($process, $loop){
    $pid = $process->getPid();
    echo "Sending KILL signal to PID: $pid\n";
    (new Process("kill {$pid}"))->start($loop);
});
$loop->run();

{% endhighlight %}

 In this example, we again start `ping 8.8.8.8` process. Then we attach a timer which retrieves a child process PID and creates a new child process to `kill` the first one. We have also registered a handler for the `exit` event to check if the process has actually received the signal:

<p class="">
    <img src="/assets/images/posts/reactphp/child-process-pid.gif" alt="child-process-pid" class="">
</p>

<hr>

It was a basic overview of the [ReactPHP Child Process](http://reactphp.org/child-process/#child-process-component){:target="_blank"} component - a library for executing child processes in PHP. 

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/child-process){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
