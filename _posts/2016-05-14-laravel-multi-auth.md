---

title: "Laravel Multiauth: Different Versions - Different Strategies"
layout: post
tags: [Laravel]

---

In Laravel application that I'm currently working on, we have decided to implement login and registration
for our clients. But we have already implemented auth for our admins, that are placed in a 
different table. We didn't want to change out database schema: to move all entities to one table, or
to add different flags and so one. We just needed two auth implementations: one for clients and one
for admins.

## Laravel 5.1

After some reserch we arrived at Laravel's <a href="https://laravel.com/docs/5.1/authentication#adding-custom-authentication-drivers" target="_blank">
Authentication Documentation</a>, that told us to extend basic Auth class, and then switch to this new driver in 
`config/auth.php` file. 

In our case we want to create another implementation of `EloquentUserProvider` that will use *clients* table to recieve users.
Ok, but how to extend? Where to put all of this code? 

### Extend AuthManager

Laravel components may be extended in two different ways: binding a new implementation in Laravel IoC container, or registering an 
extension with a *Manager* class. For managing creation of driver-based components there are several special *Manager* classes. They
are implementations of the "Factory" design pattern, which create a particular driver implementation for a component, based on 
tha application's configuration. 
Each manager class has an `extend` method for injecting new driver implementation into the manager.

In our example we are interested in *AuthManager*. To add a new driver resolution functionality into it we need to use already 
know `extend` method:

{% highlight php %}
<?php

Auth::extend('clientEloquent', function($app) {
   // We need to return here an implementation of Illuminate\Auth\UserProviderInterface 
});
{% endhighlight %}

In `extend` method we must return our new driver for *clients* table, let's name it *clientEloquent*. Now we should create
this driver. Driver implementation must implement *UserProviderInterface*, which is responsible for fetching *UserInterface* 
implementations out of a persistent storage system. In our case *UserInterface* implementations will be *Eloquent* models, and
we will use *EloquentUserProvider* as an implementation of *UserProviderInterface*.

{% highlight php %}
<?php

Auth::extend('clientEloquent', function($app) {
   $clientProvider = new EloquentUserProvider($app['hash'], Client::class);
   return new Guard($clientProvider, $app['session.store']);
});
{% endhighlight %}

*EloquentUserProvider* requires an instance of *HasherContract* for password cheking, and *Eloquent* model class. Then we wrap 
an instance of our provider into *Guard* class to use advantages such of methods as `check()`, `guest()`, `user()` and so one.

Ok, but where to put all of this code? In `app/Providers` directory there already exists one service provider for this puprose `AuthServiceProvider`.
Let's update it's boot method with our code:

{% highlight php %}
<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(GateContract $gate)
    {
        $this->registerPolicies($gate);

        Auth::extend('clientEloquent', function($app) {
            $clientProvider = new EloquentUserProvider($app['hash'], Client::class);
            return new Guard($clientProvider, $app['session.store']);
        });
    }
}
{% endhighlight %}

Then as documentation says we go to our `config/auth.php` and switch to the new driver:

{% highlight php %}
<?php

// ...
'driver' => 'clientEloquent',
{% endhighlight %}

But, this will simply replace our *admins* auth implementation with newly created *clients* one. So how to fix it?
How to switch programmatically between both? 

### Use Middleware to Switch Between Drivers

Becouse we have both admin and client controllers in our application, we need a way to switch between auth drivers. So come
back to `config/auth.php` file and change `driver` back to *eloquent*. This driver will be used by default in admin controllers,
so there is no need to change their code. Our main goal is to add authentication to client controllers.

I've choosen to use middleware to change auth driver in client controllers. We call it `ClienAuth` and place in in `app\Http\Middleware`
directory:

{% highlight php %}
<?php

namespace App\Http\Middleware;

use Closure;
use Config;

class ClientAuth {
    /**
    * Handle an incoming request.
    * 
    * @param  \Illuminate\Http\Request $request
    * @param  \Closure $next
    * @return mixed
    */
    public function hanlde($request, Closure $next) {
        Config::set('auth.driver', 'clientEloquent');
        Config::set('auth.model', 'Client');

        return $next($request);
    }
}
{% endhighlight %}

Register *ClientAuth*  middleware in `Kernel.php` as *routeMiddleware*:

{% highlight php %}
<?php

namespace App\Http;

class Kernel extends HttpKernel {

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        // ...
        'auth.client' => App\Http\Middleware::class,
        // ...
    ];

}
{% endhighlight %}

Enable middleware in controller:

{% highlight php %}
<?php

namespace App\Http\Controllers\Site;

class ProfileController extends SiteBaseController
{
    public function __construct() {
        $this->middleware('auth.client');
    }
}
{% endhighlight %}

Finaly we have Profile controller that is available to use the new *client* auth driver. The same is true about AuthController. Just add this 
middleware in constrcutor, and `AuthenticatesAndRegistersUsers` trait will use our new driver to manage users. As your have seen in Laravel 5.1
it is not a trivial task to create seperate auth providers in your app. 

## Laravel 5.2

In Laravel 5.2 multiple authentication is implemented as an inbuilt functionality. Let's go through the steps to achieve the same results as in
the previous chapter.

### Set up models

In order to achieve authentication our *Client* and *Admin* models must be instances of `Illuminate\Contracts\Auth\Authenticatable`:

{% highlight php %}
<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Client extends Authenticatable 
{
    // ...
}

class User extends Authenticatable 
{
    // ...
}

{% endhighlight %}

### Change config

Now it's time to make some changes in `config/auth.php`. First of all `guards` array. This array defines how authentication 
is performed for every request. We can either use session or tokens for handling authentication. 

{% highlight php %}
<?php

// ...
'guards' => [
    'user' => [
        'driver' => 'session',
        'provider' => 'users'
    ]
    'client' => [
        'driver' => 'session',
        'provder' => 'clients'
    ]
]
// ...
{% endhighlight %}

In `guards` array we are referencing to `providers` array, which is in the save config file below. `providers` define which driver 
and model class we are going to use for authentication. Driver can be either eloquent or database or any custom driver.
We must change it accordingly:

{% highlight php %}
<?php

'provders' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\User::class,
    ],
    'clients' => [
        'driver' => 'eloquent',
        'model' => App\Client::class,
    ],

]
{% endhighlight %}

Then if you want, you can add changes to `passwords` array. After all we there is one more change in `defaults` array. We want Laravel
to use `users` guard by default.

{% highlight php %}
<?php

'defaults' => [
    'guard' => 'users',
    'passwords' => 'users'
]
{% endhighlight %}

### Gaurd Instance

If we have more than one authentication table, we must use `Auth::guard` in a different way we did it before. Now we must specify
what `gaurd` we want to use (they are listen in `config/auth.php` file in `guards` array):

{% highlight php %}
<?php

Auth::guard('user')->user()  
Auth::guard('client')->user()->logout()
auth()->guard('client')->check()
Auth::guard('user')->attempt(['email' => '', 'password' => ''])

{% endhighlight %}

### AuthController

In your `AuthController` to change *guard* instance simply define a `guard` property:

{% highlight php %}
<?php

protected $guard = 'client';
{% endhighlight %}

### Middlewares

If you want you can implement special middlewares for your guards and then use them in controllers. For example:

{% highlight php %}
<?php

class RedirectIfNotClient 
{
    
    /**
    * Handle an incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Closure  $next
    * @param  string|null  $guard
    * @return mixed
    */
    public function handle($request, Closure $next, guard='client')
    {
        if (!Auth::guard($guard)->check()) {
            return redirect('/');
        }

        return $next($request);
    }
}
{% endhighlight %}

Register middlware in `Kernel.php`:

{% highlight php %}
<?php

protected $routeMiddleware = [
    'client' => \App\Http\Middleware\RedirectIfNotClient::class,
];
{% endhighlight %}

Use middleware in for example *ProfileController*:

{% highlight php %}
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class ProfileController extends Controller 
{
    public function __construct(){
        $this->middleware('client');
    }
}

{% endhighlight %}

And And it's done! As you have seen it's much easier that it was in Laravel 5.1, were we had to write too musch code, to implement the 
same things.
