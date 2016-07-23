---

title: "Laravel Middlewares"
layout: post
tags: [PHP, Laravel]

---

So, what really means *middleware?* HTTP middlewares provide a convenient mechanism for filtering HTTP requests, that are
sent into the application. They work like the layers of the onion. When the request comes into the application it has to go through 
all the layers of this onion to get to the core. Each layer can examine the request and pass it to the next layer or reject it.

HTTP middlewares are configured in the `Http\Kernel` class:

{% highlight php %}
<?php

class Kernel extends HttpKernel {


/**
 * The application's route middleware groups.
 *
 * @var array
 */
 protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
                
    ],
    'api' => [
        'throttle:60,1',
    ],
];
{% endhighlight %}

In the example above we can see that for every request, that comes into our application we will have encrypted cookies, we will add queued cookies
to the response, the session will start, the errors will be shared from the session and the csrf token will be verified. All of these 
middlewares are included in the framework. User-defined middlewares are usually located in the `app/Http/Middleware` directory.

Every middleware must implement the *handle()* method. This method processes the request and then passes it to the next middleware (the next 
layer of the onion):

{% highlight php %}
<?php

/* Handle an incoming request.
 *
 * @param  \Illuminate\Http\Request $request
 * @param  \Closure $next
 * @param  string|null  $guard
 * @return mixed
 */
public function handle($request, Closure $next, $guard = null) {
    return $next($request);
}

{% endhighlight %}

## Create A New Middleware

For example, we need to set a view namespace according to client user agent string: *desktop* or *mobile*. We can use `artisan`
command to create a new middleware:

{% highlight bash %}
php artisan make:middleware SetViewNamespace
{% endhighlight %}

This command will create a new `SetViewNamespace` class in the `app/Http/Middleware`. In this middleware, we will check the 
request user agent string and store the result in the session. Then the request is passed deeper into the application by
calling the `next` callback with the `$request` param.

{% highlight php %}
<?php

class SetViewNamespace {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string $code
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $agent = app(Agent::class);

        ($agent->isPhone()) ?
            \Session::set('view.namespace', 'mobile') :
            \Session::set('view.namespace', 'desktop');

        $response = $next($request);

        \Session::forget('view.namespace');

        return $response;
    }
}

{% endhighlight %}

## Before/After 

It depends on the middleware itself when to process its logic: before or after the request. Compare these two `handle()` methods of different
middlewares:

{% highlight php %}
<?php

namespace App\Http\Middleware;

class BeforeMiddleware 
{
    public function handle($request, Closure $next)
    {
        // Perform logic
        return $next($request);
    }
}
{% endhighlight %}

In the code above the `BeforeMiddleware` will perform its logic *before* the request is handled by the application.

{% highlight php %}
<?php

namespace App\Http\Middleware;

class AfterMiddleware 
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Perform logic

        return $response$response;
    }
}
{% endhighlight %}

But this middleware will perform its logic *after* the request is handled.

## Registering middlewares

There are three different ways to register a middleware:

- Global middlewares
- Route middlewares
- Middleware groups

### Global Middlewares
If your want a middleware to be run for every HTTP request to the application, list the middleware class in the `$middleware` property of the
`app/Http/Kernel` class:

{% highlight php %}
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel {

    protected $middleware = [
        //...
        \App\Http\Middleware\VerifyCsrfToken::class,
        \App\Http\Middleware\MyGlobalMiddleware::class
    ];
}
{% endhighlight %}

### Route Middlewares

To assign a middleware to specific routes, you should first create a short-key for this middleware in `$routeMiddleware` property of the
`app/Http/Kernel` class:

{% highlight php %}
<?php 

    // App\Http\Kernel class

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class
        // ...
    ];
{% endhighlight %}

Once the middleware has been assigned to a short-key in the Http kernel, we can use it in the `middleware` key in the route options array, 
in the `app/Http/routes.php` file:

{% highlight php %}
<?php

// Assing only one middleware
Route::get('admin/dashboard', ['middleware' => 'auth']);

// Assign multiple middlewares
Route::get('admin/dashboard', ['middleware' => ['auth', 'admin']]);
{% endhighlight %}

Instead of using a short-key we can pass a fully qualified class name:

{% highlight php %}
<?php

use App\Http\Middleware\AdminMiddleware;

Route::get('admin/dashboard', ['middleware' => AdminMiddleware::class]);
{% endhighlight %}
