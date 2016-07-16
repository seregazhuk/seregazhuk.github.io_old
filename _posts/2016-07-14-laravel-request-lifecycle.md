---

title: "Laravel Request Lifecycle"
layout: post
tags: [PHP, Laravel]

---

Lets dive into the magic of Laravel and the way it handles http requests. First of all, the entry point for all the requests is 
the `public/index.php` file. It is usually called the *front controller*. The web server (Apache or Nginx) send all requests 
to this file. So, it may be considered as the starting point for loading the application. 

First of all it loads the Composer generated autoloader file `vendor/autoload.php` and retrieves and instance Laravel 
application (`Illuminate\Foundation\Application`) from the `bootstrap/app.php` file.

{% highlight php %}
<?php

$app = new Illuminate\Foundation\Application(
    realpath(__DIR__.'/../')
);

{% endhighlight %}

This instance serves as the "glue" for all 
the components of Laravel, and is the IoC container itself. There are some bindings to the IoC container in the `bootstrap/app.php` 
file: for *Http Kernel*, *Console Kernel* and *Exception Handler*:

{% highlight php %}
<?php

$app->singleton(
    'Illuminate\Contracts\Http\Kernel',
    'App\Http\Kernel'
);

$app->singleton(
    'Illuminate\Contracts\Console\Kernel',
    'App\Console\Kernel'
);

$app->singleton(
    'Illuminate\Contracts\Debug\ExceptionHandler',
    'App\Exceptions\Handler'
);

{% endhighlight %}

## Kernel
Next, request is served by the HTTP kernel or cli kernel, it depends on the type of the request. It this article I will focus on
HTTP requests and HTTP kernel, that handles them. It is located in `app/Http/Kernel.php`:

{% highlight php %}
<?php

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel {

{% endhighlight %}

Http kernel extends the *Illuminate\Foundation\Http\Kernel*. This class has an array of *bootstrapers* that will be run befoure 
the request is executed. They detect environment and load configuration, configure logs and error handling. They also register
providers with facades, and then boot providers:

{% highlight php %}
<?php

/**
 * The bootstrap classes for the application.
 *
 * @var array
 */
protected $bootstrappers = [
    'Illuminate\Foundation\Bootstrap\DetectEnvironment',
    'Illuminate\Foundation\Bootstrap\LoadConfiguration',
    'Illuminate\Foundation\Bootstrap\ConfigureLogging',
    'Illuminate\Foundation\Bootstrap\HandleExceptions',
    'Illuminate\Foundation\Bootstrap\RegisterFacades',
    'Illuminate\Foundation\Bootstrap\RegisterProviders',
    'Illuminate\Foundation\Bootstrap\BootProviders',
];


{% endhighlight %}

The http kernel also has a list of HTTP middlewares (global and for specific routes). Every request passes through all
the global middlewares before being handled by the kernel.

The process of handling a request is very simple. Kernel has `handle()` method, that recieves a `Request` object and returns
a `Response` object:

{% highlight php %}
<?php

/**
 * Handle an incoming HTTP request.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\Response
 */
public function handle($request)

{% endhighlight %}

I will not dive deeper in the process of how the router works. I treat kernel as a black box that represents my application.

## Service Providers

The most important Kernel bootstrapper is one that registeres service providers (`Illuminate\Foundation\Bootstrap\RegisterFacades`).
It calls `registerConfiguredProviders()` method of the application instance:

{% highlight php %}
<?php

namespace Illuminate\Foundation\Bootstrap;

use Illuminate\Contracts\Foundation\Application;

class RegisterProviders
{
    /**
    * Bootstrap the given application.
    *
    * @param  \Illuminate\Contracts\Foundation\Application  $app
    * @return void
    */
    public function bootstrap(Application $app)
    {
        $app->registerConfiguredProviders();
    }
}
{% endhighlight %}

The list of service providers for the application is configured in the `config.app` file, in `providers` array. First, providers are registered and
on every provider the `register` method will be called. Then, when all the providers have been registered, they must be booted by another bootstrapper
(`Illuminate\Foundation\Bootstrap\BootProviders`). It simply calls `boot` method of the application instance, which calls `boot` method of every 
service provider:

{% highlight php %}
<?php

/**
 * Boot the given service provider.
 *
 * @param  \Illuminate\Support\ServiceProvider  $provider
 * @return mixed
 */
protected function bootProvider(ServiceProvider $provider)
{
    if (method_exists($provider, 'boot')) {
        return $this->call([$provider, 'boot']);
    }
}
{% endhighlight %}

Service providers are responsible for bootstraping all of the framework's and application components. For example, validation, database, routing and so on.
They bootstrap and configure every feature of the framework or application. Service providers are the most important part of the entire application bootstrap
process.

## Dispatch Request

After bootstraping the application and registering service providers, the Request will be dispatched by the router:

{% highlight php %}
<?php

// Illuminate\Foundation\Http\Kernel

/**
* Get the route dispatcher callback.
*
* @return \Closure
*/
protected function dispatchToRouter()
{
    return function ($request) {
        $this->app->instance('request', $request);

        return $this->router->dispatch($request);
    };
}
{% endhighlight %}

And then the router will dispatch the request to a router or controller:

{% highlight php %}
<?php

//Illuminate\Routing\Router
/**
 * Dispatch the request to the application.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\Response
*/
public function dispatch(Request $request)
{
    $this->currentRequest = $request;

    // If no response was returned from the before filter, we will call the proper
    // route instance to get the response. If no route is found a response will
    // still get returned based on why no routes were found for this request.
    $response = $this->callFilter('before', $request);

    if (is_null($response)) {
        $response = $this->dispatchToRoute($request);
    }

    // Once this route has run and the response has been prepared, we will run the
    // after filter to do any last work on the response or for this application
    // before we will return the response back to the consuming code for use.
    $response = $this->prepareResponse($request, $response);

    $this->callFilter('after', $request, $response);

    return $response;
                                
}
{% endhighlight %}

## Conclusion

Steps fo the the request lifecycle:

1. Request is send to `public/index.php`.
2. `bootstrap/app.php` loads Composer autoloader and creates an instance of the application and binds kernels with exception handler.
3. Kernel calls bootstrappers, that loads configuration, detect environment, register and then boot service providers.
4. Kernel handles the request and dispatch it to the router.
5. Router calls *before* filter.
6. Router finds the matched route and calls the route *before* filters.
7. Route calls it's action. 
8. Router calls route *after* filters.
9. Router calls app *after* filters.
10. The middleware stack cascades the Response back up the chain
11. Response is sent to the user.
