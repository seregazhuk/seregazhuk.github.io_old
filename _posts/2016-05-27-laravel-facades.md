---

title: 'Laravel: Facades'
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

You might have noticed that there is no diffrenece between these lines of code:
{% highlight php %}
<?php

SomeService::someMethod();
// and
app()->make('some.service')->someMethod();
// or
App::make('some.service')->someMethod();
{% endhighlight %}

As mentioned before, you can use facade classes in Laravel to make services available in a more readable way. In Laravel all services have their facade class.
