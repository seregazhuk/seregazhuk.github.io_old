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

