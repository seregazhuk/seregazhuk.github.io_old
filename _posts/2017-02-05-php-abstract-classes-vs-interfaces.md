---
title: "Abstract Class VS Interface"
layout: post
tags: [PHP, OOP]
---

## Abstract class
Abstract class represents a new *data type* in your application. Classes define a blueprint for the objects, and we know how to use these objects of the certain class type. Just like we know how to use strings and arrays. When we extend one class from another, we create a new *sub-type*. According to **Liskov Substitution Principle**, we can use these sub-types without any special knowledge, only by reading the parent class public interface.

Let's consider `Cache` class hierarchy:

{% highlight php %}
<?php

namespace Cache;
abstract class Cache {}

namespace Cache;
class FileSystemCache {}

namespace Cache;
class RedisCache {}

namespace Cache;
class NullCache {}

{% endhighlight %}

All of these classes should behave as a parent `Cache` class.

## Interface
Interface describe an aspect of a type. In PHP we don't have multiple inheritance, but we can implement many interfaces. According to **Interface Segregation Principle**, interfaces should be small and specific. In the context of the previous example, let's define an interface for the objects, that can be cached `Cacheable`. 

{% highlight php %}
<?php

interface Cacheable {}

class Category implements Cacheable {}
class Product implements Cacheable {}
{% endhighlight %}

We can notice that many interfaces are usually named in a common way. They usually end with *able* or *ing*. And furthermore, interfaces can be implemented in entirely different types, which doesn't have anything in common. In the example above, we two different classes `Category` and `Product`, but they both implement one interface and can be used by `Cache` class interchangeably. `Cache` class doesn't care what type of object it works with. The only `Cacheable` aspect of type matters. 

## On The Contrary
Like with an abstract class, objects that implement one interface should have some common behavior. So what happens if we define a `Cache` interface instead of an abstract class? 

Interfaces provide only methods signatures, they don't have any logic. In the terms of types: they provide only one aspect of the object's behavior.

That means that we can create different *types* that implement `Cache`: for example `ActiveRecord` or `Log`. It may look like *flexibility* but ends up with god objects with many different empty or never used methods. 

## Summary
An interface annotates an aspect of a type. When we define a new base type, there is no need to extract an interface from classes. Use abstract classes instead.

