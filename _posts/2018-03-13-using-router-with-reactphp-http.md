---
title: "Using Router With ReactPHP Http Component"
tags: [PHP, Event-Driven Programming, ReactPHP, Routing]
layout: post
description: "Using FastRoute with ReactPHP Http Component"
image: "/assets/images/posts/reactphp-http-with-router/http-with-router.jpg" 
---

<iframe width="95%" height="600" src="https://www.youtube.com/embed/qHV0GATf2zY" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>

Router defines the way your application responds to a client request to a specific endpoint which is defined by  URI (or path) and a specific HTTP request method (`GET`, `POST`, etc.). With ReactPHP [Http component](http://reactphp.org/http/){:target="_blank"} we can create an asynchronous [web server]({% post_url 2017-07-17-reatcphp-http-server %}){:target="_blank"}. But out of the box the component doesn't provide any routing, so you should use third-party libraries in case you want to create a web-server with a routing system. 

## Manual Routing
Of course, we can create a simple routing system ourselves. We start with a simple *"Hello world"* server:

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

This is the most primitive server. It responds the same way to all incoming requests (regardless of the path and method). Now, let's add two more endpoints: one for GET request and path `/tasks` and one for `POST` request and the same path. The first one returns all tasks, the second adds a new one. Also, for all other requests, we return `404 Not found`. For simplicity tasks will be stored as an in-memory array. To detect the current path and method we use `$request` object:

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

    if ($path === '/tasks') {
        if ($method === 'GET') {
            return new Response(200, ['Content-Type' => 'text/plain'],  implode(PHP_EOL, $tasks));
        }

        if ($method === 'POST') {
            // ...
        }
    }

    return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
});
{% endhighlight %}

In case of `POST` request, we need to write some logic. We expect a new task from the request body. If there is a `task` field in the request body, we get it, store in `$tasks` array and return `201` response (`Created`). If there is no such field we consider it as a bad request and return an appropriate response:

{% highlight php %}
<?php

$tasks = [];

$server = new Server(function (ServerRequestInterface $request) use (&$tasks) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if ($path === '/tasks') {
        if ($method === 'GET') {
            return new Response(200, ['Content-Type' => 'text/plain'],  implode(PHP_EOL, $tasks));
        }

        if ($method === 'POST') {
            $task = $request->getParsedBody()['task'] ?? null;
            if ($task) {
                $tasks[] = $task;
                return new Response(201);
            }

            return new Response(400, ['Content-Type' => 'text/plain'], 'Task field is required');
        }
    }

    return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
});
{% endhighlight %}

You see that already with two endpoints the code doesn't look nice with all these nested conditions. And while the application grows with new endpoints this code will be in a real mess. Let's figure out how we can refactor it and make it a bit cleaner.

## Middleware As Routes

The callback with our logic is a middleware, a sort of a request handler. We can create a handler for each endpoint and then pass these handlers as an array to the `Server` constructor. Let's try this out. 

>*I'm not going to cover middleware in this article. If you are not familiar with middleware in ReactPHP check [this post]({% post_url 2017-12-20-reactphp-http-middleware %}){:target="_blank"}.*

We are going to have three middlewares:
- List all tasks
- Add a new task
- 404 not found.

### List All Tasks

{% highlight php %}
<?php

$listTasks = function (ServerRequestInterface $request, callable $next) use (&$tasks) {
    if ($request->getUri()->getPath() === '/tasks' && $request->getMethod() === 'GET') {
        return new Response(200, ['Content-Type' => 'text/plain'], implode(PHP_EOL, $tasks));
    }
    
    return $next($request);
};
{% endhighlight %}

### Add A New Task

{% highlight php %}
<?php

$addTask = function (ServerRequestInterface $request, callable $next) use (&$tasks) {
    if ($request->getUri()->getPath() === '/tasks' && $request->getMethod() === 'POST') {
        $task = $request->getParsedBody()['task'] ?? null;
        if (!$task) {
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

This may look cleaner than *all code in one callback*, but now all middleware have these *path and method checks*. It actually doesn't look like routing: just several requests handlers. It is not clear what route - goes where. We have to look through all these handlers to collect a complete picture of the routes.

## Using FastRoute

Now, you have seen that we need a router to remove this mess with path and method checks. For this purpose, I have chosen [FastRoute](https://github.com/nikic/FastRoute){:target="_blank"} by [Nikita Popov](https://twitter.com/nikita_ppv){:target="_blank"}.

Install the router via composer:

{% highlight bash %}
composer require nikic/fast-route
{% endhighlight %}

### Clearing Middleware

The main idea of using a third-party router is to take these *URI and method checkings* out of middleware and move them to the router. This will clean our middleware from conditionals. Also, we can remove `callable $next`:

{% highlight php %}
<?php

$listTasks = function () use (&$tasks) {
    return new Response(200, ['Content-Type' => 'text/plain'],  implode(PHP_EOL, $tasks));
};

$addTask = function (ServerRequestInterface $request) use (&$tasks) {
    $task = $request->getParsedBody()['task'] ?? null;
    if (!$task) {
        return new Response(400, ['Content-Type' => 'text/plain'], 'Task field is required');        
    }

    $tasks[] = $task;
    return new Response(201);
};
{% endhighlight %}


### Defining Routes

Next step is to create a *dispatcher*. The dispatcher is created by `FastRoute\simpleDispatcher` function. To define the routes you provide a callback with `FastRoute\RouteCollector()` as an argument. Then you use this *collector* to define the routes. Here is an example:

{% highlight php %}
<?php

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($listTasks, $addTask) {
    $routes->addRoute('GET', '/tasks', $listTasks);
    $routes->addRoute('POST', '/tasks', $addTask);
});
{% endhighlight %}

In the snippet above we define two routes: to list all tasks and to add a new one. For each route, we call `addRoute()` method on an instance of `FastRoute\RouteCollector`. We provide a request method, path and a handler (a callable) to be called when this route is being matched. We need to store the result of `FastRoute\simpleDispatcher()` function in `$dispatcher` variable. Later we will use it to get a corresponding route for a specified path and request method.

### Route dispatching
And now is the most interesting part - dispatching. We need to somehow match the requested route and get back the handler, that should be called in the response to the requested path and method. This can be a separate middleware or we can inline it right in the `Server` constructor. For the simplicity let's inline it:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($dispatcher) {
    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            return new Response(405, ['Content-Type' => 'text/plain'], 'Method not allowed');              
        case FastRoute\Dispatcher::FOUND:
            return $routeInfo[1]($request);
    }

    throw new LogicException('Something went wrong in routing.');
});

{% endhighlight %}

The dispatcher has just one method `dispatch()`, which accepts a request method and URI and returns a plain array. The length of the array may differ, but it always contains at least one element. The first element of this array (`$routeInfo[0]`) represents the result of dispatching. It can be one of three possible values. All these values are defined as constants in `FastRoute\Dispatcher` interface:

{% highlight php %}
<?php

namespace FastRoute;

interface Dispatcher
{
    const NOT_FOUND = 0;
    const FOUND = 1;
    const METHOD_NOT_ALLOWED = 2;

    // ...
}
{% endhighlight %}

So, we dispatch the route and start checking the result. In case of `FastRoute\Dispatcher::NOT_FOUND` we return a `404` response. In case of `FastRoute\Dispatcher::METHOD_NOT_ALLOWED` we return `405` response. And when we have `FastRoute\Dispatcher::FOUND` `$routeInfo` array contains the second element (`$routeInfo[1]`). This is the handler which was previously defined for this route. In our case this handler is a middleware, so can execute it with an instance of the `ServerRequestInterface` and return the result of this execution:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($dispatcher) {
    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

    switch ($routeInfo[0]) {
        // ...
        case FastRoute\Dispatcher::FOUND:
            return $routeInfo[1]($request);
    }
});

{% endhighlight %}

Now, we have separated our middleware from the routing. Middleware don't know the exact route which invokes them. Middleware contain only the *business logic*.

## Route With Parameters (Using Wildcards)

Until now we had very simple routes. The real application always has more complex routes that may contain wildcards. Let's say that we want to view a certain task by a specified id: `/tasks/123`. As an ID of the task, we use its index in the `$tasks` array. If there is a task with a specified index in the `$tasks` array we return it, otherwise, we return a `404` response. How can we implement this? 

First of all, we need a new middleware for viewing the task by id and a new route for it:

{% highlight php %}
<?php

$viewTask = function(ServerRequestInterface $request, $taskId) use (&$tasks) {
    if (isset($tasks[$taskId])) {
        return new Response(200, ['Content-Type' => 'text/plain'], $tasks[$taskId]);
    }

    return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
};


$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($viewTask, $listTasks, $addTask) {
    $r->addRoute('GET', '/tasks/{id:\d+}', $viewTask);
    $r->addRoute('GET', '/tasks', $listTasks);
    $r->addRoute('POST', '/tasks', $addTask);
});
{% endhighlight %}

Notice that a new route has a wildcard `{id:\d+}` which means path `/tasks/` followed by any number. But this is not enough. We need to somehow extract an actual task id, that was passed within the URI. All matched wildcards and their values can be found in the third element of the array which is being returned by `$dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath())` call. It is an associative array with wildcards and corresponding values. For routes without wildcards it will be empty. 

>*The more detailed explanation for defining routes can be found at [nikic/FastRoute docs](https://github.com/nikic/FastRoute#defining-routes).*

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($dispatcher) {
    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            return new Response(405, ['Content-Type' => 'text/plain'], 'Method not allowed');               
        case FastRoute\Dispatcher::FOUND:
            return $routeInfo[1]($request, ... array_values($routeInfo[2]));
    }
});
{% endhighlight %}

## Refactoring: Extracting a Class

The definition of the dispatcher looks a bit ugly. When using `FastRoute\simpleDispatcher()` function we are forced to declare routes inside the closure. And it means that we have to inject all the dependencies inside the closure. And that makes the code messy and hard to understand. Instead we can go OOP and create our own Router. It will be responsible for dispatching a route and call a corresponding controller. Create class `Router` with a magic method `__invoke()`:

{% highlight php %}
<?php

use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;

final class Router
{
    private $dispatcher;

    public function __construct(RouteCollector $routes)
    {
        $this->dispatcher = new GroupCountBased($routes->getData());
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $routeInfo = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::NOT_FOUND:
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
            case FastRoute\Dispatcher::FOUND:
                $params = $routeInfo[2];
                return $routeInfo[1]($request, ... array_values($params));
        }

        throw new LogicException('Something wrong with routing');
    }
}
{% endhighlight %}

Inside the constructor we instantiate a dispatcher with a collection of routes `FastRoute\RouteCollector` (we will create it soon). Method `__invoke()` now contains dispatching logic. Now, we move back to the main script and create a collection of routes:

{% highlight php %}
<?php

// ...

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;

// ...

$routes = new RouteCollector(new Std(), new GroupCountBased());
{% endhighlight %}

We are not going to dive into details here. `RouteCollector` requires a parser for routes and a data generator. And we provide these objects for it (`FastRoute\RouteParser\Std` and `FastRoute\DataGenerator\GroupCountBase`). Actually, this happens inside `FastRoute\simpleDispatcher()` function under the hood.

Now, we can define our routes without any callbacks in a declarative way:

{% highlight php %}
<?php

// ...

$routes = new RouteCollector(new Std(), new GroupCountBased());
$routes->get('/tasks', $listTasks);
$routes->get('/tasks/{id:\d+}', $viewTask);
$routes->post('/tasks', $addTask);
{% endhighlight %}

Notice, that we have also replaced `addRoute()` calls with more explicit `get()` and `post()`. Looks much better, yeah? Then inside the server instantiate a router with defined routes:

{% highlight php %}

// ...
$server = new Server(new Router($routes));
{% endhighlight %}

## Conclusion
When building a web application on top of ReactPHP you can face a problem with defining routes. In case of something very simple, you can simply add checking right inside your request handlers. But when you are building something complex with many different routes it is better to add a third-party router and let it do the job. In this particular article, we have touched [FastRoute](https://github.com/nikic/FastRoute){:target="_blank"} by [Nikita Popov](https://twitter.com/nikita_ppv){:target="_blank"}, but you can easily replace it with the router of your own choice.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/http-with-router){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.

{% include book_promo.html %}

