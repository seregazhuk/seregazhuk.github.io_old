---
title: "Building a RESTful API Using ReactPHP and MySQL"
tags: [PHP, Event-Driven Programming, ReactPHP, MySQL, API]
layout: post
description: "Create asynchronous RESRful API with ReactPHP and MySQL"
---

Today we will be looking at creating a RESTful API using ReactPHP, MySQL and nikic/FastRoute. Let's look at the API we want to build and what it can do.

## Application

We are going to build the API that:

- Handles CRUD operations on a resource (we are going to use `users`)
- Uses the proper HTTP verbs to make it RESTful (GET, POST, PUT, and DELETE)
- Returns JSON data

All of this is pretty standard for RESTful APIs. Feel free to switch out users for anything you want for your application (orders, products, customers, etc).

## Getting Started

Here is our file structure. We won't need many files and we'll keep this very simple for demonstration purposes. 

{% highlight bash %}
 - src/             // Contains project files
 - index.php        // Entry point to our application
 - composer.json    // Define our app and its dependencies
 - vendor/          // Created by Composer and contains our dependencies
{% endhighlight %}

### Installing Our Dependencies

We need to install several packages:

- [react/http](https://github.com/reactphp/http){:target="_blank"} for running HTTP server
- [friends-of-reactphp/mysql](https://github.com/friends-of-reactphp/mysql){:target="_blank"} to interact with MySQL database
- [nikic/fast-route](https://github.com/nikic/FastRoute){:target="_blank"} to handle routing 

Go to the command line in the root of the project and run the following commands:

{% highlight bash %}
$ composer require react/http
$ composer require friends-of-reactphp/mysql
$ composer require nikic/fast-route
{% endhighlight %}

Simple and easy. Then open `composer.json` and add autoloading section. We are going to autoload classes from `src` folder into `App` namespace:

{% highlight js %}
{
    "require": {
        "react/mysql": "^0.5",
        "react/http": "^0.8",
        "nikic/fast-route": "^1.3"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
{% endhighlight %}


Now that we have our packages being installed, let's go ahead and use them to set up our API.

### Setting Up Our Server 

First of all, we need a running server that will handle incoming requests. Create an empty HTTP server:

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

This is our entry point, our future RESTful API server. Here we have a *"hello-world"* HTTP server. It has one middleware `$hello` which is triggered for all incoming requests and returns a plain text string 'Hello'.

>*If you are not familiar with ReactPHP middleware and don't know how they work check [this article]({% post_url 2017-12-20-reactphp-http-middleware %}){:target="_blank"}.*

Let's make sure that everything is working up to this point. Start the server. From the command line, type:

{% highlight bash %}
$ php index.php
Listening on http://127.0.0.1:8000
{% endhighlight %}

You should see the message saying that the server is up and is listening. Now that we know our application is up and running, let's test it. Make a GET request to `http://127.0.0.1:8000`. 

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/hello.png">
</p>

Nice! We got back exactly what we wanted. Now, let's wire up our database so we can start performing CRUD operations on users.

### Database

Create a database and table `users` with the following schema: `id`, `name`, and `email`. Email field is unique.

{% highlight sql %}
CREATE TABLE users
(
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    CONSTRAINT users_email_uindex UNIQUE (email)
);

{% endhighlight %}

Now, let's connect to a database. Create a factory `React\MySQL\Factory`. Then ask it for a lazy connection. and provide a connection string. My connection string consists of: username `root`, has no password, host `localhost`, and database called "test". 

{% highlight php %}
<?php

$factory = new Factory($loop);
$db = $factory->createLazyConnection('root:@localhost/test');
{% endhighlight %}

The main idea of lazy connection is that internally it lazily creates the underlying database connection only on demand once the first request is invoked and it will queue all outstanding requests until the underlying connection is ready. 

## Creating Basic Routes

We will now create the routes to handle getting all the users and creating a new user. Both routes will be handled using the `/users` path. 

## Getting All Users 
### GET /users

Create a middleware `$listUsers` and pass an instance of the connection inside. Now, we are ready to execute queries. Select all users and return them as a JSON object:

{% highlight php %}
$listUsers = function () use ($db) {
    return $db->query('SELECT id, name, email FROM users ORDER BY id')
        ->then(function (\React\MySQL\QueryResult $queryResult) {
            $users = json_encode($queryResult->resultRows);

            return new Response(200, ['Content-type' => 'application/json'], $users);
        });
};

$server = new Server($listUsers);
{% endhighlight %}

Method `query()` accepts a raw SQL string and returns a promise that resolves with an instance of `QueryResult`. To grab resulting rows we use `resultRows` property of this object. It will be an array of arrays, that represent the result of the query. Then convert them to JSON and return with an appropriate `Content-type` header. I have also changed the middlware in the `Server` constructor from `$hello` to `$listUsers`.

Check our API, make a GET request to `http://127.0.0.1:8000` and you will receive an empty list. Add several users to the database and check again. Now it should return them. 

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/list-users.png">
</p>

MySQL query works, but the server is still a sort of "hello-world" one. It responds the same way to all incoming requests. It's time to fix it and add routing to our application. 

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

>*I'm not going to cover details of using FastRoute in ReactPHP project. Instead, we will focus on writing controllers and database quires. If you are interested you can read about it in [Using Router With ReactPHP Http Component]({% post_url 2018-03-13-using-router-with-reactphp-http %}){:target="_blank"}.*

Inside we check method and path of the request. `$routeInfo[0]` contains the result of the matching. If the request matches one of the defined routes we execute a corresponding controller with a request object and matched params (if they were defined). Otherwise, we return `404` response.

The first endpoint of our API is ready. In response to GET request to `/users` path, we return a JSON representation of users.

## Create a New User 
### POST /users

Create a new middleware (controller) `$createUser`. For this endpoint, we use the same path `/users` but the request method will be `POST`:

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

Assume that we receive user data in JSON. So, we get the response body and decode it to an array:

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

>*I have skipped validation here on purpose, to make examples easier to understand. In a real life, you should **never trust** input data.*

It looks like we are performing raw queries and everything we pass inside `query()` method will be placed right into the query. So, it looks like there is a room for a SQL injection. Don't worry, when we execute a query all provided params are escaped. So, feel free to provide any values and don't be afraid of SQL injection. OK, the endpoint is done. Let's check it. Create a new user:

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/user-created.png">
</p>

Then request all users.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/list-with-created.png">
</p>

We see that a new user has been stored in the database. Let's try to add the same user one more time.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/error-when-same-creation.png">
</p>

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

Then restart the server and execute the request. Now the problem is clear. We receive a clear "bad request" response, explaining that we are trying to insert the user with a duplicated email.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/bad-request.png">
</p>

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

Our application is becoming messy. Adding more controllers will only make things worse. Let's replace our procedural code with classes. Create folder `src/Controller`. We are going to store controller classes here. 

>*I assume that you have a root namespace `App` and autoloading in your `composer.json` file.*

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
        return $this->db->query('SELECT id, name, email FROM users ORDER BY id')
            ->then(function (\React\MySQL\QueryResult $queryResult) {
                $users = json_encode($queryResult->resultRows);

                return new Response(200, ['Content-type' => 'application/json'], $users);
            }
        );
    }
}
{% endhighlight %}

Notice that a database connection is now injected into the constructor. The rest of the code stays the same. Having raw SQL queries in controllers is not a good practice. We can extract them out and create a sort of repository for users. Create class `Users` in `src` folder:

{% highlight php %}
<?php

namespace App;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

final class Users
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function all(): PromiseInterface
    {
        return $this->db->query('SELECT id, name, email FROM users ORDER BY id')
            ->then(function (QueryResult $queryResult) {
                return $queryResult->resultRows;
            });
    }
}
{% endhighlight %}

It encapsulates a database connection. Method `all()` returns a promise that resolves with an array that contains
raw users data. Now, inside the controller, we inject an instance of `Users` class instead of MySQL connection. And replace a raw SQL query with a call of `Users::all()`:

{% highlight php %}
<?php

<?php

namespace App\Controller;

use App\JsonResponse;
use App\Users;
use Psr\Http\Message\ServerRequestInterface;

final class ListUsers
{
    private $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return $this->users->all()
            ->then(function(array $users) {
                return new Response(200, ['Content-type' => 'application/json'], $users);
            });
    }
}
{% endhighlight %}

Done. But there is still a room for improvement here. As it was mentioned before our API returns JSON responses. So, instead of repeating the same response building logic we can also create our own custom `JsonResponse` class - a wrapper on top of `React\Http\Response`. It will accept a status code and the data we want to return. JSON encoding logic and required headers will be encapsulated in this class. Create it in `src` folder:

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

namespace App\Controller;

use App\JsonResponse;
use App\Users;
use Psr\Http\Message\ServerRequestInterface;

final class ListUsers
{
    private $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return $this->users->all()
            ->then(function(array $users) {
                return JsonResponse::ok($users);
            });
    }
}
{% endhighlight %}

Done. The controller looks pretty simple and readable. The same way we can move `$createUser` controller to its own class `App\Controller\CreateUser`:

{% highlight php %}
<?php

namespace App\Controller;

use App\JsonResponse;
use App\Users;
use Exception;
use Psr\Http\Message\ServerRequestInterface;

final class CreateUser
{
    private $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $name = $user['name'] ?? '';
        $email = $user['email'] ?? '';

        return $this->users->create($name, $email)
            ->then(
                function () {
                    return new Response(201);
                },
                function (Exception $error) {
                    return new Response(
                        400, 
                        ['Content-Type' => 'application/json'], 
                        json_encode(['error' => $error->getMessage()])
                    );
                }
            );
    }
}
{% endhighlight %}

Update `Users` class and a missing method `create()`:

{% highlight php %}
<?php

namespace App;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

final class Users
{
    // ... 

    public function create(string $name, string $email): PromiseInterface
    {
        return $this->db->query('INSERT INTO users(name, email) VALUES (?, ?)', [$name, $email]);
    }
}
{% endhighlight %}

It returns a promise that resolves when a new record has been added to the database.

Status codes and response structure can be hidden behind `JsonResponse` class. Let's add some named constructors to it:

{% highlight php %}
<?php

namespace App;

use React\Http\Response;

final class JsonResponse extends Response
{
   // ..

    public static function created(): self
    {
        return new self(201);
    }

    public static function badRequest(string $error): self
    {
        return new self(400, ['error' => $error]);
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
        $name = $user['name'] ?? '';
        $email = $user['email'] ?? '';

        return $this->users->create($name, $email)
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

This makes the controller even more readable. Then we move back to the main script and replace functions with objects. And don't forget to create an instance of `Users` class and pass it inside the closure.

{% highlight php %}
<?php

$users = new \App\Users($db)
// ...

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($users) {
    $routes->addRoute('GET', '/users', new \App\Controller\ListUsers($users));
    $routes->addRoute('POST', '/users', new \App\Controller\CreateUser($users));
});
{% endhighlight %}

## Routes for A Single Item

We've handled the group for routes ending in `/users`. Let's now handle the routes for when we pass in a parameter like a user's id.

The things we'll want to do for this route, which will end with `/users/{id}` will be:

- Get a single user.
- Update a user's info.
- Delete a user.

## Get a Single User 
### GET /users/{id}

We start by adding a new method `find(string $id)` to our `Users` class:

{% highlight php %}
<?php

namespace App;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

final class Users
{
    // ...

    public function find(string $id): PromiseInterface
    {
        return $this->db->query('SELECT id, name, email FROM users WHERE id = ?', [$id])
            ->then(function (QueryResult $result) {
                if (empty($result->resultRows)) {
                    throw new UserNotFoundError();
                }
                
                return $result->resultRows[0];
            });
    }
}
{% endhighlight %}

As you can see I have added a new custom exception `UserNotFoundError`. The code here is very straightforward. Make a `SELECT` query and return a promise. If there is no such record in the database we throw an exception and the promise rejects. Otherwise, the promise resolves with an array of a user's data.

Here is `UserNotFoundError` class:

{% highlight php %}
<?php

namespace App;

use RuntimeException;

final class UserNotFoundError extends RuntimeException
{
    public function __construct($message = 'User not found')
    {
       parent::__construct($message);
    }
}

{% endhighlight %}

Before writing a controller let's add one more custom response to `JsonResponse` class. For this endpoint we definitely need a `404` response, so add a new named constructor `notFound` which accepts a string:

{% highlight php %}
<?php

namespace App;

use React\Http\Response;

final class JsonResponse extends Response
{
    // ...

    public static function notFound(string $error): self
    {
        return new self(404, ['error' => $error]);
    }
}

{% endhighlight %}

Now we are ready to create a new controller `App\Controller\ViewUser`:

{% highlight php %}
<?php

namespace App\Controller;

use App\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;

final class ViewUser
{
    private $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }

    public function __invoke(ServerRequestInterface $request, string $id)
    {
        return $this->users->find($id)
            ->then(
                function (array $user) {
                    return JsonResponse::ok($user);
                },
                function (UserNotFoundError $error) {
                    return JsonResponse::notFound($error->getMessage());
                }
            );
    }
}
{% endhighlight %}

Here we ask `Users` object to find a user by its id and return a corresponding response. If the promise was rejected with `UserNotFoundError` we return a `404` response. Otherwise, we return a JSON representation of a user. Then define a new route:

{% highlight php %}
<?php

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($users) {
    $routes->addRoute('GET', '/users', new \App\Controller\ListUsers($users));
    $routes->addRoute('POST', '/users', new \App\Controller\CreateUser($users));
    $routes->addRoute('GET', '/users/{id}', new \App\Controller\ViewUser($users));
});
{% endhighlight %}

From the call to get all users, we can see id of one of our users. Let's grab that id and test getting that single user.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/view-user.png">
</p>

We can grab one user from our API now! Let's look at updating that user's name. 

## Update a User's Name
### PUT /users/{id}

Our users have only three possible fields: `id`, `email`, and `name`. We will allow changes only for names. Update `Users` and add a new method `update()`:

{% highlight php %}
<?php

namespace App;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

final class Users
{
    // ...

    public function update(string $id, string $newName): PromiseInterface
    {
        return $this->find($id)
            ->then(function () use ($id, $newName) {
                $this->db->query('UPDATE users SET name = ? WHERE id = ?', [$newName, $id]);
            });
    }
}
{% endhighlight %}

The method accepts an id of the user and a new name. We don't check for `affectedRows` property of the `QueryResult` object because in `UPDATE` request we cannot detect whether there is no record with such id, or there is no need in updating a field value. So, we need to find a user first. And if there is such record in the database we perform an update. The resulting promise rejects with `UserNotFoundError` or resolves on successful update.

Then create a new controller `App\Controller\UpdateUser`:

{% highlight php %}
<?php

namespace App\Controller;

use App\JsonResponse;
use App\UserNotFoundError;
use App\Users;
use Psr\Http\Message\ServerRequestInterface;

final class UpdateUser
{
    private $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }

    public function __invoke(ServerRequestInterface $request, string $id)
    {
        $name = $this->extractName($request);
        if (empty($name)) {
            return JsonResponse::badRequest('"name" field is required');
        }

        return $this->users->update($id, $name)
            ->then(
                function () {
                    return JsonResponse::noContent();
                },
                function (UserNotFoundError $error) {
                    return JsonResponse::notFound($error->getMessage());
                }
            );
    }

    private function extractName(ServerRequestInterface $request): ?string
    {
        $params = json_decode((string)$request->getBody(), true);
        return $params['name'] ?? null;
    }
}
{% endhighlight %}

Here we extract the `name` field from the received request body. If it is not present or is empty we return a bad request. Then we try to update a user. If it has been successfully updated and we return `204` response. If the promise rejects we return `404` response. I have already updated `JsonResponse` class with a new static constructor for `204` status code:

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

Add a new route:

{% highlight php %}
<?php

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($users) {
    $routes->addRoute('GET', '/users', new \App\Controller\ListUsers($users));
    $routes->addRoute('POST', '/users', new \App\Controller\CreateUser($users));
    $routes->addRoute('GET', '/users/{id}', new \App\Controller\ViewUser($users));
    $routes->addRoute('PUT', '/users/{id}', new \App\Controller\DeleteUser($users));
});
{% endhighlight %}

Make a PUT request to check that everything works as expected.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/update-user.png">
</p>

We have received 204 status code and a record in the database has changed.

## Deleting a User
### DELETE /users/{id}

The last endpoint in our tutorial is responsible for deleting a user. Create a new method `delete()` in `Users` class:

{% highlight php %}
<?php

namespace App;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

final class Users
{
    // ...

    public function delete(string $id): PromiseInterface
    {
        return $this->db
            ->query('DELETE FROM users WHERE id = ?', [$id])
            ->then(
                function (QueryResult $result) {
                    if ($result->affectedRows === 0) {
                        throw new UserNotFoundError();
                    }
                });
    }
}
{% endhighlight %}

Here we check `affectedRows` property of the `QueryResult` object to detect whether a user has been deleted or not.

`App\Controller\DeleteUser` controller looks the following:

{% highlight php %}
<?php

namespace App\Controller;

use App\JsonResponse;
use App\UserNotFoundError;
use App\Users;
use Psr\Http\Message\ServerRequestInterface;

final class DeleteUser
{
    private $users;

    public function __construct(Users $users)
    {
        $this->users = $users;
    }

    public function __invoke(ServerRequestInterface $request, string $id)
    {
        return $this->users->delete($id)
            ->then(
                function () {
                    return JsonResponse::noContent();
                },
                function (UserNotFoundError $error) {
                    return JsonResponse::notFound($error->getMessage());
                }
            );
    }
}
{% endhighlight %}

As always define a new route:

{% highlight php %}
<?php

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $routes) use ($users) {
    $routes->addRoute('GET', '/users', new \App\Controller\ListUsers($users));
    $routes->addRoute('POST', '/users', new \App\Controller\CreateUser($users));
    $routes->addRoute('GET', '/users/{id}', new \App\Controller\ViewUser($users));
    $routes->addRoute('PUT', '/users/{id}', new \App\Controller\DeleteUser($users));
    $routes->addRoute('DELETE', '/users/{id}', new \App\Controller\DeleteUser($users));
});
{% endhighlight %}

Now when we send a request to our API using DELETE method with the proper user's id, we'll delete this user. For example, let's delete our first user with id `1`.

<p class="text-center image">
    <img src="/assets/images/posts/reactphp-restful-api/delete-user.png">
</p>

## Conclusion

We now have the means to handle CRUD on a specific resource (our beloved bears) through our own API. Using the techniques above should be a good foundation to move into building larger and more robust APIs.

This has been a quick look at creating RESTful API with ReactPHP and MySQL. There are many more things you can do. For example, you can add authentication and create validation with better error messages.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/reactphp-blog-series/tree/master/restulf-api-with-mysql){:target="_blank"}.

This article is a part of the <strong>[ReactPHP Series](/reactphp-series)</strong>.
