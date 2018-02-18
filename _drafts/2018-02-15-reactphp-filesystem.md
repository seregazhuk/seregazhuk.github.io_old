---
title: "Working With FileSystem In ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Working with files asynchronously in ReactPHP"
---

I/O operations in the filesystem are often very slow, comparing with CPU calculations. In an asynchronous PHP application this means that every time we access the filesystem even with a simple `fopen()` call, the event loop is being blocked. All other operations cannot be executed while you are reading or writing on the disk. As a rule of thumb:

>*in an asynchronous PHP application we cannot use native PHP function to access the filesystem.*

So, what is the solution? ReactPHP ecosystem already has a component that allows you to work asynchronously with a filesystem: [reactphp/filesystem](https://github.com/reactphp/filesystem){:target="_blank"}. This component provides a promise-based interface for all available operations with a filesystem.



## Files

Before we start working with files and folders we need to make some setup. First of all, like in any other ReactPHP application we need an event loop. Next, we need to create an instance of the `\React\Filesystem\Filesystem` class:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$filesystem = \React\Filesystem\Filesystem::create($loop);
{% endhighlight %}

It is a sort of factory for all other object that we may need: files and directories. To get an object that represent a file we can use `file($filename)` method, which returns a promise that fulfills with the contents of the file:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$filesystem = \React\Filesystem\Filesystem::create($loop);

$file = $filesystem->file('test.txt');
{% endhighlight %}

This method returns an instance of `React\Filesystem\Node\FileInterface`, which provides various method for working with files.

### Reading

To asynchronously read the contents of the file call `getContents()` method:

{% highlight php %}
<?php

$loop = Factory::create();
$filesystem = Filesystem::create($loop);

$file = $filesystem->file('test.txt');
$file->getContents()->then(function($chunk) {
    echo $chunk . PHP_EOL;
});
{% endhighlight %}

And don't forget to call `$loop->run()` or nothing will happen. Behind the scenes this method opens a file in a reading mode, then starts reading this file and buffering it contents. Once, reading is done it resolves its promise with this contents. It works like `file_get_contents()` but in an asynchronous way and doesn't block the loop. To prove this we can attach a timer to output a message every second. This timer represents some other performing task while we are reading a file. And then we start reading a huge file (in my case 40MB of repeated `Hello world` lines):

{% highlight php %}
<?php

$loop = Factory::create();
$filesystem = Filesystem::create($loop);

$file = $filesystem->file('test.txt');
$file->getContents()->then(function($chunk) {
    echo $chunk . PHP_EOL;
});

$loop->addPeriodicTimer(1, function(){
    echo 'Timer' . PHP_EOL;
});

$loop->run();
{% endhighlight %}

You can see that while we are reading the file the loop is not blocked and the timer works. It approximately takes 8 seconds to read to whole file:

<p class="image">
    <img src="/assets/images/posts/reactphp-filesystem/read-and-timer.gif" alt="read-and-timer" class="">
</p>

In case you want to work with the underlying stream, that provides the contents, you can use method `open($flags)`. Consider it as an asynchronous analog for native PHP `fopen()` function, it accepts the same [flags](http://php.net/manual/ru/function.fopen.php). This method returns a promise which fulfills with an instance of a stream (readable or writable depending on the mode you specified):

{% highlight php %}
<?php

$file->open('r')
    ->then(function($stream) {
        $stream->on('data', function($chunk) {
            echo 'Chunk read' . PHP_EOL;
        });
    });
{% endhighlight %}

This snippet does the same as the previous one, but instead of buffering we have access to every received chunk of data.

### Creating a new file

But before writing the file, we should create one if it doesn't exist. There are three ways to do it. The first one is to create a file object and then call method `create()` on it. It returns a promise which is fulfilled once the file is being created. The promise rejects if a file with a specified name already exists:

{% highlight php %}

$file = $filesystem->file('new_created.txt');
$file->create()->then(function () {
    echo 'File created' . PHP_EOL;
});
{% endhighlight %}

Actually method `create()` behind the hood calls method `touch()`. `touch()` works as you expect: if there is no file with a specified name it creates this file, if file exists - it does nothing. In this case returned promise fulfills if file was created or it already exists:

{% highlight php %}
<?php

$file = $filesystem->file('new_created.txt');
$file->touch()->then(function () {
    echo 'File created or exists' . PHP_EOL;
});
{% endhighlight %}

The third approach to create a file is to use `open()` method and provide `c` (*create*) flag:

{% highlight php %}
<?php

$file = $filesystem->file('new_file.txt');
$file->open('c')->then(function () {
    echo 'File created' . PHP_EOL;
});
{% endhighlight %}

Method `open()` opens the file and returns a promise which fulfills with a stream that can be read from or written to. The next snippet opens a file in a writable mode (`w`) and creates it (`c`) if it doesn't exist:

{% highlight php %}
<?php

$file = $filesystem->file('new_file.txt');
$file->open('cw')->then(function () {
    // ...
});
{% endhighlight %}

### Writing
