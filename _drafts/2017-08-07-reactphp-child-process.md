---
title: "Managing Child Processes With ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Managing Child Processes With ReactPHP"
---

ReactPHP [Child Process Component](http://reactphp.org/child-process/#child-process-component) enables an access to Operating System functionalities by running any system command inside a child process. We have access to that child process input stream and can listen to its output stream. For example, we can pass arguments to the command or *pipe* its output to another command as its input.

First of all, let's create an instance of the `React\ChildProcess\Process` class. Our first command is going to ping Google: `ping 8.8.8.8`. 

{% highlight php %}
<?php
use React\ChildProcess\Process;

$process = new Process('ping 8.8.8.8');
{% endhighlight %}

There are two basic operations you can perform on a process: to start or to terminate it. To start a process we need an instance of the [event loop]({% post_url 2017-06-06-phpreact-event-loop %}) being passed to `start()` method:

{% highlight php %}
<?php

use React\EventLoop\Factory;
use React\ChildProcess\Process;

$loop = Factory::create();
$process = new Process('ping 8.8.8.8');

$process->start($loop);
$loop->run();
{% endhighlight %}

As it was said earlier ReactPHP takes care of the process input and output streams. So even if we run this example from the command line the will be no output of the `ping` command in the terminal.

We need to setup input and output streams ourselves. Let's start with some basic stuff and simply echo the process output to the console. An instance of the `Process` class has three public properties for managing the basic I/O of the process:

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

Each of this properties is an instance of the `React\Stream\Stream` so we can use them as [ReactPHP streams]({% post_url 2017-06-12-phpreact-streams %}). For `stdout` we can listen for `data` event and `echo` it:

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

**Notice** that these I/O properties will be populated with `React\Stream\Stream` objects only after calling `start()` on the `Process` instance :

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

So, if you try to access these properties before calling `start` you will get an error:

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

The code above will cause a fatal error: `Uncaught Error: Call to a member function on() on null`. Keep this in mind working with a child process I/O.

`ping` command is going to execute untill we stop it. To stop a child process from its parent we call `terminate()` method. To demontrate it we use a simple timer like this:

{% highlight php %}
 <?php

 $loop->addTimer(3, function() use ($process) {
    $process->terminate();
});
{% endhighlight %} 

When process is terminated it emmits `exit` event. In the next example we execute `ping` command during 3 seconds, then when the child process is being finished we also stop the event loop and exit the parent script:

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

$process->on('exit', function() use ($loop) {
    echo "Process exited";
    $loop->stop();
});

$loop->run();
{% endhighlight %}


<p class="">
    <img src="/assets/images/posts/reactphp/child-process-ping-with-timer.gif" alt="child-process-ping-with-timer" class="">
</p>
