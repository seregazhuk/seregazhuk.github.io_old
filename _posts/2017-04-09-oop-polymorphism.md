---
title: "Making Polymorph"
tags: [OOP, PHP]
layout: post
description: "Implementing polymorphism in PHP with examples"
---
    
In object-oriented programming, polymorphism is one of the most important concepts. The general description of the word *polymorphism* in the programming community is *the provision of a single interface to entities of different types*. There are several types of polymorphism, some of them cannot be achieved in PHP, due to language constraints.

### Ad-hoc Polymorphism

Ad-hoc polymorphism allows a polymorphic value to exhibit different behaviors when *viewed* at different types. The most common example of this is method overloading when we create different implementations that share the same method name *within the same class*. The only difference between these methods is in its arguments, they should be of different types. Then the compiler at a runtime chooses the appropriate implementation based on the type of the passed arguments. But sadly in PHP, we don't have such a feature. Our interpreter will simply die as soon as it finds a class with to or more identically named methods. 

### Subtype Polymorphism
This is the most commonly known type of polymorphism. In OOP terms it means that when we have methods in different classes that do similar things, we should give these methods the same name. These classes shouldn't even belong to a one base type. We ensure that all our classes have the same interface: they all have the same methods that take the same arguments. Why is it important? What does it give to us? When our interface is implemented, we don't need to care about how these classes work. According to their common interface, we know their methods, their behavior, so we exactly know how to work with all of them. There is no more reason to consider every single class separately, instead, we consider all of them as a whole. Let me show you some examples, to demonstrate it. At first, we will cover an example without polymorphism. 

For example, we have base `Cache` abstract class and some concrete implementations: `MemcaheCache`, `FileCache`, and `RedisCache`. And we have some logic to flush cache:

{% highlight php %}
<?php

class Application
{
    /**
     * @var Cache 
     */
    protected $cache;

    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function flushCache()
    {
        switch(get_class($this->cache)) {
            case "MemcacheCache": 
                $this->cache->clearMemcache(); 
                break;
            case "FileCache":
                $this->cache->unlinkFile(); 
                break;
            case "RedisCache": 
                $this->cache->flushRedis(); 
                break;
        }
    }
}
{% endhighlight %}

Now you see what happens when are forced different classes that do similar things but don't provide a common interface. We should use these ugly `switch` or `if-else` statements to provide a logic branch for every specific class. And next time when we add a new cache implementation we need to change this `flushCache` method and add a new branch for this class. To fix this issue we need a common interface. Then we can extract every branch to its own class and hide it behind the common interface. It looks like we need an abstract `flush` method in our base `Cache` class. Then in every child class, we write a concrete implementation for this method.

{% highlight php %}
<?php

abstract class Cache
{
    abstract public function flush();
}

class MemcacheCache extends Cache 
{
    public function flush()
    {
        $this->memcache->flush();
    }
}    

class RedisCache extends Cache 
{
    public function flush()
    {
        $this->redis->flush();
    }
}    

class FileCache extends Cache 
{
    public function flush()
    {
        unlink($this->file);
    }
}    
{% endhighlight %}

Now, we can modify `flushCache` method and remove all of these type checks and logic branches, because we don't care about concrete classes anymore. We know for sure that all of them implement `flush` method, so we can safely call it.

{% highlight php %}
<?php

class Application
{
    // ...
    public function flushCache()
    {
        $this->cache->flush();
    }
}
{% endhighlight %}

Besides the fact that `flushCache` method has become very simple and clear, we can now safely add new `NullCache` implementation without touching `Application` class at all. It already knows how to collaborate with newly created `NullCache` class. Very nice, right? This is what we call *polymorphism*. We have hidden *different logic behind one name*, so we can safely add new implementations and every class that knows this *name* automatically knows how to collaborate with new implementations.

We can achieve the same *different logic one name* with interfaces when our classes are not related. For example, we have `Cache` class that uses *cacheable* objects to store them in cache. Every *cacheable* object implements an interface that has a method for representing a cache key.

{% highlight php %}
<?php

interface Cacheable 
{
    public function getCacheKey();
}

class Product implements Cacheable 
{
    public function getCacheKey() 
    {
        return 'product_' . $this->id;
    }
}

class Category implements Cacheable 
{
    public function getCacheKey()
    {
        return 'category_' . $this->id; 
    }
}
{% endhighlight %}

`Product` and `Category` classes work very differently internally. But because they implement the same interface, we can use them both exactly the same way (of course, only in the context of this current interface):

{% highlight php %}
<?php

class Cache
{
    public function store(Cacheable $cacheable)
    {
        $this->store($cacheable->getCacheKey(), $cacheable);
    }
}

$cache = new Cache();
$product = new Product();
$category = new Category();

$cache->store($product);
$cache->store($category);
{% endhighlight %}

Here class `Cache` doesn't care what exactly class it works with. It only knows that it implements `getCacheKey()` method, and it is enough for it. It will work with *any* class that implements `Cacheable` interface.

Polymorphism here is the combination of implementing an interface (abstract class or *real* interface) in your classes and then depending only on that interface. 

### Parametric Polymorphism

Unlike ad-hoc polymorphism, this type of polymorphism PHP supports out of the box, because of the loosely typed nature of the language. When we have no type hints, we can pass into function/method anything we want:

{% highlight php %}
<?php

function sum($a, $b){
    return $a + $b;
}

echo sum((int)2, (int)3); // 5 
echo sum((float)1.6, (float)2.12); // 3.72
echo sum('abc', 4); // 4
{% endhighlight %}


The difference with *ad-hoc polymorphism* is that *parametric polymorphism* means that *we don't care about the type, we implement the function the same for any*. *Ad-hoc polymorphism* instead means that we have *a different implementation depending on the type of the argument*. Now let's refresh the example with `Cache` from *subtype polymorphism*:

{% highlight php %}
<?php

class Cache
{
    public function store(Cacheable $cacheable)
    {
        $this->store($cacheable->getCacheKey(), $cacheable);
    }
}
{% endhighlight %}

Method `store` here uses *parametric polymorphism*. It is implemented for any concrete implementation of `Cacheable` type. As long as it receives an argument that is an instance of the `Cacheable` data type, it should execute successfully, just as our `sum` function did. And again we achieve *the same name, different logic*. Same name here means that we don't care about the concrete implementation of the `Cacheable` data type, we accept any of them. And each concrete type will have its own implementation, its own different logic. 

As you can see *subtype polymorphism* and *parametric polymorphism* are closely related. We create different subtypes (with inheritance or interfaces), and then use them depending on the base type (class or interface).

