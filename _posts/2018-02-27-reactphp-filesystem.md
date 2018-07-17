---
title: "Working With FileSystem In ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Working with files asynchronously in ReactPHP"
image: "/assets/images/posts/reactphp-filesystem/logo.jpg" 
---

I/O operations in the filesystem are often very slow, compared with CPU calculations. In an asynchronous PHP application this means that every time we access the filesystem even with a simple `fopen()` call, the event loop is being blocked. All other operations cannot be executed while we are reading or writing on the disk. As a rule of thumb:

>*In an asynchronous PHP application, we cannot use native PHP functions to access the filesystem.*

So, what is the solution? ReactPHP ecosystem already has a component that allows you to work asynchronously with a filesystem: [reactphp/filesystem](https://github.com/reactphp/filesystem){:target="_blank"}. This component provides a promise-based interface for the most commonly used operations within a filesystem.



<p class="text-center image">
    <img itemprop="image" src="/assets/images/posts/reactphp-filesystem/files.jpg" alt="files" class="">
</p>

## Files

Before we start working with files and directories we need to make some setup. First of all, like in any other ReactPHP application, we need an event loop. Next, we need to create an instance of the `\React\Filesystem\Filesystem` class:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$filesystem = \React\Filesystem\Filesystem::create($loop);
{% endhighlight %}

It is a sort of factory for all other objects that we may need: files and directories. To get an object that represents a file we can use `file($filename)` method:

{% highlight php %}
<?php

$loop = \React\EventLoop\Factory::create();
$filesystem = \React\Filesystem\Filesystem::create($loop);

$file = $filesystem->file('test.txt');
{% endhighlight %}

This method returns an instance of `React\Filesystem\Node\FileInterface`, which provides various methods for working with files.

### Reading

To asynchronously read the contents of the file call `getContents()` method, which returns a promise that fulfills with the contents of the file:

{% highlight php %}
<?php

$loop = Factory::create();
$filesystem = Filesystem::create($loop);

$file = $filesystem->file('test.txt');
$file->getContents()->then(function ($contents) {
    echo $contents . PHP_EOL;
});
{% endhighlight %}

And don't forget to call `$loop->run()` or nothing will happen. Behind the scenes this method opens a file in a reading mode, then starts reading this file and buffering its contents. Once, reading is done it fulfills its promise with this contents. It works like `file_get_contents()` but in an asynchronous way and doesn't block the loop. To prove this we can attach a timer to output a message every second. This timer represents some other performing task while we are reading a file. And then we start reading a huge file (in my case 40MB):

{% highlight php %}
<?php

$loop = Factory::create();
$filesystem = Filesystem::create($loop);

$file = $filesystem->file('test.txt');
$file->getContents()->then(function ($contents) {
    echo 'Reading completed' . PHP_EOL;
});

$loop->addPeriodicTimer(1, function () {
    echo 'Timer' . PHP_EOL;
});

$loop->run();
{% endhighlight %}

You can see that while we are reading the file the loop is not blocked and the timer works. It approximately takes 8 seconds to read the whole file:

<p class="image">
    <img src="/assets/images/posts/reactphp-filesystem/read-and-timer.gif" alt="read-and-timer" class="">
</p>

In case you want to work with the underlying stream, that provides the contents, you can use method `open($flags)`. Consider it as an asynchronous analog for native PHP `fopen()` function, it accepts the same [flags](http://php.net/manual/en/function.fopen.php){:target="_blank"}. This method returns a promise which fulfills with an instance of a stream (readable or writable depending on the mode you specified):

{% highlight php %}
<?php

$file->open('r')
    ->then(function ($stream) {
        $stream->on('data', function ($chunk) {
            echo 'Chunk read: ' . $chunk . PHP_EOL;
        });
    });
{% endhighlight %}

This snippet does the same as the previous one, but instead of buffering we have access to every received chunk of data.

### Creating a new file

Before writing the file, we should create one if it doesn't exist. There are three ways to do it. The first one is to create a file object and then call method `create()` on it. It returns a promise which fulfills once the file is being created. The promise rejects if a file with a specified name already exists:

{% highlight php %}
<?php

$file = $filesystem->file('new_created.txt');
$file->create()->then(function () {
    echo 'File created' . PHP_EOL;
});
{% endhighlight %}

Actually, method `create()` under the hood calls method `touch()`. `touch()` works as you expect: if there is no file with a specified name it creates this file and if such file exists - it does nothing. In this case, the returned promise fulfills if the file was created or it already exists:

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
$file->open('c')->then(function ($stream) {
    echo 'File created' . PHP_EOL;
});
{% endhighlight %}

Method `open()` opens the file and returns a promise which fulfills with a stream that can be read from or written to. The next snippet opens a file in a writable mode (`w`) and creates it (`c`) if it doesn't exist:

{% highlight php %}
<?php

$file = $filesystem->file('new_file.txt');
$file->open('cw')->then(function (React\Stream\WritableStreamInterface $stream) {
    // ...
});
{% endhighlight %}

In case we have opened file in a readable mode (`r`) the promise fulfills with an instance of `React\Stream\ReadableStreamInterface`:

{% highlight php %}
<?php

$file = $filesystem->file('new_file.txt');
$file->open('r')->then(function (React\Stream\ReadableStreamInterface $stream) {
    // ...
});
{% endhighlight %}

### Writing

To write something to the file you should open it in a writable mode and then just use an opened writable stream and `write()` data to it:

{% highlight php %}
<?php

$file = $filesystem->file('test.txt');
$file->open('cw')->then(function(React\Stream\WritableStreamInterface $stream) {
    $stream->write("Hello world\n");
    $stream->end();
    echo "Data was written\n";
});
{% endhighlight %}

We open a file via `open()` method and provide to flags: `c` to create a file if it doesn't exist and `w` to open this file in a writable mode. Then when a file is opened in the *onFulfilled* handler we get access to the stream which represents our file. In this handler, we can start writing to this stream.

>*If you are not familiar with ReactPHP streams and don't know how they work check [this article]({% post_url 2017-06-12-phpreact-streams %}){:target="_blank"}.*


>*Don't forget to call `close()` on the file, when you are done. Don't leave opened file descriptors.*

Also, there is a helper method `putContents()`. Which under the hood does the same what we have already done:

{% highlight php %}
<?php

$file = $filesystem->file('test.txt');
$file->putContents("Hello world\n")->then(function () {
    echo "Data was written\n";
});
{% endhighlight %}

One notice here: it implicitly calls `close()` method on the file and *closes* it. 

### Other methods

`rename($toFilename)` renames current file object to a specified name. Returns a promise that fulfills with an instance of a new renamed file:

{% highlight php %}
<?php

$filesystem->file('test.txt')->rename('new.txt')->then(function (FileInterface $file) {
    echo 'File was renamed to: ' . $file->getPath() . PHP_EOL;
});
{% endhighlight %}


`remove()` removes current file object. Returns a promise that fulfills once the file is removed:

{% highlight php %}
<?php

$filesystem->file('test.txt')->remove()->then(function () {
    echo 'File was removed' . PHP_EOL;
});
{% endhighlight %}

`stat()` returns a promise which fulfills with an associative array that contains information about the file.  The array structure is the same as [native PHP](http://php.net/manual/en/function.stat.php){:target="_blank"} `stat()` function returns:

{% highlight php %}
<?php

$filesystem->file('test.txt')->stat()->then(function ($stat) {
    print_r($stat);
});

/*
Array
(
    [dev] => 16777224
    [ino] => 23935210
    [mode] => 33188
    [nlink] => 1
    [uid] => 501
    [size] => 12
    [gid] => 80
    [rdev] => 0
    [blksize] => 4096
    [blocks] => 8
    [atime] => DateTime Object
        (
            [date] => 2018-02-20 13:07:43.000000
            [timezone_type] => 1
            [timezone] => +00:00
        )

    [mtime] => DateTime Object
        (
            [date] => 2018-02-19 06:10:17.000000
            [timezone_type] => 1
            [timezone] => +00:00
        )

    [ctime] => DateTime Object
        (
            [date] => 2018-02-19 06:10:17.000000
            [timezone_type] => 1
            [timezone] => +00:00
        )

)
*/
{% endhighlight %}

`time()` returns a promise which fulfills with an associative array that consists of three `DateTime` objects. Each object for the change time, access time, and modification time. Actually is a wrapper over the `stat()` method and returns only a *time part* from `stat()` array:

{% highlight php %}
<?php

$filesystem->file('test.txt')->time()->then(function ($time) {
    print_r($time);
});

/*
Array
(
    [atime] => DateTime Object
        (
            [date] => 2018-02-20 13:07:43.000000
            [timezone_type] => 1
            [timezone] => +00:00
        )

    [ctime] => DateTime Object
        (
            [date] => 2018-02-19 06:10:17.000000
            [timezone_type] => 1
            [timezone] => +00:00
        )

    [mtime] => DateTime Object
        (
            [date] => 2018-02-19 06:10:17.000000
            [timezone_type] => 1
            [timezone] => +00:00
        )

)
*/
{% endhighlight %}


`exists()` returns a promise which fulfills if the current file exists otherwise it rejects:

{% highlight php %}
<?php


$filesystem->file('test.txt')->exists()->then(function () {
    echo 'File exists' . PHP_EOL;
}, function () {
    echo 'File not found' . PHP_EOL;
});
{% endhighlight %}

`size()` returns a promise which fulfills with the size of the file in bytes:

{% highlight php %}
<?php
$filesystem->file('test.txt')->size()->then(function ($size) {
    echo 'Size is: '. $size . ' bytes' . PHP_EOL;
});
{% endhighlight %}

`chown($uid = -1, $gid = -1)` changes the owner of the file. This method accepts owner id and optional group id. Returns a promise that fulfills once the owner has been changed:

{% highlight php %}
<?php

$filesystem->file('test.txt')->chown(501)->then(function () {
    echo 'Owner changed' . PHP_EOL;
});
{% endhighlight %}

>*`501` is my current uid. To get your uid run `id -u` in your terminal.*

`chmod($mode)` changes the mode of the file. Parameter `$mode` is the same as native PHP `chmod()` [function](http://php.net/manual/en/function.chmod.php){:target="_blank"} has:

{% highlight php %}
<?php

$filesystem->file('test.txt')->chmod(755)->then(function () {
    echo 'Mode changed' . PHP_EOL;
});
{% endhighlight %}

<!-- ## Copying files

To asynchronously create a copy of a file use `copy()` method and provide a *copied to* file object. Notice, that this file should already exist: -->

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-filesystem/directories.jpg" alt="directories" class="">
</p>

## Directories

To create a directory object we use the same `FilesystemInterface` and method `dir()`:

{% highlight php %}
<?php

$loop = Factory::create();
$filesystem = Filesystem::create($loop);
$dir = $filesystem->dir(__DIR__);
{% endhighlight %}

This code creates a variable `$dir` which points to the current directory and is an instance of the `\React\Filesystem\Node\DirectoryInterface`:

{% highlight php %}
<?php

$dir = $filesystem->dir(__DIR__);
echo $dir->getPath(); // outputs full path to the current directory
{% endhighlight %}

### Listing 

Then, to list all contents of the directory we can use method `ls()`, which returns a promise that fulfills with an instance of [`SplObjectStorage`](http://php.net/manual/en/class.splobjectstorage.php){:target="_blank"} which represents a map of  `React\Filesystem\Node\Nodeinterface` objects (files and directories):

{% highlight php %}
<?php

$dir->ls()->then(function (SplObjectStorage $nodes) {
    foreach ($nodes as $node) {
        echo $node . PHP_EOL;
    }
});
{% endhighlight %}

The snippet above outputs the contents of the directory. Or if you need a promise which fulfills with an array of paths:

{% highlight php %}
<?php

$dir->ls()->then(function (SplObjectStorage $nodes) {
    $paths = [];
    foreach ($nodes as $node) {
        $paths[] = $node->getPath();
    }

    return $paths;
})->then(function ($paths) {
    print_r($paths);
});
{% endhighlight %}

Method `ls()` iterates only one level deep inside the directory. If you want to get the contents of all child directories recursively use `lsRecursive()`. The signature is the same with `ls()`: returns a promise which fulfills with an instance of `SplObjectStorage` that contains instances of `React\Filesystem\Node\Nodeinterface` objects:

{% highlight php %}
<?php

$dir->lsRecursive()->then(function (SplObjectStorage $nodes) {
    foreach ($nodes as $node) {
        echo $node . PHP_EOL;
    }
});
{% endhighlight %}

The snippet above outputs paths of all the inner nodes of the directory. All instances of `React\Filesystem\Node\Nodeinterface` implement magic `__toString()` method, which returns a path to the current node.

### Creating a new directory

Method `create()` creates a new directory. It returns a promise which fulfills once the directory is created or rejects if such directory already exists:

{% highlight php %}
<?php

$dir->create()->then(function () {
    echo 'Created' . PHP_EOL;
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
{% endhighlight %}

You can also create a set of embedded directories with `createRecursive()`:

{% highlight php %}
<?php

$filesystem = Filesystem::create($loop);
$dir = $filesystem->dir('new/test/test');

$dir->createRecursive()->then(function () {
    echo 'Created' . PHP_EOL;
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
{% endhighlight %}

>*Actually, all directory-related methods have appropriate recursive pairs: use method name and suffix `Recursive`.*

### Removing 

To remove an empty directory you can use `remove()` method. It returns a promise that fulfills once the directory is removed. The same promise rejects if the directory is not empty:

{% highlight php %}
<?php

$dir->remove()->then(function () {
    echo 'Removed' . PHP_EOL;
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
{% endhighlight %}

In case you need to remove the non-empty directory you can use `removeRecursive()`, which removes the directory and all its contents. 

### Size

Method `size()` can be useful in case you need to *count* contents of the directory. It returns a promise that fulfills with an associative array. This array contains the number of child directories, files and their total size in bytes:

{% highlight php %}
<?php

$dir->size()->then(function ($size) {
    echo 'Directories: ' . $size['directories'] . PHP_EOL;
    echo 'Files: ' . $size['files'] . PHP_EOL;
    echo 'Bytes: ' . $size['size'] . PHP_EOL;
});
{% endhighlight %}

Method `size()` goes only one level deep inside the directory. In case you need to get counters recursively use `sizeRecursive()`.

### Other methods

Directory object also has `stat()`, `chmod()`, `chown()` methods, which behaves exactly as their `File` analogs.
Also, all these methods have *recursive* implementations: `statRecursive()`, `chmodRecursive()` and `chownRecursive()` that does the same job but with all inner files and directories.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-filesystem/links.jpg" alt="links" class="">
</p>

## Symbolic Links

### Creating

To create a symbolic link from a specified path you should make to steps:
1. Get access to the current filesystem adapter.
2. Call `symlink($fromPath, $toPath)` method on it.

{% highlight php %}
<?php

$filesystem->getAdapter()
    ->symlink('test.txt', 'test_link.txt')
    ->then(function () {
        echo 'Link created' . PHP_EOL;
    });
{% endhighlight %}

Method `symlink($fromPath, $toPath)` creates a symbolic link for a specified `$fromPath` and names this link after the value provided via `$toPath`. This method returns a promise which fulfills once the link is created. In the snippet above we create a symbolic link `test_link.txt` which points to file `test.txt`.

### Reading
To resolve actual file link points to you can use `readlink($path)` of the filesystem adapter:

{% highlight php %}
<?php

$filesystem->getAdapter()
    ->readlink('test_link.txt')
    ->then(function ($path) {
        echo $path . PHP_EOL;
    });
{% endhighlight %}

Method `readlink($path)` returns a promise which fulfills with a path the link is pointing at.

### Removing 
To remove the link use method `unlink()` on filesystem adapter:

{% highlight php %}
<?php

$filesystem->getAdapter()
    ->unlink('test_link.txt')
    ->then(function() {
        echo 'Link removed' . PHP_EOL;
    }, function(Exception $e){
        echo $e->getMessage() . PHP_EOL;
    });
{% endhighlight %}

>*Method `unlink()` can also be applied to files, not only symbolic links.*

## Conclusion 
This tutorial has introduced ReactPHP [Filesystem Component](https://github.com/reactphp/filesystem) which allows you to work asynchronously with a filesystem in ReactPHP ecosystem. This component contains classes and interfaces to work with files, directories, and symbolic links. Filesystem I\O is blocking, so when you deal with files in your asynchronous ReactPHP application you **SHOULD** use [reactphp/filesystem](https://github.com/reactphp/filesystem){:target="_blank"}.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/filesystem){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}
