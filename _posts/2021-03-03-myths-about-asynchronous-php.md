---

title: "Myths About Asynchronous PHP: It Is Not Truly Asynchronous"
layout: "post"
description: "Demystifying wrong assumptions about asynchronous PHP"
tags: [PHP, AsyncPHP, Amp, ReactPHP]
image: "/assets/images/posts/asyncphp-myths/explaining.jpg"

---

Recently I had a lot of discussions about performance in PHP. Even though we have PHP 8, JIT, and
all other improvements, people still continue complaining about PHP. That it is a language only for
the request-response circle. That PHP is very slow and cannot be used in high-load systems. On the
one hand yes, it is true. If we want to build something really performant – the classic blocking PHP
is not the right choice. The majority of PHP functions and libraries are built to work in a
traditional blocking environment, which truth be told, is not about performance. Don't let it put
you down, and use another language to solve performance-sensitive tasks. PHP **can be fast**,
moreover, it can be really fast. How? We can have two reasons that can affect performance: when we
calculate something complex or when we have some blocking I/O. The first one cannot (or can?) be
solved in PHP and if you are trying to resolve 50+ nested GraphQL resolvers, maybe PHP is not the
best choice (just like NodeJs) for you. The second problem with a blocking I/O is not really a
problem for PHP (also just like for NodeJs). The community has been writing asynchronous PHP code
for years. And all these years while some small part of PHP community continues using and writing
asynchronous tools, the majority of PHP developers (and not only PHP) still consider asynchronous
PHP as something "wild". "
You might be really desperate if you write asynchronous code in PHP" - I have heard a lot of times.
Let's be honest, we have this bias that PHP is not the right tool for this sort of stuff. In most
cases, this bias is based on some wrong assumptions about PHP and about the word "asynchronous".
Wrong assumptions lead to wrong expectations, which leads to blaming asynchronous PHP that it is not
**"truly asynchronous"**.

In this article, I want to discuss some common wrong assumptions and believes about asynchronous
PHP. Before we start discussing asynchronous PHP it is essential to understand the basics. Let the
journey begin!

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
      <img src="/assets/images/posts/asyncphp-myths/theory.jpg">
    </p>
</div>

## Concurrency and parallelism

First, we need to understand the difference between synchronous and asynchronous execution. Let's
quickly revise it just to be sure that we are on the same page. And then we will move on to more
complex topics. Consider a program that makes two network requests. With a traditional synchronous
approach the code is executed sequentially:

- send the first request
- wait until the response is received
- send the second request
- wait until the second response is received

Here each operation blocks the flow. In most cases, this approach is fine. Problems come when we
have a lot of blocking operations, while performance is critical. The program doesn't utilize all
available resources, thus it may spend a lot of time just waiting and doing nothing. While waiting
for a network request to be finished (I/O bound operation) the CPU remains idle. And vise versa
while calculating something (CPU-bound operation) the program "freezes" and doesn't respond to the
input.

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
      <img src="/assets/images/posts/asyncphp-myths/sync-flow.png">
    </p>
</div>

The asynchronous approach offers a solution to blocking stuff. Asynchronous flow executes multiple
things at a time, and we don’t have to wait for the current task to finish before moving on to the
next one. An asynchronous operation is non-blocking and only initiates the operation. Continuing
with the previous example network requests now run concurrently. Also, it may seem like they run in
parallel (actually they don't).

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
        <img src="/assets/images/posts/asyncphp-myths/async-flow.png">
    </p>
</div>

The core problem when arguing about asynchronous PHP is actually the misunderstanding of what
concurrency means. Very often people confuse asynchronous execution with a parallel one. "PHP is not
truly asynchronous because we cannot execute things in parallel" - I have heard it many times. The
key difference is that concurrency is a much broader, general problem than parallelism.

With concurrent execution we have two tasks that can start, run, and complete in **overlapping**
time periods. It doesn't mean that they will be running at the same time. A very good example is
your computer. When we execute two programs (or one multi-threaded program) on a single-core CPU,
there is no way to run these programs in parallel. They have to share a single CPU time. So, the OS
decides to run one program first and then the other program. Or maybe it decides to run a small part
of one program and a small part of another program. The second program can start even before the
first one finishes.

In contrast, parallelism is when two tasks literally run at the same time. If we keep going with the
same example as above, imagine a multi-threaded program on a multicore processor. Parallel execution
requires hardware with multiple processing units. With a single-core CPU, you may achieve
concurrency but NOT parallelism. Parallelism is a specific kind of concurrency where tasks are
really executed simultaneously.

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
        <img src="/assets/images/posts/asyncphp-myths/concurrent-vs-parallel.png">
    </p>
</div>

It is clear from the foregoing that the application can be concurrent — but not parallel, which
means that it processes more than one task at the same time, but no two tasks are executing at the
same time instant.

Ok, now the difference between concurrency and parallelism is clear, but PHP is a single-threaded
programming language. Being single-threaded means that only one line of PHP code can be executed at
any time. Aha! PHP is not truly asynchronous! But, do we actually need to have multiple threads to
run our code asynchronously? To answer these questions we again need to dig into a theory and see
the difference between threads and processes.

## Threads and processes

We as programmers write code which is later executed by the computer. It doesn't matter what
language we use: C, Lisp or PHP. At the end of the day our code is compiled or interpreted into a
binary file. When we execute this binary code the program needs some resources from the OS to run:
memory address space, a PID (process ID) and some others. There can be multiple instances of the
same program running, each of which is a separate process within the OS. Switching from one process
to another requires some time for saving and loading CPU registers, memory, and other resources. All
processes are isolated. We can say that each process considers itself to be the only one running on
our computer and no other programs are running at the moment. You have definitely seen the situation
when one of your program "freezes", but you were able to quit it without affecting other programs.

So, the process starts, and it receives its own memory and resources. All threads within the process
share that memory and resources. Each process has at least one thread known as primary thread. Once
the primary thread is done with its execution, the process and the program itself exits. One can
treat a process as a container with a compiled code, memory and different OS resources.

<div class="row">
    <p class="text-center image col-sm-6 col-sm-offset-3">
        <img src="/assets/images/posts/asyncphp-myths/thread-process.png">
    </p>
</div>

## Single-threaded concurrency

Having multiple threads inside the process (multi-threaded processes) we can deal with multiple
things at once. Moreover, in most cases, we have systems with multiple processors or CPU cores,
where multiple processes or threads can be executed in parallel. This allows us to implement
concurrency in our programs.

But, it is important to understand that concurrency doesn't mean multi-threading. In many cases
(actually - most of the cases), single-threaded concurrency is the way to go. With all these
advantages of threads, multi-threaded programs may (will) become monsters overloaded with threads.
Yes, the cost of communication between threads is low, but the disadvantage is that a problem with
one thread within a process will certainly affect other threads, and the process itself (say
"hello" to synchronization and deadlocks).

The application performance depends on how optimally it uses available resources (CPU, memory, and
so on). Some operations in our program make take significant time to complete, and during this time,
we want to be able to do something else. That's where concurrency is needed. Let's look at the two
main reasons for operations to be time-consuming:

- CPU-bound operations involve heavy computations. They actually need CPU time.
- I/O-bound operations depend on network/hardware/user interaction/etc. They need time. Because they
  need to wait for something to happen.

With CPU bound blocking the thread is blocked because it is actively executed. For example, when
calculating something or rendering a 3d model. For CPU bound operations multithreading is preferred,
because on multi-processor systems, several threads may actually be executed simultaneously. This
way, higher overall performance is achieved.

However, with I/O bound operation the thread is blocked because it has to wait for data to return
from some I/O source (network, hard drive, etc). The OS sees that there is no data available at the
moment and thus puts the thread in a sleep state. In this case, the thread is not actively executed.
Truth be told it does nothing, but waits. Here multithreading is useless: creating several threads
to wait for some conditions to occur (a network response or filesystem) won't help those conditions
to occur faster. In fact, a single thread may wait for any of the specified conditions to occur and
do what is necessary for any of them.

Now, let's talk about PHP. In most cases, it is a language for web-applications, where we deal with
a lot of I/O: write something to the filesystem, do network requests or handle the console. Having
this in mind, we should consider that a single-threaded PHP is not a limitation for implementing
concurrency, but an opportunity.

## Non-blocking I/O

Having a single thread doesn't make our program asynchronous. Moreover, when we talk about I/O in
PHP it seems that PHP was created with an intention of being synchronous and blocking. All native
functions for handling I/O operations block the entire application.

- You read the file with `fopen()`? Application will be waiting.
- You query the database with `PDO`? Application freezes.
- Want to read something with `file_get_contents()`? Ah, you know the answer.

Blocking is not always bad. In PHP, we often don't think whether I/O in our application is blocking
or not. And truth be told, it is a very rare thing to have a non-blocking I/O in PHP applications,
especially when it comes to the type of I/O we usually do (HTTP requests, database queries, etc.).
In the request-response model, we need things to be blocking because it is the only way to know when
an operation is completed and there is a result. For example, we receive the request, query the
database, somehow process the result, render HTML or JSON, and return it back to the client as a
response. There is no room for non-blocking stuff here. In all these steps we need to wait. We need
results from the previous operation to continue. Non-blocking I/O is much more useful in server-side
code when dealing with potentially thousands of parallel client requests. Yes, of course, PHP is a
server-side code, but in front of it we always have Nginx or Apache. And these tools allow us to
write a blocking synchronous PHP code. In traditional PHP we always deal with a single HTTP request
and actually don't care if our code is blocking or not.

But, what if we want to implement an HTTP server in pure PHP? Or a socket server? What if we try to
implement a service in PHP which is intended to handle thousands of concurrent requests? I mean that
being able to write asynchronous code offers opportunities for creating a huge set of applications
that were unavailable in PHP before.

Yes, I already see the typical answer that "PHP was not designed for it". But what if I say that we
can. There are tools for it. You even don't need to install any additional extensions to run your
single-threaded PHP code concurrently.

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
        <img src="/assets/images/posts/asyncphp-myths/choice.jpeg">
    </p>
</div>

How? Instead of blocking native PHP functions (like `file_get_contents()`) for handling I/O, we can
use libraries (ReactPHP and Amp) that gives us high-level abstractions for implementing non-blocking
I/O in PHP. Having asynchronous non-blocking I/O we don't need many threads. The operating system
runs the I/O code in parallel for us. When our code calls a non-blocking API it doesn't wait for
this API to respond. PHP thread can immediately continue executing code that comes after this I/O
call. Once the OS is ready, and it’s time to send data back to PHP we will be notified. I know that
it sounds strange. Especially, having a traditional request-response model in mind. How does the
consumer of a non-blocking/asynchronous API gets notified? Is it a sort of signal? Or there is a
mechanism that is constantly checking whether the data is ready or not? When we think about a CPU
that executes instructions sequentially, how it could be possible that our program could listen for
an event once the data is ready? This is generally done with a callback that has access to the
resulting data. Most of operating system on which we work (Linux, Mac OS, Windows) have async
handlers, i.e. we can ask them to do something, and they will provide the result in a callback. Of
course, there are many other ways to express a non-blocking action with promises, coroutines, etc.
But under the hood, they are all based on a routine (a function) that is called once the data has
returned for I/O. OS has many threads, using which it helps to access different system resources. OS
can access the filesystem or make a network call in different threads. Thus, our PHP program only
delegates I/O-bound tasks to OS and then operates on results received in the callback.

The problem is that traditional "sequential" PHP-script cannot handle these callbacks. For example,
we want to make two concurrent HTTP requests in PHP:

{% highlight php %}
<?php

$client = new Browser();

$result1 = $client->get('http://google.com/');
$result2 = $client->get('https://github.com/reactphp');
{% endhighlight %}

Imagine this code where we want to make two concurrent requests. HTTP requests represent I/O-bound 
operations thus if we run this code asynchronously, they should be delegated to the OS. We start 
the first request, don't wait till it is done, and then immediately start the second one. Once 
the OS completes these requests our script will be notified. But... Do you see the problem here? 
Single-threaded PHP is executed line-by-line. Chances high that by the time requests are 
completed our script will exit. It just has nothing more to do. Again, here we don't wait for 
the response, we only start network requests. If we want to handle response we need two things:
 - An opportunity to listen to I/O events.
 - Not to exit the script if any I/O tasks are running in the background.

Both problems can be solved with the event loop. The previous example can be rewritten the 
following way:

{% highlight php %}
<?php

use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;

$loop = React\EventLoop\Factory::create();
$client = new Browser($loop);

$result1 = $client->get('http://google.com/');
$result2 = $client->get('https://github.com/reactphp');

$loop->run();
{% endhighlight %}

We have introduced a new object: instance of the event loop. I used ReactPHP loop implementation.
In the beginning of the script we create a loop, and at the end of the script we `run()` it.  This
is the thing that makes this PHP code asynchronous. On the last line the program doesn't exit, 
but starts listening to events. We have made two concurrent network requests, thus we need to wait
for responses. Moreover, this instruction actually doesn't send any network request:

{% highlight php %}
<?php

$result1 = $client->get('http://google.com/');
{% endhighlight %}

We only describe our intention to send a request. Only when the loop starts running the request 
will be sent. Wait... if requests are not sent... then what values are inside `$result1` and 
`$result2`? Are they both set to `null`? In the asynchronous (ReactPHP) world, when we need a value 
that is not available right now, we deal with **promises**. Consider promise as a placeholder 
for a future value. The underlying promises will resolve with received responses once network
requests complete.

{% highlight php %}
<?php
$printResponse = fn (ResponseInterface $response) => var_dump((string)$response->getBody());

$promise1 = $client->get('http://google.com/');
$promise2 = $client->get('https://github.com/reactphp');

$promise1->then($printResponse);
$promise2->then($printResponse);
{% endhighlight %}

I can add a handler to these promises and print responses as soon as they are available. How it 
works behind the scenes? The event loop under the hood is just an "endless" loop that listens to 
specific events and calls handlers for them. We start two I/O non-blocking tasks and tell the OS 
to make these network requests for us. That’s it. Then the execution flow can do something else. 
We started these tasks and do not wait until they are completed. Once the OS has received 
network responses it sends us an event with the data that has been received. A record of this 
event is added to the event queue. The execution thread takes the first event from the queue and 
calls the corresponding handler for this event. In our case we have added the same handler to 
both tasks – print the response body.

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
      <img src="/assets/images/posts/asyncphp-myths/event-loop.png">
    </p>
</div>

## Conclusion

All these things together: single-threaded PHP, non-blocking I/O with event-driven architecture can
easily make PHP asynchronous. Yes, currently there is no native high-level support for it in the
language, but there are libraries that can help us. Moreover, PHP can be asynchronous 
out-of-the-box without any additional extensions. At the moment the main problem is a lack of 
native support for high-level abstractions and I/O functions. We have been living in the 
request-response model for many years. And thus the majority of libraries that we have are 
intended to work in a blocking environment. But, we also see that the language evolves very 
quickly. Chances high that soon we will see the first steps for native support of asynchronous 
code in PHP (say"hello" to fibers).

<div class="row">
    <p class="text-center image col-sm-8 col-sm-offset-2">
      <img src="/assets/images/posts/asyncphp-myths/try-to-say.jpg">
    </p>
</div>

The purpose of this article is not to tell you that you can write anything on PHP. Of course, there
is no silver bullet and different tasks require different tools. As a developer, it is up to you to
decide whether PHP suits your task, or you need another language. I wanted to explain the way
asynchronous PHP works. That there is no magic inside and that asynchronous PHP is actually
asynchronous. It is not required to have multiple threads to run the code concurrently. Moreover, if
we are talking about PHP being single-threaded is an advantage here and not a limitation. 







