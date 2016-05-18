---

title: "Laravel: Don't Let Facades Confuse You"
layout: post
tags: [Laravel]

---

## Facade Design Pattern

Let's look at Gang of Four description of the Facade pattern: 

*This is a structural pattern as it defines a manner for creating relationships between 
classes or entities. The facade design pattern is used to define a simplified interface to a more complex subsystem.*


According to the Gang of Four the Facade pattern is a structural pattern. The Facade pattern is a class, 
which wraps a complex library and provides a simplier and more readable interface to it. The facade itself maintains it's 
dependencies.

## Facades in Laravel

- [Usage](#usage) 
- [How it works](#how-it-works) 
- [Aliases](#aliases) 
- [Create Custom Facade](#create-a-custom-facade) 


Laravel has a feature similar to this pattern, also named Facades. This name may confuse you, because facades in
Laravel don't fully implement the Facade design pattern. According to the <a href="https://laravel.com/docs/master/facades" target="_blank">documentation</a>:

*Facades provide a "static" interface to classes that are available in the application's service container.*

Another words facades serve as a proxy for accessing to the container's services, which is actually the syntactic sugar for these services. Instead of having to
go through a testable and maintainable way of instiantiating a class, passing in all of it's dependencies, we can simply use a static interface, but behing
the scenes Laravel itself will take care of instantiating a class and resolving it's dependencies ot of the IoC container.

## Usage

We will use Laravel `Cache` facade in our examples. Syntax is very clear:

{% highlight php %}
<?php

// retrieve value by key from cache
$val = Cache::get('key'); 
{% endhighlight %}

You can achieve the same results with the code below:

{% highlight php %}
<?php

$val = app()->make('cache')->get('key');
{% endhighlight %}

As mentioned before, you can use facade classes in Laravel to make services available in a more readable way. In Laravel all services inside the IoC
container have unique names, and all of them have their own facade class. To access a service from the container you can use `App::make()` method or
`app()` helper function. So there is no difference between these lines of code:
{% highlight php %}
<?php

SomeService::someMethod();
// and
app()->make('some.service')->someMethod();
// or
App::make('some.service')->someMethod();
{% endhighlight %}

## How it works

Let's take a look at a "real" example of Laravel cache system and `Cache` facade:

{% highlight php %}
<?php

namespace App\Http\Controllers;

use Cache;
use App\Http\Controllers\Controller;

class CatalogController extends Controller
{
    /**
     * Shows popular books in catalog
     */
    public function items()
    {
        $books = Cache::get('books:popular');

        return view('catalog.books', compact('books'));
    }
}

{% endhighlight %}

Here we retrieve books from cache with the help of `Cache` facade.

All facade classes are extended from the base `Facade` class. There is only one method, that must be implemented in every facade class: `getFacadeAccessor()`
which returns the unique service name inside the IoC container. So it must return a string, that will be resolved then out of the IoC container. 

Here is the source code of the `Illuminate\Support\Facades\Cache` facade class:

{% highlight php %}
<?php 

namespace Illuminate\Support\Facade;

class Cache extends Facade 
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
     protected static function getFacadeAccessor()
     {
        return 'cache';
     }
}
{% endhighlight %}

Ok, but how are we able to do things like below:

{% highlight php %}
<?php

Cache::get('books:popular');
{% endhighlight %}

It looks like we are calling a static method `get()` of `Cache` class, but as we have seen there is no such static 
method in `Cache` class. Here method `get()` actually exists in the service inside the container. 
All the magic is hidden inside the basic `Facade` class.

Do your remember the only one method `getFacadeAccessor` from the `Cache` class? This method returns the name of a 
service container binding. When we are referencing any static method on the `Cache` facade, Laravel resolves the 
`cache` binding from the service container and runs the requested method against that object.

Now let's examine this "magic" in details.
Every facade is goning to extend the basic abstract `Facade` class. The magic is hidden inside three methods here:

- `__callStatic()` - simple PHP magic method
- `getFacadeRoot()` - gets service out of the IoC container
- `resolveFacadeInstance()` - is responsible for resolving the instance of the servce

`__callStatic()` is fired every time, when a static method that does not exist on a facade is called. So, after calling `Cache::get('books:popular')` we are falling 
inside this method, we resolve an instance of the service behind a facade out of the IoC container with the help of `getFacadeRoot()` method. Then 
we determine a number of arguments were passed to the method and according to this number the required method of the service is called.

{% highlight php %}
<?php

/**
 * Handle dynamic, static calls to the object.
 *
 * @param  string  $method
 * @param  array   $args
 * @return mixed
 */
public static function __callStatic($method, $args)
{
    $instance = static::getFacadeRoot();

    if (! $instance) {
        throw new RuntimeException('A facade root has not been set.');
    }

    switch (count($args)) {
        case 0:
            return $instance->$method();

        case 1:
            return $instance->$method($args[0]);

        case 2:
            return $instance->$method($args[0], $args[1]);

        case 3:
            return $instance->$method($args[0], $args[1], $args[2]);

        case 4:
            return $instance->$method($args[0], $args[1], $args[2], $args[3]);

        default:
            return call_user_func_array([$instance, $method], $args);
    }
}
{% endhighlight %}

Method `getFacadeRoot()` returns an instance of the service object behind the facade:

{% highlight php %}
<?php

/**
 * Get the root object behind the facade.
 *
 * @return mixed
 */
public static function getFacadeRoot()
{
    return static::resolveFacadeInstance(static::getFacadeAccessor());
}
{% endhighlight %}

It uses `resolveFacadeInstance()` method, which is responsible for resolving the proper instance of the service. Here we check passed
argument for an object, then we check if we have already resolved that service. And if not it is simply retrieved out of the container:

{% highlight php %}
<?php

/**
 * Resolve the facade root instance from the container.
 *
 * @param  string|object  $name
 * @return mixed
 */
protected static function resolveFacadeInstance($name)
{
    if (is_object($name)) {
        return $name;
    }

    if (isset(static::$resolvedInstance[$name])) {
        return static::$resolvedInstance[$name];
    }

    return static::$resolvedInstance[$name] = static::$app[$name];
}
{% endhighlight %}
And that is all. Actually no magic here.

## Aliases
Instead of writing `Illuminate\Support\Facades\Cache` every time when you need to get access to Laravel cache system, you may 
simply import `Cache` and start using it. But how? Again some magic here. We have seen in the source code of `Cache` facade, that it's
namespace was `Illuminate\Support\Facades`. It becomes possible with the help of aliases. All the aliases of your appliaction are listed in
`aliases` array in `config/app.php` file:

{% highlight php %}
<?php

return [

    //...

    'aliases' => [
        'App'     => Illuminate\Support\Facades\App::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        'Auth'    => Illuminate\Support\Facades\Auth::class,
        'Blade'   => Illuminate\Support\Facades\Blade::class,
        'Bus'     => Illuminate\Support\Facades\Bus::class,
        'Cache'   => Illuminate\Support\Facades\Cache::class,
        'Config'  => Illuminate\Support\Facades\Config::class,
    ],

    // ...
];
{% endhighlight %}

Here you can see that each alias name is mapped to a fully-qualified class name. We can use any name for 
a facade class. Now it becomes clear, that Laravel itself loads this array of aliases. This process happens in 
`Illuminate\Foundation\AliasLoader` service. It takes the `aliases` array, then creates a stack of PHP's `__autoload`
functions using `spl_autoload_register` function call:

{% highlight php %}
<?php

/**
 * Prepend the load method to the auto-loader stack.
 *
 * @return void
 */
protected function prependToLoaderStack()
{
    spl_autoload_register([$this, 'load'], true, true);
}
{% endhighlight %}

In this stack each function creates an alias for the respective facade class by using PHP's `class_alias` function:

{% highlight php %}
<?php

/**
 * Load a class alias if it is registered.
 *
 * @param  string  $alias
 * @return bool|null
 */
public function load($alias)
{
    if (isset($this->aliases[$alias])) {
        return class_alias($this->aliases[$alias], $alias);
    }
}
{% endhighlight %}

And that's all the magic with autoloading. Next time, when we try to access a facade class, that doesn't exist, PHP will check
the `__autoload` functions stack to get a necessary autoloader. By this time `AliasLoader` has already registered everything.
According to the `aliases` array from `config/app.php` each autoloader resolves original class and then creates an alias for it.

So, next time when you write something like this:

{% highlight php %}
<?php

$books = Cache::get('books:popular');
{% endhighlight %}

you should understand that behind the scenes `Cache` is resolved by Laravel to `Illuminate\Support\Facades\Cache`.

## Create a Custom Facade

Now when we have understood the magic behind facades, it's time to create our own one. This process is very simple and consists
of four steps:

- create a service class
- bind it to the IoC container
- create a facade class
- configure a facade alias configuration

We start with a service class. For example we'll create a `Stripe` service for processing paments in out application:

{% highlight php %}
<?php

namespace App\Payments\Stripe;

class Stripe extends Payment 
{
    public function process()
    {
        // some logic
    }
}
{% endhighlight %}

To use facades we need to be able to resolve this class out of the IoC container, so let's create a binding.
The best place to put this a binding is a custom service provider. For example we create `PaymentServiceProvider` and
add this binding in a `register` method.

{% highlight php %}
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider 
{
    public function register()
    {
        $this->app->bind('payment', App\Payments\Stipe::class);
    }
}
{% endhighlight %}

Now we must configure Laravel to load our new service provider. Add it to `providers` array in the `config/app.php` file:

{% highlight php %}
<?php

return [
    // ...
    'providers' => [
        // ... 
        App\Payments\PaymentServiceProvider::class
        // ...
    ]
];
{% endhighlight %}

Next, we can create our own facade class. Let's put it in `app\Facades` directory:

{% highlight php %}
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Payment extends Facade
{
    protected static function getFacadeAccessor() { return 'payment'; }
}
{% endhighlight %}

Finally, we can add an alias for our facades in `aliases` array in the `config/app.php` file:

{% highlight php %}
<?php

return [
    // ..
    'aliases' => [
        // ...
        'payment' => App\Facades\Payment::class
        // ...
    ]
];
{% endhighlight %}

That's all, we have successfully created a Laravel facade. Feel free to test it. Now there is no more magic about
facades for us. We have traveled from using `Cache` facade and understanding how it works to creating our own 
`payment` one. 
