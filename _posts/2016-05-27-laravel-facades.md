---

title: "Laravel: Don't Let Facades Confuse You"
layout: post
tags: [Laravel]

---

## Facade Design Pattern

Let's look at Gang of Four description of the Facade pattern: 

*This is a structural pattern as it defines a manner for creating relationships between 
classes or entities. The facade design pattern is used to define a simplified interface to a more complex subsystem.*


Accprding to the Gang of Four the Facade pattern is a structural pattern. The Facade pattern is a class, 
which wraps a complex library and provides a simplier and more readable interface to it. The facade itself maintains it's 
dependencies.

## Facades in Laravel

Laravel has a fature similar to this pattern, also named Facades. This name may confuse you, because facades in
Laravel don't fully implement the Facade design pattern. According to the <a href="https://laravel.com/docs/master/facades" target="_blank">documentation</a>:

*Facades provide a "static" interface to classes that are available in the application's service container.*

Another words facades serve as a proxy for accessing to the contatiner's services, which is actually the syntactic sugar for these services. Instead of having to
go through a testable and maintainable way of instaintiating a class, passing in all of it's dependencies, we can simply use a static interface, but behing
the scenes Laravel itself will take care of instantiating a class and resolving it's dependencies ot of the IoC container.

## Usage

Let's take a look at `Cache` facade:

{% highlight php %}
<?php

$val = Cache::get('key');
{% endhighlight %}

You can achieve the same results with the code below:

{% highlight php %}
<?php

$val = app()->make('cache')->get('key');
{% endhighlight %}


As mentioned before, you can use facade classes in Laravel to make services available in a more readable way. In Laravel all services inside the IoC
container have unique names, and all of them have their own facade class. To access a service from the container you can use `App::make()` method or
`app()` helper function. So there is no diffrenece between these lines of code:
{% highlight php %}
<?php

SomeService::someMethod();
// and
app()->make('some.service')->someMethod();
// or
App::make('some.service')->someMethod();
{% endhighlight %}

## How it works

All facade classes are extended from the base `Facade` class. There is only one method, that must be implemented in every facade class: `getFacadeAccessor()`
which returns the unique service name inside the IoC container. So it must return a string, that will be resovled then out of the IoC container. 

Here is the source code of the `Cache` facade:

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

Cache::get('key');
{% endhighlight %}

Here method `get()` actually exists in the service inside the container. It looks like we are calling a static method `get()` of `Cache` class, but 
as we have seen there is no such static method in `Cache` class. All the magic is hidden inside the basic `Facade` class.

Every facade is goning to extend the basic abstract `Facade` class. The magic is hidden inside three methods here:

- `__callStatic()` - simple PHP magic method
- `getFacadeRoot()` - gets service out of the IoC container
- `resolveFacadeInstance()` - is responsible for resolving the instance of the servce

`__callStatic()` is fired every time, when a static method that does not exist on a facade is called. So, after calling `Cache::get('key')` we are falling 
inside this method. Then we resolve an instance of the service behind a facade out of the IoC container by calling `getFacadeRoot()` method. 
