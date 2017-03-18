---
title: "Inheritance: Families Of Related Types"
tags: [OOP, OHP]
layout: post
description: "Inheritance in PHP"
---

What's the first thing that comes to your mind when you hear word *inheritance*? I have no doubt that it is *code reuse*. Right? Let's forget about *code reuse* and start from scratch.

In [one of my previous posts]({% post_url 2016-03-20-abstraction-in-php %}), I have considered abstraction as a process of creating new data types in our language. Then we use can `extends` keyword to create child classes, the specifications for one of the new types. These child classes will inherit all of the parent's non-private properties and methods. Despite the fact that these child classes are specifications, the client code still considers them as the basic parent's type. The client code still expects them to behave exactly as their parent does. What on earth does it mean? Let's take an example.

{% highlight php %}
<?php 

class Task {
    protected $is_closed;

    public function close()
    {
        $this->is_closed = true;
        $task->closed_at = date("Y-d-m H:i:s");

        return $this;
    }
}

class Project extends Task { }
{% endhighlight %}

We have a simple inheritance hierarchy. Class `Project` extends `Task` and *inherits* its non-private properties and methods. Both of them now represent a new type *task* in our application. The client code considers them both as *tasks*. Now our client code can safely use method `close` with both classes.

{% highlight php %}
<?php 

class User {
    public function completeTask(Task $task) 
    {
        // some staff
        $task->close();
    }
}

$user->completeTask(new Task());
// ...
$user->completeTask(new Project());
{% endhighlight %}

Everything works fine when there is no code in child classes. But it is stupid to have empty child classes, our `Project` class is not a specification of its parent. It is a simple copy of it. In real-world hierarchies, we always add some code to child classes. And here we can mess up our application. How? Ok, let's make `Project` class specific:

{% highlight php %}
<?php 

class Task {
    protected $is_closed;

    public function close()
    {
        $this->is_closed = true;
        $task->closed_at = date("Y-d-m H:i:s");

        return $this;
    }
}

class Project extends Task {
    public function close() 
    {
        // ... some project specific staff
        parent::close();
    }
}
{% endhighlight %}

Do you see why our application can die? Look carefully at the return value of the parent and child implementations of the `close` method. Exactly! Project returns nothing. And now our naive user class is going to chain some methods like this:

{% highlight php %}
<?php 

class User {
    public function completeTask(Task $task) 
    {
        // some staff
        $task
            ->close()
            ->setUser($this);
    }
}

// still works
$user->completeTask(new Task()); 
// trying to get property of non-object
$user->completeTask(new Project()); 
{% endhighlight %}

You see the danger now. The type's interface is broken. When `Project` extends `Task` it promises to behave exactly the same way, but now it breaks this promise and also breaks our application. So, every time we add a new code to a child class, we should do it very carefully.

One important thing about inheritance is its depth. How many levels of inheritance hierarchy should we have? The answer is: less is better. Two levels are the best variant. Even if all child classes respect their parents' public interface and behave exactly as their parents, so our client code can safely rely on them, we still have one problem. Why? Because of human nature. When we many levels of inheritance we have to keep in mind all these layers to understand the whole object interface. We should jump from one class to another to find what we need. And bugs will appear. Do you remember the previous example, where we forgot to return `$this`? Or we can override parent method and forget to call the parent implementation and now application will be broken. That is why two or three levels of inheritance hierarchy should be enough. When hierarchy grows, it comes out of our control.

We have started this chapter with the question about *inheritance* and with the most popular answer *code reuse*. I think that these thoughts of *code reused* have been influenced mostly by frameworks. Don't get me wrong, I have nothing against them, on the contrary, I think that they strongly help us as developers to make our job. But when we learn a new awesome MVC framework, we always meet inheritance in terms of code reuse. You want your model to be an *Active Record*? Extend basic `Model` class. You need to create a controller? Extend base `Controller` class. You see, when you need some functionality, you go and use `extends` keyword. In most cases everything is fine. Unlikely that your `PostsController` will actually become a model or a view. So, next time, when you will extend some class to get access to some of its useful methods, keep in mind that your child class and the parent should be of one data type. If not, you will undoubtedly have problems with this inheritance hierarchy in future. And try to keep your hierarchies as small as possible. Two levels are the best variant: a common data type (*parent*) and its specification (*child*). Of course, not every application can be suitable for such conditions, but *you should have a very solid reason to go deeper than two levels*.