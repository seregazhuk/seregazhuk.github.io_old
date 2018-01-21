---
title: "Asynchronous PHP: Why?"
tags: [PHP, AsyncPHP, Even-Driven Programming]
description: ""
---

Asynchronous programming is on demand today. Especially in web-development where responsiveness of the application plays a huge role. No one wants to waist their time and *wait* for a *freezing* application, while you are performing some database queries, sending an email or running some other potentially long-running tasks. Users want to receive responses to their actions, and they want them immediately. When your application becomes slow, you start loosing your client. Once a user has to deal with a freezing application, he or she simply closes it and never return. When the UI freezes from the user's point of view, it is not clear if your application is broken, or it is performing some long-running task and requires some time for it. 

## Responsiveness
Modern applications tend to be *responsive*, but some potentially long-running tasks or blocking operations such as network and filesystem I/O or database queries can significantly slow down your application. To prevent an application from being *blocked* by these operations we can run them  in background thus hiding the latency which they bring. So, an application stays *responsive* because it can continue with other work, for example, it can return the flow to UI or respond to other events.

## Parallelism vs Asynchronous

When running something asynchronously it means a non-blocking execution without waiting for completion. Instead parallelism means running multiple separate tasks at the same time as independent units of work.

Asynchronous:
>Do the task by **yourself** somewhere else and let me know when you are done and bring me the results (via a callback). By this time I can continue to run my own task. 

Asynchronous code requires handling dependencies between order of execution and this is done via callbacks. When some job is done it notifies another job what it has done. Asynchronous code mostly deals with time (order of events).


<p class="text-center image">
    <img src="/assets/images/posts/asyncronous-php-why/async-execution.png" alt="async-execution" class="">
</p>

Parallel:
>**Hire as many folks as you wish** and split the task between them to complete the task **quicker** and notify me when you are done (via callback). 

I might continue to do my other stuff or if the task is urgent I will stay here and wait until you come back with the results. Then I can combine the results from these guys. Parallel execution often requires more resources so it mostly depends on hardware.

<p class="text-center image">
    <img src="/assets/images/posts/asyncronous-php-why/parallel-execution.jpg" alt="parallel-execution" class="">
</p>

To illustrate the difference between asynchronous and parallel execution on real-world examples, we can compare two popular web servers: Apache and Nginx. They perfectly illustrate this difference: Nginx is asynchronous and event-based, while Apache uses parallel threads. Apache creates a new threads for every additional connection, so there is a maximum number of allowable connections depending on the available in the system memory. When this limit of connections is reached Apache refuses additional connections. The limiting factor in tuning Apache is memory (remember that parallel execution often depends on hardware). If a thread stops, the client waits for the response until the thread becomes free and so it can send a response.

Nginx works differently than Apache and it doesn't create new threads for each incoming request. It has a main worker process (or several workers, often a rule of thumb is to have one worker process for each CPU), which is single-threaded. This worker can handle thousands of concurrent connections. It does this asynchronously with one thread, rather than using multi-threaded parallel execution.

Most people when they see *asynchronous code* immediately think *Oh, it's cool! I can run my stuff in parallel!*. I may disappoint you but actually it is not true, concurrency and parallelism is not the same thing. It is commonly misunderstood thing, so let's try to understand why.

So, concurrency is a way to build things. It is a composition of independently executing things. Parallelism is a simultaneous execution of multiple things (they may be related and may not). In concurrency we are **dealing** with a lot of different things at once. Parallelism is **doing** a lot of things at once. It looks like the same but these are actually different ideas. Concurrency is about structure, while parallelism is about execution. 

Use can compare concurrency with I\O driver in your OS (mouse, keyboard, display). They all are managed by the operating system, but each of them is an independent thing inside the kernel. These things are concurrent, they can be parallel but not necessary. They don't need to run in parallel. So, to make concurrency work you have to create a communication between these independent parts to coordinate them.

## Why Bother On Back-End?

Now you can complain that on back-end you event don't care about responsiveness. You have all these nasty asynchronous JavaScript things on front-end and everything you server does is simply responds to the requests, so its front-end's job to provide responsiveness for a user not yours. Yes it's true, but back-end is not limited only with API responses. Sometimes you have to manage some complicated tasks, for example server for uploading videos. In this case may be responsiveness in not a key factor, but we come to *resources waisting* because an application has to wait. It waits for filesystem operations, for network communication, for database queries and so on. Often these I/O operations are extremely slow comparing to CPU calculations. And while we are slowly storing or reading a file, our CPU has to wait and do nothing else, instead of doing some useful job. As we have already considered instead of waiting we can run these tasks in background. How? Continue reading.

## Asynchronous PHP

JavaScript world already has an out-of-box support and solutions for writing asynchronous code. And we also have NodeJs which allows to write asynchronous back-end applications. In JavaScript we can use `setTimeout()` function to demonstrate some asynchronous code:

{% highlight js %}
setTimeout(function() {
    console.log('After timeout');
}, 1);

console.log('Before timeout');
{% endhighlight %}

When running this code we see the following:

{% highlight bahs %}
Before timeout
After timeout
{% endhighlight %}


`setTimeout()` functions **queues** the code to run once the current call stack is done. This means that we break the synchronous code flow and delay some execution. The second `console.log()` call is being executed before the queued one inside `setTimeout()` call. 

But what about PHP? Well, out-of-box PHP we don't have nice and friendly tools to write really asynchronous code. There is no `setTimeout()` equivalent and we can't simply delay or queue some code. That's why such frameworks as Amp and ReactPHP began to appear. Their main idea is to hide low-level language-specific details from us and provide high-level tools and mechanisms that can be used to write asynchronous code and manage concurrency.
