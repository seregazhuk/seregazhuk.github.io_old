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
