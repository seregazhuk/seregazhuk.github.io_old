---

title: "Laravel Middlewares"
layout: post
tags: [PHP, Laravel]

---

So, what really means *middleware?* HTTP middlewares provide a convenient mechanism for filtering HTTP requests, that are
sent into the application. They work like the layers of the onion. When the request comes into the application it has to go through 
all the layers of this onion to get to the core.

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
middlewares are included in the framework. User-defined middlewares are usually located in the `app\Http\Middleware` directory.

Every middleware must implement the *handle()* method. This method processes the request and then passes it to the next middleware (the next 
layer of the onion).
