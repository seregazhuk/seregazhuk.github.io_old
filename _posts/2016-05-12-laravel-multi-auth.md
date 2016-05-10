---

title: Laravel Multiauth
layout: post
tags: [Laravel]

---

In Laravel application that I'm currenlty working on, we have decided to implement login and registration
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

### Implementation

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

Ok, but where to put all of this code? Let's create a service provider for this purpose named "ClientAuthServiceProvider":

{% highlight php %}
<?php

namespace App\Providers;

use Auth;
use App\Client;
use Illuminate\Auth\Guard;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\EloquentUserProvider;

class ClientAuthServiceProvider extends ServiceProvider {
    public function boot() {
        Auth::extend('clientEloquent', function($app) {
            $clientProvider = new EloquentUserProvider($app['hash'], Client::class);
            return new Guard($clientProvider, $app['session.store']);
        });
    }
}
{% endhighlight %}

Now to use this service provider, we must add it to our `config/app.php` file:

{% highlight php %}
<?php

'providers' => [
    // ... 
    App\Providers\ClientAuthServiceProvider::class,
    // ...
]

{% endhighlight %}

Then as documentation says we go to our `config/auth.php` and switch to the new driver:

{% highlight php %}
<?php

// ...
'driver' => 'clientEloquent',
{% endhighlight %}

But, this will simply replace our *admins* auth implementation with newly created *clients* one. So how to fix it?
In our code we want to use something like this:

{% highlight php %}
<?php

// Admin Controller code 
Auth::loginUsingId(1) // logins as entity from admins table

// Profile Controller code
ClientAuth::loginUsingId(1) // logins as entity form client table
{% endhighlight %}

Becouse we have both admin and client controllers in our application, we need a way to switch between auth drivers. So come
back to `config/auth.php` file and change `driver` back to *eloquent*. This driver will be used by default in admin controllers,
so there is no need to change their code. Our main goal is to add authentication to client controllers.

It seems that we need to create a seperate *ClientAuth* facade, which will call methods of our new *eloquentClient* auth driver. 

{% highlight php %}
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class ClientAuth extends Facade {
    protected static function getFacadeAccessor() 
    {
        return 'auth.driver_client';
    }
}
{% endhighlight %}

Then we must register `auth.driver_client` in the IoC container. Let's update our *ClientAuthServiceProvider*:

{% highlight php %}
<?php

namespace App\Providers;

use Auth;
use App\Client;
use Illuminate\Auth\Guard;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\EloquentUserProvider;

class ClientAuthServiceProvider extends ServiceProvider {
    public function boot() {
        Auth::extend('clientEloquent', function($app) {
            $clientProvider = new EloquentUserProvider($app['hash'], Client::class);
            return new Guard($clientProvider, $app['session.store']);
        });
    }

    public function register(){
        $this->app->singleton('auth.driver_client', function($app) {
            return Auth::driver('clientEloquent');
        });
    }
}
{% endhighlight %}

Also we add an alias for our facade in `config/app.php`:

{% highlight php %}
<?php

'aliases' => [
    // ...
    'ClientAuth' => App\Facades\ClientAuth::class,
    // ...
]
{% endhighlight %}

That is all. Our client auth driver is ready: we have service provider, in which we extend base *Auth* class with a new 
implementation of *EloquentUserServiceProvider*. We have facade *ClientAuth* to get access to this driver. The only last thing is 
to switch between different auth drivers in out application. We decided that we don't touch config file and admin controllers. By 
default we are using auth for *admins* table. We only need to change behaviour in client controllers. For this purpose I've choosen 
to use middleware.

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

class CatalogController extends SiteBaseController
{
    public function __construct() {
        $this->middleware('auth.client');
    }
}
{% endhighlight %}

Finaly we have Catalog controller that is available to use the new *client* auth driver. Is your have seen in Laravel 5.1
it is not a trivial task to create seperate auth providers in your app. 

