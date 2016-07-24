---

title: "Laravel middleware"
layout: post
tags: [PHP, Laravel]

---

So, what really means *middleware?* HTTP middleware provide a convenient mechanism for filtering HTTP requests, that are
sent into the application. They work like the layers of the onion. When the request comes into the application it has to go through 
all the layers of this onion to get to the core. Each layer can examine the request and pass it to the next layer or reject it.

HTTP middleware are configured in the `Http\Kernel` class:

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
middleware are included in the framework. User-defined middlewares are usually located in the `app/Http/Middleware` directory.

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
middleware:

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

## Registering middleware

There are three different ways to register a middleware:

- Global middleware
- Route middleware
- Middleware groups

### Global middleware
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

### Route middleware

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

// Assign only one middleware
Route::get('admin/dashboard', ['middleware' => 'auth', 'AdminController@dashboard']);

// Assign multiple middleware
Route::get('admin/dashboard', ['middleware' => ['auth', 'admin']], 'AdminController@dashboard');
{% endhighlight %}

Instead of using a short-key we can pass a fully qualified class name:

{% highlight php %}
<?php

use App\Http\Middleware\AdminMiddleware;

Route::get('admin/dashboard', ['middleware' => AdminMiddleware::class], 'AdminController@dashboard');
{% endhighlight %}

### Middleware groups

We can group our middleware under a single key to make them easier to assign to the routes. There is a `$middlewareGroups` property for 
this purpose. For example, we can create an `admin` group:

{% highlight php %}
<?php

// App\Http\Kernel class

protected $middlewareGroups = [
    'admin' => [
        \App\Http\Middleware\FooMiddleware::class,
        \App\Http\Middleware\BarMiddleware::class,
];

{% endhighlight %}

Then, this group can be assigned to routes and controller actions:

{% highlight php %}
<?php

Route::group(['middleware' => 'admin'], function(){
    Route::get('dashboard', 'AdminController@dashboard');
});
{% endhighlight %}

Out of the box Laravel comes with two middleware groups: `web` and `api`:

{% highlight php %}
<?php

// App\Http\Kernel

protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
    ],
    'api' => [
        'throttle:60,1'
    ]
];
{% endhighlight %}

Middleware groups can be assigned to routes and controller actions with the same syntax as individual middleware. They simply
make it more convenient to assign many middleware to a route at once. The `web` middleware group is automatically applied to 
`routes.php` file in the `RouteServiceProvider`: 

{% highlight php %}
<?php

// App\ServiceProviders\RouteServiceProvider

/**
 * Define the "web" routes for the application.
 *
 * These routes all receive session state, CSRF protection, etc.
 *
 * @param  \Illuminate\Routing\Router  $router
 * @return void
 */
protected function mapWebRoutes(Router $router)
{
    $router->group([
        'namespace' => $this->namespace, 'middleware' => 'web',
    ], function ($router) {
        
    });
}
{% endhighlight %}

## Middleware Parameters

Middleware can receive additional parameters. For example, we can verify that the user has a given *role* to perform an action. To do it
we need to create a middleware that receives that *role* parameter in the `handle()` method and pass this parameter in the `routes.php` file:

App\Http\Middleware\RoleMiddleware: 

{% highlight php %}
<?php

namespace App\Http\Middleware;

use Closure;

class RoleMiddleware 
{
    public function handle($request, Closure $next, $role) 
    {
        if(!$request->user->hasRole($role)) {
            // redirect
        }

        return $next($request);
    }
}
{% endhighlight %}

App\Http\routes.php:


{% highlight php %}
<?php

// routes.php

Route::get('admin/posts', ['middleware' => 'admin', 'role:moderator'], 'Admin\PostsController@index' );
{% endhighlight %}

## Terminable Middleware

If we need to do some work after the HTTP response has been sent to the browser, we need to define a *terminable* 
middleware. To do it we can simply add a `terminate()` method to the middleware. For example, Laravel comes with the 
`Illuminate\Session\Middleware\StartSession` middleware. It writes session data *after* the response has been sent:

{% highlight php %}
<?php

namespace Illuminate\Session\Middleware;

// use ...

class StartSession
{
    // ... 

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
     public function $terminate($request, $response) 
     {
         if ($this->sessionHandled && $this->sessionConfigured() && ! $this->usingCookieSessions()) {
            $this->manager->driver()->save();
         }
     }
}
{% endhighlight %}

The `terminate()` method receives both the request and the response objects. After defining a terminable middleware,
it should be listed in the global middlewares in the Http kernel.
