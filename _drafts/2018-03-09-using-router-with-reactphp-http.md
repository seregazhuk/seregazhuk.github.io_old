---
title: "Using Router With ReactPHP Http Component"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Using fast-router with ReactPHP Http Component"
---

Router defines the way your application responds to a client request to a specific endpoint which is defined by an URI (or path) and a specific HTTP request method (`GET`, `POST`, etc.). With ReactPHP [Http component](http://reactphp.org/http/){:target="_blank"} we can create an asynchronous [web server]{% post_url 2017-07-17-reatcphp-http-server %}{:target="_blank"}. But out of the box the component doesn't provide any routing, so you should use third-party libraries in case you want to create a web-server with a routing system. 

## Manual Routing
Of course, we can create a simple routing system ourselves. We start with a simple "Hello world" server:

{% highlight php %}
<?php

use React\Http\Server;
use React\Http\Response;
use React\EventLoop\Factory;
use Psr\Http\Message\ServerRequestInterface;

$loop = Factory::create();

$server = new Server(function (ServerRequestInterface $request) {
    return new Response(200, ['Content-Type' => 'text/plain'],  'Hello world');
});

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";

$loop->run();
{% endhighlight %}

This is the most primitive server. It responds the same way to all incoming requests (regardless of the path and method). Now, let's add two more endpoints: one for GET request and path `/tasks` and one for `POST` request and the same path. The first one returns all tasks, the second adds a new one. Also, for all other requests we return `404 Not found.`. The tasks will be stored as an in-memory array. To detect the current path and method we use `$request` object:

{% highlight php %}
<?php

$tasks = [];

$server = new Server(function (ServerRequestInterface $request) use (&$tasks) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();
    
    // ...
});
{% endhighlight %}

The next step is to add conditions for each endpoint. The first endpoint returns a `200` response (`OK`) with a list of stored tasks:

{% highlight php %}
<?php

$tasks = [];

$server = new Server(function (ServerRequestInterface $request) use (&$tasks) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if($path === '/tasks') {
        if($method === 'GET') {
            return new Response(200, ['Content-Type' => 'text/plain'],  implode(PHP_EOL, $tasks));
        }

        if($method === 'POST') {
            // ...
        }
    }

    return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
});
{% endhighlight %}

In case of `POST` request we need to write some logic. We expect a new task from the request body. If there is a `task` field in the request body, we get it, store in `$tasks` array and return `201` response (`Created`). If there is no such field as a bad request and return an appropriate response:

{% highlight php %}
<?php

$tasks = [];

$server = new Server(function (ServerRequestInterface $request) use (&$tasks) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if($path === '/tasks') {
        if($method === 'GET') {
            return new Response(200, ['Content-Type' => 'text/plain'],  implode(PHP_EOL, $tasks));
        }

        if($method === 'POST') {
            $task = $request->getParsedBody()['task'] ?? null;
            if($task) {
                $tasks[] = $task;
                return new Response(201);
            }

            return new Response(400, ['Content-Type' => 'text/plain'], 'Task field is required');
        }
    }

    return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
});
{% endhighlight %}

You see that already with two endpoints the code doesn't look nice with all these nested conditions. And while it grows with new endpoints this code will become a real mess. Let's figure out how we can refactor it and make it a bit cleaner.

## Middleware As Routes

The callback with our logic is a middleware, a sort of a request handler. We can create a handler for each endpoint and the pass these handlers as an array to the `Server` constructor. Let's try this out. 

>*I'm not going to cover middleware in this article. If you are not familiar with middleware in ReactPHP check [this post]({% post_url 2017-12-20-reactphp-http-middleware %}){:target="_blank"}.*

We are going to have three middlewares:
- List all tasks
- Add a new task
- 404 not found.

### List All Tasks

{% highlight php %}
<?php

$listTasks = function (ServerRequestInterface $request, callable $next) use ($tasks) {
    if($request->getUri()->getPath() === '/tasks' && $request->getMethod() === 'GET') {
        return new Response(200, ['Content-Type' => 'text/plain'], implode(PHP_EOL, $tasks));
    }
    
    return $next($request);
};
{% endhighlight %}

### Add A New Task

{% highlight php %}
<?php

$addTask = function (ServerRequestInterface $request, callable $next) use (&$tasks) {
    if($request->getUri()->getPath() === '/tasks' && $request->getMethod() === 'POST') {
        $task = $request->getParsedBody()['task'] ?? null;
        if(!$task) {
            return new Response(400, ['Content-Type' => 'text/plain'], 'Task field is required');
        }

        $tasks[] = $task;
        return new Response(201);
    }

    return $next($request);
};
{% endhighlight %}


### Not Found

{% highlight php %}
<?php

$notFound = function () {
    return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
};
{% endhighlight %}

### Combining All Together

Now having all middleware done we can provide an array of middleware in the `Server` constructor:

{% highlight php %}
<?php

$server = new Server([
    $listTasks,
    $addTask,
    $notFound
]);
{% endhighlight %}

This may look cleaner than *all code in one callback*, but now all middlware have these *path and method checks*. It actually doesn't look like routing, it looks as it is: several requests handlers. It is not clear what route - goes where. We have to look through all these handlers to collect a complete picture of the routes.

## Using Fast-Router

Now, you have seen that we need a router to remove this mess with path and method checks. For this purpose I have chosen [FastRoute](https://github.com/nikic/FastRoute){:target="_blank"} by [Nikita Popov](https://twitter.com/nikita_ppv){:target="_blank"}.

Install the router via composer:

{% highlight bash %}
composer require nikic/fast-route
{% endhighlight %}

The main idea of using a third-party router is to take these *URI and method checkings* out of middleware and move them to the router. This will clean our middleware from conditionals. Also, we can remove `callable $next`:

{% highlight php %}
<?php

$listTasks = function () use ($tasks) {
    return new Response(200, ['Content-Type' => 'text/plain'],  implode(PHP_EOL, $tasks));
};

$addTask = function (ServerRequestInterface $request) use (&$tasks) {
    $task = $request->getParsedBody()['task'] ?? null;
    if(!$task) {
        return new Response(400, ['Content-Type' => 'text/plain'], 'Task field is required');        
    }

    $tasks[] = $task;
    return new Response(201);
};
{% endhighlight %}


Next step is to create a *dispatcher*. The disaptcher 

## Using Wildcards

Until now we had very simple routes. Let's say that we want a certain task by a specified id: `/tasks/123`. How can we implement this? First of all we need a new middleware for it:

{% highlight php %}
<?php

$viewTask = function(ServerRequestInterface $request) use ($tasks) {
    // ...
};

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($listTasks, $addTask, $viewTask) {
    $r->addRoute('GET', '/tasks', $listTasks);
    $r->addRoute('GET', '/tasks/{id:\d+}', $viewTask);
    $r->addRoute('POST', '/tasks', $addTask);
});
{% endhighlight %}

But this is not enough. We somehow need to extract an actual task id, that was passed in the URI. 
