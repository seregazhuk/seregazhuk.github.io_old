---

title: "Laravel: IoC Container"
layout: post
tags: [Laravel]

---

From Laravel <a href="https://laravel.com/docs/5.2/container" target="_blank">documentation</a>: 
*The Laravel service container is a powerful tool for managing class dependencies and performing dependency injection.* Here
we are not going to dig in dependency injection term itself. Just notice that IoC container and dependency injection are very 
coupled terms. The IoC container is made to make the process of managing dependencies easier and cleaner.
Especially it becomes valuable when you deal with complex objects. When your dependencies have their dependencies, and you
need to construct all of these objects and it can be a nightmare. You need to remember the way each object should be instantiated.
Here is some dummy example of using Dependency Injection to achieve Inversion of Control.

{% highlight php %}
<?php

class Foo
{
    protected $bar;

    public function __construct(Bar $bar)
    {
        $this->bar = $bar;
    }
}

class Bar 
{
    protected $baz;

    public function __construct(Baz $baz)
    {
        $this->baz = $baz;
    }
}

// this code doesn't look very nice
$foo = new Foo(new Bar(new Baz()));
{% endhighlight %}

By using constructor injection we have delegated the creation of all the dependencies to the client code. We have achieved clean
and testable classes, but we also have achieved a cluttered client code. The client code now has to create all of these objects.

And here the IoC container comes into play. You can declare all your dependencies once in one place, and later simply get them (*resolve*) out
of the container. Instead of managing dependencies ourselves, we delegate them to the container. 

## Laravel IoC

Laravel IoC container is the heart of the framework, it keeps all different framework components connected together. It allows them to 
communicate with each other. Laravel itself is an IoC container. Its *Application* class extends *Container* class. Everything that happens 
inside your application at some point has an interaction with the IoC container. It is the key difference between frameworks and libraries.

The container in Laravel is commonly used to bind and resolve instances of your service providers. I am sure, that if you open any of
your service provider classes, you will find bindings there. Something like this:

{% highlight php %}
<?php

// service provider code

public function register() {
    App::bind('App\Contracts\Payment', function(){
        return new App\Services\Stripe(Config::get('payments.stripe.key'));
    });
}
{% endhighlight %}

In service provider we can get the container via `$this->app` property. Method `bind` registers a binding. The first parameter is a unique 
identifier of the binding, for example, a class name or an interface. The second parameter is a callback, that returns an instance of the
binding. This callback will be executed every time we resolve the `Payment` interface:

{% highlight php %}
<?php

$paymentService = App::make('App\Contracts\Payment');
$paymentService->process(data);
{% endhighlight %}

Remember, that in this closure we receive the container itself as an argument. Why? It may be useful when we need to resolve some dependencies
for our bindings:

{% highlight php %}
<?php

App::bind('App\Contracts\Payment', function($app){
    return new App\Services\Stripe(Config::get('payments.stripe.key'), $app['httpClient']);
});

{% endhighlight %}

Another words, container is a place to store closures that resolve various classes. We can resolve a class anywhere in
our application, if it has been registered in the container.

Lets have a look at a real example, how it works in the application. For exmaple we have `BillingController`:

{% highlight php %}
<?php

namespace App\Http\Controllers;

use App\Contracts\Payment;

class BillingController extends BaseController
{
    public function process(Payment $paymentService)
    {
        // ...
    }
}
{% endhighlight %}

As you remember, we have registered `Stripe` as the implementation of `Payment` contract. Now if we want to change it and
start using `PayPal`, we need to change only one line of code in the binding:

{% highlight php %}
<?php

App::bind('App\Contracts\Payment', function($app){
    return new App\Services\PayPal(Config::get('payments.paypal.key'), $app['httpClient']);
});
{% endhighlight %}

## Reflection

Notice, that there is no need to use the container for binding classes, that do not implement interfaces. The container is smart enough to create them. Such classes 
are constructed with the help of PHP Reflection API. Reflection API is used to inspect classes and methods.

{% highlight php %}

<?php

class Stripe 
{
    public function __construct(HttpClient $http)
    {
        // ...
    }
}

// resolve instance of Stripe like new Stripe(new HttpClient());

$stripeService = App::make('App\Services\Stripe');
{% endhighlight %}

Imagine that in the example above the container does not have a binding for `Stripe`. But as the result, we have an 
instance of `Stripe` and also with an instance of `HttpClient` injected. Magic? No, its Reflection API:

1. First of all, Laravel tries to get a resolver for `Stripe`. There is no binding and no resolver for it. 
2. Next, it examines a constructor of `Stripe` for any dependencies recursively and resolves them. 
3. Finally, it instantiates a new instance of `Stripe` and returns it.

But what happens if we try to do the same but with a contract? And imagine that we have no binding for it.

{% highlight php %}
<?php

// Exception: Target Payment is not instantiable.
$payment = App::make('App\Contracts\Payment');
{% endhighlight %}

This happens because Laravel does know what implementation you need for this contract. Interfaces cannot be instantiated.
Reflection API works only with concrete classes or parameters with a default value.

## Shared bindings

*Shared* binding means that this binding should be resolved only once, and the same instance should be returned on
subsequent calls to the container. Here I have a dummy example to show how it works:

{% highlight php %}
<?php

App::singleton('sharedInstace', function() {
    return new stdClass();
});
// ...
$obj = App::make('sharedInstance');
$obj->property = 123;
// ...
$obj2 = App::make('sharedInstance');
echo $obj2->propery; // 123
?>
{% endhighlight %}

As you can see, the container instantiates the instance of `sharedInstance` binding only once per application lifecycle.

One more way to register a *shared* binding is method `instance`. It will bind an existing object instance into the 
container. The given instance will always be returned on subsequent calls into the container.

{% highlight php %}
<?php

$obj = new stdClass();
$obj->property = 123;
App::instance('sharedInstance', $obj);

// ...
$obj1 = App::make('sharedInstance');
$obj1->property = 'test';

// ...
$obj2 = App::make('sharedInstance');

echo $obj2->property; // 'test'
?>
{% endhighlight %}

## Conditional Binding

You can register a binding to the container if it hasn't already been registered before:

{% highlight php %}
<?php

App::bind('obj', function(){
    return new stdClass();
});


App::bindIf('obj', function(){
    $obj = new stdClass();
    $obj->test = 'test';
    return $obj;   
});

$obj = App::make('obj');
$obj->test; // PHP error:  Undefined property: stdClass::$test
?>
{% endhighlight %}

This code will not bound a new object with property `test` because there already exists a binding with such alias.

## Contextual Binding

Let's imagine that you have two classes that use implementations of the same interface, but we need to inject into them
different implementations. As we have learned before, we can once register a binding of some implementation and later use
it in our code. But in this case, we can use *contextual bindings*. How does it work? Laravel provides a nice interface for it:

{% highlight php %}
<?php

$this->app->when('App\Order')
    ->needs('App\Contracts\Payment')
    ->give('App\Services\Srtipe');

{% endhighlight %}

If we need to create a complex object with its own dependencies, we can pass a closure to the `give` method:

{% highlight php %}
<?php

$this->app->when('App\Order')
    ->needs('App\Contracts\Payment')
    ->give(function(){
        // creating a complex object    
    });

{% endhighlight %}

## Tagging

How to find out what has already been registered into the container? If we want to resolve something if we know how 
it has been bound. But we can group our bindings with tags:

{% highlight php %}
<?php

$this->app->bind('stripePayment', function(){
    // ...
});

$this->app->bind('payPalPayment', function(){
    // ...
});

$this->app->tag(['stripePayment', 'payPalPayment'], 'payments');
{% endhighlight %}

Now we can get all of these bindings with `tagged` method:

{% highlight php %}
<?php

$services = $this->tagged('payments');
{% endhighlight %}

## Extending

Sometimes you may need to inject a dependency into one of the bindings. We can do it with the `extend` method:

{% highlight php %}
<?php

$this->app->extend('payment', function($app, $payment) {
    $payment->setTariff(new MonthStrategy());
});
{% endhighlight %}

This method will resolve the binding and execute your closure with the container and the resolved binding 
as the parameters.

## Events

Every time something is being resolved out of the container an event is fired. You can add a listener for this
event with the `resolving` method. It accepts a closure with a resolved instance and the container as parameters. You
can attach a handler to listen for any resolved instance or you can pass a class and then typehint a resolved instance:

{% highlight php %}
<?php

$this->app->resolving(function($binding, $app) {
    // attaches listener for object of any type
}); 

$this->app->resolving(Payment::class, function(Payment $payment, $app){
    // attaches listener only for objects of type Payment
});
{% endhighlight %}
