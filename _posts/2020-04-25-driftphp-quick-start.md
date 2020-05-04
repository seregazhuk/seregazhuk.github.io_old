---

title: "DrfitPHP: Quick Start"
layout: post
description: ""
tags: [PHP, ReactPHP, DriftPHP]
draft: true

---

## Idea and Architecture

Let's imagine that you are building a high-performance PHP application. Speaking of me previously I had two options: either use Symfony or ReactPHP. And to tell the truth, both solutions have their one pros and cons. With Symfony, you have a huge ready-to-go ecosystem and all you need is to add your own business logic. But the downside here is that your code is going to be blocking in most cases. If you have a lot of input/output operations (and you will definitely have) they will slow down the application. Everything you can do in this case is to reduce the bootstrap of the framework by using [php-pm](https://github.com/php-pm/php-pm){:target="_blank"}. It means that you have several instances of your applications that are already booted and ready to accept incoming connections. Yes, in this case, you drastically reduce the bootstrap time but if you have a lot of input-output operations inside the app, it is not the solution for you. The bootstrapped application still behaves the same way: PHP blocks and wait until input-output operations finish.

>*In this tutorial, I suppose that you are familiar with both Symfony and ReactPHP. We will not cover the basics of Symfony or ReactPHP components.*

With ReactPHP you can write a nonblocking code, all input-output operations execute concurrently and thus they don't slow down the application. But you have to write a lot of "infrastructure" code from scratch. ReactPHP is not a framework, but a set of components. You have an HTTP server, HTTP client, different clients for storages, and so on. But there is no framework here. You don't have routing or components or something like that. No. If you want to build a web-application you need to compose your app with these different building blocks by yourself.

And with DriftPHP things change. It is a framework build on top of both Symfony and ReactPHP components. You may consider it as an *"asynchronous Symfony"*, or *"Symfony on top of ReactPHP"*. With this framework, you get the benefits from both solutions. From Symfony, you have all its infrastructure: configuration, routing, event dispatching, and so on. And from ReactPHP you receive non-blocking execution. 

How does it work? The idea is the following: we don't need to bootstrap the Symfony kernel for each request. So, we boot it once, and then it keeps running handling all incoming requests. This part executes in a traditional synchronous (blocking) way because here we load all resources (twig templates, configuration files). Once everything is loaded into memory the kernel is ready to handle requests. This is the place where ReactPHP comes into play. 

The kernel runs on top of ReactPHP server. It opens a socket on a specified port, listens to incoming connections, and then delegates them to Symfony. It means that you don't need a dedicated web-server anymore. Your application IS the server itself. Inside the app, the code should execute asynchronously. What does it mean? For all input/output operations, we use ReactPHP components. As you know such operations are slow and thus they can slow down the server. We don't want one connection to wait for another. So, in places where we have input-output (filesystem or network communication), we don't deal with actual values but with promises. This sort of architecture allows to build an asynchronous ReactPHP application on top of Symfony framework.

## Hello World

To start from scratch we create a new DriftPHP project. Open your terminal and type this:

{% highlight bash %}
composer create-project drift/skeleton -sdev my-app
{% endhighlight %}

This creates a new folder `my-app` with DriftPHP application inside:

{% highlight bash %}
my-app/
 |- bin
 |- docker
 |- Drift
 |- public
 |- src
 |- Domain
 |- var
 |- vendor
 |- composer.json
 |- composer.lock
 |- docker-compose.yml
 |- Dockerfile
{% endhighlight %}

Remember that this application is already a server, so we don't need Apache or Nginx to start it. We already have an HTTP server written in PHP. To boot it we use Docker Compose. DriftPHP project already has `docker-compose.yml` file that includes all instructions to Inside the project directory in the terminal type the following command:

{% highlight bash %}
docker-compose up
{% endhighlight %}

It installs all Composer dependencies and starts the server on `0.0.0.0:8000`. From now you have a running PHP server listening on `0.0.0.0:8000` for incoming requests. You can check it and open this URL in your browser on request it in the terminal:

{% highlight bash %}
$ curl http://0.0.0.0:8000 -s -D - | json 
HTTP/1.1 200 OK
cache-control: no-cache, private
date: Sun, 26 Apr 2020 11:48:06 GMT
content-type: application/json
X-Powered-By: React/alpha
Content-Length: 34
Connection: close

{
  "message": "DriftPHP is working!"
}
{% endhighlight %}

As you can see we made a GET request to `http://0.0.0.0:8000` and in response, we received `200 OK` status code with JSON body `{"message": "DriftPHP is working!"}`. Amazing, right?! So, it works. Let's see how it works.

## Fundamentals: Route, Controller, Promise

As was previously said DriftPHP under the hood uses Symfony. It means that if you are familiar with Symfony there will be no surprises for you. Bundles, services, and routes are located inside `Drift/config` folder. 

{% highlight bash %}
Drift/config/
 |- bootstrap.php
 |- bundles.php
 |- routes.yml
 |- services.yml
{% endhighlight %}

We were able to make a `GET` request to path `/`. So, there should be a declared route for it. Open `Drift/config/routes.yml` and check it:

{% highlight yaml %}
# Routes
default:
  path: /
  controller: App\Controller\DefaultController
{% endhighlight %} 

There is a registered route which is handled by `App\Controller\DefaultController`. The controller is declared in `Drift/config/services.yml` file:

{% highlight yml %}
# ...

# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: true

    #
    # Controllers
    #
    App\Controller\:
        resource : "%app_path%/src/Controller/*"
        tags:
            - {name: controller.service_arguments}
{% endhighlight %}

Let's open the controller to see what's inside. By default controllers are stored inside `src/Controller` directory. Open `src/Controller/DefaultController.php` file: 

{% highlight php %}
<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController
{
    /**
     * Default path.
     */
    public function __invoke(Request $request)
    {
        return new FulfilledPromise(
            new JsonResponse([
                'message' => 'DriftPHP is working!',
            ], 200)
        );
    }
}
{% endhighlight %}

You see it is just a plain PHP class. A callable class actually. It has one method `__invoke()` that accepts an instance of Symfony `Request` and returns a promise. Remember that the server runs asynchronous and under the hood uses ReactPHP. The server can't wait for controller to return an actual response. All data processing goes via promises. In our case, we have ready `200` response and wrap it into a `FulfilledPromise`. What happens next? The kernel receives this promise. Once the promise resolves with a response it will be sent back to the client. Our promise is already resolved, thus the client receives a response immediately. 

## First Asynchronous Endpoint

Let me show you an example that makes the usage of promises in the controller more clear. A very basic thing. You need to grab data from the database and return it. It is obvious that the DB call is going to take some time. If we don't want the whole server to freeze we need to use promises here. Let's simulate a DB call with a timer. Create a new controller `src/Controller/WaitController.php` with the following code:

{% highlight php %}
<?php
declare(strict_types=1);

namespace App\Controller;

use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class WaitController
{
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function __invoke(Request $request)
    {
       
    }
}

{% endhighlight %}

This new controller has a dependency on the event loop. We will use it to add a timer. Then we add an action method `wait()` with the following contents:

{% highlight php %}
<?php

public function wait()
{
    $deferred = new Deferred();
    $this->loop->addTimer(1, static function () use ($deferred) {
        $response = new JsonResponse(
            [
                'message' => 'Wait for a second!',
            ], 200
        );
        $deferred->resolve($response);
    });

    return $deferred->promise();
}
{% endhighlight %}

In the snippet above we instantiate a `Deferred` object. It is an abstraction that represents something that is gonna happen in the future. Then we add a timer for 1 second. Once the time is up we resolve this deferred with a response. From the controller, we return a promise of this deferred object. We even don't need a request here. 

There is no need to declare a new service for this controller because everything from this namespace is already imported as controllers. But we need to declare a route. Let's add a `GET` request to `/wait`. Open file `Drift/config/routes.yml`

{% highlight yaml %}
wait:
    path: /wait
    controller: App\Controller\WaitController::wait
{% endhighlight %}

Now try to execute this controller:

{% highlight bash %}
$ curl http://0.0.0.0:8000/wait -s -D - | json 
HTTP/1.1 200 OK
cache-control: no-cache, private
date: Sun, 26 Apr 2020 11:48:06 GMT
content-type: application/json
X-Powered-By: React/alpha
Content-Length: 34
Connection: close

{
  "message": "Wait for a second!"
}
{% endhighlight %}

We again receive a JSON response but now in a second. What happens inside? The controller is triggered and the kernel receives the promise. But the promise is pending now. So, the kernel waits until the promise resolves (until the timer is done). Once the time is up the promise resolves with a response and the kernel returns this response to the client. The important moment here is that while the promise is in a pending state the kernel still accepts and handles new requests. It doesn't freeze and wait for the promise. No, everything here happens asynchronously and in a non-blocking way. And that's the magic of ReactPHP.

By the way, when developing a Drift application there is no need to restart the server each time you make a change. In the skeleton that we use the entry point for Docker contains the following code:

{% highlight bash %}
#!/bin/bash

cd /var/www
rm -Rf var
php vendor/bin/server watch 0.0.0.0:8000 --dev
{% endhighlight %}

It executes the server `watch` command which is a very convenient thing. Under the hood, it listens to changes in `Drift`, `src`, and `public` folders. Once the watcher detects changes in these folders it automatically restarts the server. So, there is no need to manually restart the server each time you make the change. 

## Conclusion

It was just an introduction, nothing serious here. The idea of this tutorial was to introduce you to a new approach to building PHP web-app. Instead of separating an application and a web-server, we have all-in-one. Our application IS already an HTTP server written in PHP. The server is built on top of ReactPHP and works asynchronously. It means that our code, the code of our application will be executed asynchronously and thus it should be non-blocking. Working with Drift we get power and performance of asynchronous execution but at the same time, it is our responsibility to write non-blocking code. Each time we have input-output operations (filesystem, network, database) we should special clients or adapters and work with promises.
