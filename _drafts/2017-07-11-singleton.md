---

title: "Singleton: Anti-Pattern Or Not"
tags: [PHP, OOP, DesignPatterns]
layout: post

---

Singleton is one of the simplest patterns to understand. The main goal of it is to limit the existence of only one instance of the class. The reason for it is usually the following: *only one class object is required during the lifecycle of the application and you need this object to be avaialable anywhere in the application, i.e. global access.

The Singleton pattern assumes that there is a static method for getting an instance of the class (`getInstance()`. When calling it a reference to the original object is returned. This original object is stored in a static variable, which allowes to keep this original object unchanged between `getInstance()` calls. Also a constructor is `private` to ensure that you always use only static `getInstance` method to get the object. In PHP we have some *magic* methods which can be used to create a new instance of the class: `__clone` and `__wakeup`, they also should be `private`:

{% highlight php %}
<?php

class Singleton
{
    protected $instance;

    private function __construct();
    private function __clone();
    private function __wakeup();

    public static function getInstance() 
    {
        if( is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
{% endhighlight %}

This pattern can be usefull when we have some kind of a shared resource in our application: a classic example is a database connection. Different parts of application might want to use this connection.

## Conclusion

In practise the Singleton is just a programming technique, which can be a useful part of your toolkit. Singletons themselves are not bad, but they are *hard to do right*. We always consider singletons as globals. Singleton is **not a pattern to wrap globals**. The main goal of this pattern is to guarantee that **there is only one instance of the given class** during the application lifecycle. Don't confuse singletons and globals. When used for the purpose it was intended for, you will achieve only benefits from the Singleton pattern.