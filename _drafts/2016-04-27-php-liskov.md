---

title: 'Liskov Substitution Principle in PHP'
layout: post
tags: [OOP, PHP, SOLID]

---

## Program to an Interface, not implementation

Let's refresh classic definition:
*objects in a program should be replaceable with inctances of their subtypes without altering the correctness of
the program*.

In the world of PHP it often means *programming to interface*: when a class uses an implementation of an interface,
it must be able to use any implementation of that interface without requiring any modifications. Here is a classic
example with a repository and a controller:

{% highlight php %}
<?php
interface PostsRepositoryInterface {

    // fetches all posts
    public function all();
}

// Controller code

public function index(PostsRepositoryInterface $repo)
{
    $posts = $repo->all();
    // some other logic
}

{% endhighlight %}

Here we are not coupled to a specific storage and we may change it without touching controllers logic. We can 
implement *MysqlPostsRepository* or *MongoPostsRepository* or even *RedisPostsRepository* and everything will
continue to work in our controller.

Without using *PostsRepositoryInterface* we should check the instance of object passed to controller and choose
a specific logic for it. For example different connections to storages and so on. Of course we can simply define
methods with the same name, but our client code does not know anything about them without interface. When client 
code sees that an object implements an interface, it know what public methods are callable. 

{% highlight php %}
<?php
public function index($repo)
{
    if ($repo instanceof MysqlPostsRepository) {
        // ...
    } elseif ($repo instance of MongoPostsRepository) {
        // ...
    }
    elseif ($repo instance of RedisPostsRepository) {
        // ...
    }
}
{% endhighlight %}

## Not only interfaces. What about abstraction?

When we define an abstraction we also define an interface for the client code. When dealing with an *interface* everything
was simple: we must implement all methods or there will be an error.

Within a class abstraction everything comes more tricky. In child classes we can override and change behaviour of their parent. 
And PHP will not complain. For example, if a parent class return string from it's method, we can override it and return an 
array in a child. From PHP's point of view everything is fine. In a parent class a method gets an array as a parameter, but
in a child you can change a behaviour and wait a number a method parameter. Everything you like!

And when PHP is silent, LSP sais: *"Hey, Child classes should never break the parent class' type definitions"*. But why?
PHP doesnot complain about it. Why should I care about type definitions?

The unswer is in the question. The key word here is *type*. You *should* care about types, bacause when you define a new class, 
you define a new *type* in your language. And like a creator you have a full acess to define rules for this type. That's why 
PHP is silent here. You simply say: "Hey, PHP, your basic types are not enough for me, so I'm going to create a new one.". 
And PHP has nothing else to do but to listen to you. PHP sais: "OK, go and create a new type!".


