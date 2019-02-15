---
title: "Working With MySQL Asynchronously In ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP, MySQL, API]
layout: post
---

Today we will be looking at creating a RESTful API using ReactPHP, MySQL and nikic/FastRoute. Let's look at the API we want to build and what it can do.

## Application

We are going to build the API that:

- Handles CRUD operations on a resource (we are going to use `users`)
- Uses the proper HTTP verbs to make it RESTful (GET, POST, PUT, and DELETE)
- Returns JSON data

All of this is pretty standard for RESTful APIs. Feel free to switch out users for anything you want for your application (orders, products, customers, etc).

## Getting Started

## Installing Our Node Packages

We need to install several packages:

- [react/http](https://github.com/reactphp/http){:target="_blank"} for running HTTP server
- [friends-of-reactphp/mysql](https://github.com/friends-of-reactphp/mysql){:target="_blank"} to interact with MySQL database
- [nikic/fast-route](https://github.com/nikic/FastRoute){:target="_blank"} to handle routing 

Go to the command line in the root of your project run the following command:

{% highlight bash %}
$ composer require react/http
$ composer require friends-of-reactphp/mysql
$ composer require nikic/fast-route
{% endhighlight %}

Simple and easy. Now that we have our packages being installed, let's go ahead and use them to set up our API.

### Setting Up Our Server 

First of all we need a running server that will handle all incoming requests.

Create an empty HTTP server:

{% highlight php %}
<?php

use React\Http\Response;
use React\Http\Server;
use React\MySQL\Factory;

$loop = \React\EventLoop\Factory::create();

$hello = function () {
    return new Response(200, ['Content-type' => 'text/plain'], 'Hello');
};

$server = new Server($hello);

$socket = new \React\Socket\Server('127.0.0.1:8000', $loop);

$server->listen($socket);
echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
$loop->run();
{% endhighlight %}

This is our entry point, our future REST API server. Here we have a *"hello-world"* HTTP server. It has one middleware `$hello` which is triggered for all incoming requests and return a plain text
string 'Hello'.

>*If you are not familiar with ReactPHP middleware and don't know how they work check [this article]({% post_url 2017-12-20-reactphp-http-middleware %}){:target="_blank"}.*

Let's make sure that everything is working up to this point. Start the server. From the command line, type:

{% highlight bash %}
$ php index.php
{% endhighlight %}

You should see the message saying that the server is up and is listening. Now that we know our application is up and running, let's test it. Make GET request to `http://127.0.0.1:8000`. 

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/hello.png">
</p>

Nice! We got back exactly what we wanted. Now, let's wire up our database so we can start performing CRUD operations on users.

### Database

Create a database and table `users` with the following schema: id, name and email. Email field is unique.

{% highlight sql %}
CREATE TABLE users
(
    id INT(11) UNSIGNED AUTO_INCREMENT
        PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    CONSTRAINT users_email_uindex
        UNIQUE (email)
);

{% endhighlight %}

Now, let's connect to a database. Create a factory. Then create a lazy connection. Provide a connection string that consists of: username `root`, has no password, `localhost` and database called "test". The main idea of lazy connection is that internally it lazily creates the underlying database connection only on demand once the first request is invoked and it will queue all outstanding requests until the underlying connection is ready. 

{% highlight php %}
<?php

$factory = new Factory($loop);
$db = $factory->createLazyConnection('root:@localhost/test');
{% endhighlight %}

## Creating the Basic Routes

We will now create the routes to handle getting all the users and creating a new user. Both of them will be handled using the `/users` route. 

### Getting All Users | GET /users

Create a middleware `$listUsers` and pass instance of the connection inside. Now, we are ready to execute queries. Select all users and return them as json object:

{% highlight php %}
$listUsers = function () use ($db) {
    return $db->query('SELECT id FROM users')
        ->then(function (\React\MySQL\QueryResult $queryResult) {
            $users = json_encode($queryResult->resultRows);

            return new Response(200, ['Content-type' => 'application/json'], $users);
        });
};

$server = new Server($listUsers);
{% endhighlight %}

Method `query()` accepts a raw SQL string and returns a promise that resolves with an instance of `QueryResult`. To grab resulting rows we use `resultRows` property of this object. It will be an array of arrays, that represent the result of the query. Then convert them to JSON and return with an appropriate `Content-type` header. I have also changed the middlware in the `Server` constructor from `$hello` to `$listUsers`.

Check our API and you will receive an empty list. Add several users to database and check again. Now it should return users. 

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/list-users.png">
</p>

Before moving further let's make one more change. I'm going to add the first endpoint, so we need to add a simple routing. To handle routing we will use [FastRoute](https://github.com/nikic/FastRoute){:target="_blank"} by [Nikita Popov](https://twitter.com/nikita_ppv){:target="_blank"}. 

Install the router via composer:

{% highlight bash %}
composer require nikic/fast-route
{% endhighlight %}

Define a dispatcher and specify routes. For example, this `$listUsers` middleware responds only to GET requests to path `/users`. 

{% highlight php %}
<?php

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($listUsers) {
    $routes->addRoute('GET', '/users', $listUsers);
});
{% endhighlight %}

Then add dispatching logic inside the server:

{% highlight php %}
<?php

$server = new Server(function (ServerRequestInterface $request) use ($dispatcher) {
    $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            return new Response(404, ['Content-Type' => 'text/plain'],  'Not found');
        case FastRoute\Dispatcher::FOUND:
            $params = $routeInfo[2] ?? [];
            return $routeInfo[1]($request, ... array_values($params));
    }
});
{% endhighlight %}

>I'm not going to cover details of using FastRoute in ReactPHP project. Instead, we will focus on writing controllers and database quires. If you are interested you can read about it in [Using Router With ReactPHP Http Component]({% post_url 2018-03-13-using-router-with-reactphp-http %}){:target="_blank"}. 

Inside we check method and path of the request. `$routeInfo[0]` contains the result of the matching. If the request matches one of the defined routes we execute a corresponding controller with a request object and matched params (if they were defined). Otherwise we return `404` response.

The first endpoint of our simple Api is ready. In response to GET request to `/users` path we return a json representation of users.

## Create a new user

Create a new controller `$createUser`. For this endpoint we use the same path `/users` but request method will be `POST`:

{% highlight php %}
<?php

$createUser = function (ServerRequestInterface $request) use ($db) {
    // ...  
};

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($listUsers, $createUser) {
    $routes->addRoute('GET', '/users', $listUsers);
    $routes->addRoute('POST', '/users', $createUser);
});
{% endhighlight %}

Assume that we receive user data in json. So, we get the response body and decode it to an array:

{% highlight php %}
<?php

$createUser = function (ServerRequestInterface $request) use ($db) {
    $user = json_decode((string) $request->getBody(), true);

    // ...
};
{% endhighlight %}

Now we are ready to insert `$user` array into `users` table. Again call `$db->query()` and provide a raw SQL query and an array of data to be inserted:

{% highlight php %}
<?php

$createUser = function (ServerRequestInterface $request) use ($db) {
    $user = json_decode((string) $request->getBody(), true);

    return $db->query('INSERT INTO users(name, email) VALUES (?, ?)', $user)
        ->then(function () { return new Response(201); });
};
{% endhighlight %}

Once the request is done we return `201` response. 

>I have skipped validation here on purpose, to make examples easier to understand. In a real life you should **never trust** input data.

It looks like we are performing raw requests and everything we pass in `query()` method will be placed right into the query. So, it looks like there is a room for a SQL injection. Don't worry, when we execute a query all provided params are escaped. So, feel free to provide any values and don't be afraid of SQL injection.    

Ok, endpoint is done. Let's check it. 

Then request all users.

We see that a new user has been stored in the database. Let's add the same user once more time.

It returns 500 response. Seems like the query has failed but there is no actual error anywhere. To view the error we can add a rejection handler to our query and return an error message as a response:

{% highlight php %}
<?php

return $db->query('INSERT INTO users(name, email) VALUES (?, ?)', $user)
    ->then(
        function () {
            return new Response(201);
        },
        function (Exception $error) {
            return new Response(
                400, ['Content-Type' => 'application/json'], json_encode(['error' => $error->getMessage()])
            );
        }
    );
{% endhighlight %}

Then restart the server and execute the request. Now the problem is clear. We receive a clear "bad request" response, explaining that we are trying to insert the user with a duplicate email.

Here is the complete code of `$createUser` controller:

{% highlight php %}
<?php

$createUser = function (ServerRequestInterface $request) use ($db) {
    $user = json_decode((string) $request->getBody(), true);

    return $db->query('INSERT INTO users(name, email) VALUES (?, ?)', $user)
        ->then(
            function () {
                return new Response(201);
            },
            function (Exception $error) {
                return new Response(
                    400, ['Content-Type' => 'application/json'], json_encode(['error' => $error->getMessage()])
                );
            }
        );
};
{% endhighlight %}

## Refactoring

The code is becoming more and more messy. Even our controllers are represented with function the whole code looks very procedural. Let's make it object-oriented. Our controllers are good candidates for classes. Create folder `src\Controller`. We are going to store controller classes here. 

>I assume that you have a root namespace `App` and autoloading in your `composer.json` file.

At first we extract `$listUsers` controller. Move the whole logic to its own class `App\Controller\ListUsers`:

{% highlight php %}
<?php

namespace App\Controller;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\MySQL\ConnectionInterface;

final class ListUsers
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return $this->db->query('SELECT id, email FROM users')
            ->then(function (\React\MySQL\QueryResult $queryResult) {
                $users = json_encode($queryResult->resultRows);

                return new Response(200, ['Content-type' => 'application/json'], $users);
            }
        );
    }
}
{% endhighlight %}

Notice that database connection is now injected into the constructor. We can also create our own custom `JsonResponse` class - a wrapper on top of `React\Http\Response`. It will accept a status code and the data we want to return. Json encoding logic and required headers will be encapsulated here. Create this class in `src` folder:

{% highlight php %}
<?php

namespace App;

use React\Http\Response;

final class JsonResponse extends Response
{
    public function __construct(int $statusCode, $data = null)
    {
        $body = $data ? json_encode($data) : null;

        parent::__construct($statusCode, ['Content-Type' => 'application/json'], $body);
    }
}
{% endhighlight %}

Then return an instance of `JsonResponse` in the controller:

{% highlight php %}
<?php

final class ListUsers
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return $this->db->query('SELECT id, email FROM users')
            ->then(function (\React\MySQL\QueryResult $queryResult) {
                return new JsonResponse(200, $queryResult->resultRows);
            }
        );
    }
}
{% endhighlight %}

Done. The controller looks pretty simple and readable. The same way we can move `$createUser` controller to its own class `App\Controller\CreateUser`:

{% highlight php %}
<?php

namespace App\Controller;

use App\JsonResponse;
use App\Router;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\ConnectionInterface;

final class CreateUser
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $user = json_decode((string) $request->getBody(), true);

        return $this->db->query('INSERT INTO users(name, email) VALUES (?, ?)', $user)
            ->then(
                function () {
                    return new Response(201);
                },
                function (Exception $error) {
                    return new JsonResponse(400, ['error' => $error->getMessage()]);
                }
            );
    }
}
{% endhighlight %}

Status codes and can be hidden behind `JsonResponse` class. Let's add some named constructors to it:

{% highlight php %}
<?php

namespace App;

use React\Http\Response;

final class JsonResponse extends Response
{
    public function __construct(int $statusCode, $data = null)
    {
        $body = $data ? json_encode($data) : null;

        parent::__construct($statusCode, ['Content-Type' => 'application/json'], $body);
    }

    public static function ok($data = null): self
    {
        return new self(200, $data);
    }

    public static function created(): self
    {
        return new self(201);
    }

    public static function badRequest(string $error): self
    {
        return new self(400, ['error' => $error]);
    }

    public static function notFound(): self
    {
        return new self(404);
    }
}
{% endhighlight %}

Then use them in the controller:

{% highlight php %}
<?php

namespace App\Controller;

// ...

final class CreateUser
{
    // ...

    public function __invoke(ServerRequestInterface $request)
    {
        $user = json_decode((string) $request->getBody(), true);

        return $this->db->query('INSERT INTO users(name, email) VALUES (?, ?)', $user)
            ->then(
                function () {
                    return JsonResponse::created();
                },
                function (Exception $error) {
                    return JsonResponse::badRequest($error->getMessage());
                }
            );
    }
}

{% endhighlight %}

This make the controller even more readable. Then we move back to the main script and replace functions with objects:

{% highlight php %}
<?php

// ...

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($db) {
    $routes->addRoute('GET', '/users', new \App\Controller\ListUsers($db));
    $routes->addRoute('POST', '/users', new \App\Controller\CreateUser($db));
});
{% endhighlight %}

## View user

The next endpoint will be for getting a user by a specified id - `GET /users/{id}`. We start by adding a new controller `App\Controller\ViewUser`:

{% highlight php %}
<?php

<?php

namespace App\Controller;

use App\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;

final class ViewUser
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function __invoke(ServerRequestInterface $request, string $id)
    {
        return $this->db
            ->query('SELECT * FROM users WHERE id = ?', [$id])
            ->then(
                function (QueryResult $result) {
                    return empty($result->resultRows)
                        ? JsonResponse::notFound()
                        : JsonResponse::ok($result->resultRows);
                }
            );
    }
}
{% endhighlight %}

The code here is very straightforward. Make a `SELECT` query and return a result. If there is no such record in database we return `404` response. Then define a new route:

{% highlight php %}
<?php

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($db) {
    // ...
    $routes->addRoute('GET', '/users/{id}', new \App\Controller\ViewUser($db));
});
{% endhighlight %}

## Delete User

The last endpoint in our tutorial is responsible for user deletion. Request `DELETE /users/{id}` will trigger `App\Controller\DeleteUser` controller:

{% highlight php %}
<?php

namespace App\Controller;

use App\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;

final class DeleteUser
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function __invoke(ServerRequestInterface $request, string $id)
    {
        return $this->db
            ->query('DELETE FROM users WHERE id = ?', [$id])
            ->then(
                function (QueryResult $result) {
                    return $result->affectedRows
                        ? JsonResponse::noContent()
                        : JsonResponse::notFound();
                }
        );
    }
}
{% endhighlight %}

In `DELETE` request we check `affectedRows` of the `QueryResult` object. It's non-zero value indicates that a user has been deleted and we return `204` response. I have already updated `JsonResponse` class with a new static constructor:

{% highlight php %}
<?php

namespace App;

use React\Http\Response;

final class JsonResponse extends Response
{
    // ...

    public static function noContent(): self
    {
        return new self(204);
    }
}

{% endhighlight %}
