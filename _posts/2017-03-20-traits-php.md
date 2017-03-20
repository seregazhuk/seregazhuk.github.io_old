---
title: "Traits: Copy-Paste Behavior"
tags: [PHP, OOP]
layout: "post"
description: "traits in PHP: copy-and-paste behaviour."
---

In PHP we don't have multiple inheritance. Our child classes should always have only one parent class. Some people argue that this is a bad approach because they want to inherit from multiple classes to get access to some useful methods. I hope you are not one of them. Do you remember that inheritance is not about code reuse? It is about one basic type and its specifications. But there are situations where we want to share some functionality between classes that are not related. Copy and paste? 

For example, we have an online store application. It has a catalog, that consists of categories and products. We want our URLs to be pretty with nice human readable slugs instead of ids. So, we need to use slugs for both categories and products.   

{% highlight php %}
<?php 

class Product
{
    protected $name;

    public function slug() 
    {
        $cleared = preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name);

        return strtolower(cleared);
    }
}

class Category 
{
    protected $name;

    public function slug() 
    {
        $cleared = preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name);

        return strtolower(cleared);
    }
}
{% endhighlight %}

So we end up with code duplication. Duplication leads to bugs. So this is not a very good implementation. Create a `BaseModelWithSlug` also is not a very smart idea. You see the problem: we have some shared functionality in not related classes. So we need some way to extract this functionality and then paste it in our classes. And this is exactly the purpose of traits.

Here is a description from [php.net](http://php.net/manual/en/language.oop5.traits.php):

> Traits are a mechanism for code reuse in single inheritance languages such as PHP. A Trait is intended to reduce some limitations of single inheritance by enabling a developer to reuse sets of methods freely in several independent classes living in different class hierarchies. 

In other words, traits allow us to extract a closely related group of methods into a single reusable item. And then this item can be pasted into a class. So what is the difference here with a multiple inheritance? A trait is not about inheritance at all. A trait is not inherited from, it is included into a class as a sort of mixin. Traits also provide more control on resolving conflicts that arise when using multiple inheritance. They give us an ability to control ambiguities if a method is declared in several *mixed-in* traits. Like an abstract class traits cannot be instaintiated.

How does it work? Consider trait as a *copy-paste* at the language level. When a class uses a trait, this trait will be pasted into a class at a compile time, *before* the class definition will be parsed. So, when we create objects of this class, all the methods and properties from the trait will be available despite not having them in the class definition itself.

Now, when we know enough about traits and how they work, it's time to create one. Let's call it *HasSlug*.

{% highlight php %}
<?php 

trait HasSlug 
{
    public function slug() 
    {
        $cleared = preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name);

        return strtolower(cleared);
    }
}

class Category 
{
    use HasSlug;

    protected $name;
}

class Product 
{
    use HasSlug;

    protected $name;
}
{% endhighlight %}

Now our code looks much better, but not yet perfect. We have our `HasSlug` trait and the logic responsible for creating slugs is no longer duplicated. `Product` and `Category` classes simply *use* this trait. With keyword `use`, we tell PHP interpreter to copy the contents of `HasSlug` trait and put it in the location where this keyword is used. The line with keyword `use` will be replaced with the contents of trait *before* any code will be executed because PHP needs to find out the final structure of the class.
But, what if we want slugs to be created from different properties? Now we have hardcoded `$this->name`, but later we decided that product should have slug from its `shortName` property. We need our trait to be customizable. 

{% highlight php %}
<?php 

trait HasSlug 
{
    public function slug()
    {
        $string = $this->getStringForSlug();

        $cleared = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);

        return strtolower(cleared);
    }

    /** 
     * @return string
     */
    protected abstract function getStringForSlug();
}

class Category 
{
    use HasSlug;

    protected $name;

    protected function getStringForSlug()
    {
        return $this->name;
    }
}

class Product 
{
    use HasSlug;

    protected $name;
    protected $shortName;

    protected function getStringForSlug()
    {
        return $this->shortName;
    }
}
{% endhighlight %}

In the snippet above we have added a one abstract method `getStringForSlug`, that returns a string. Then a slug will be generated from this string. So, in every class, we can now customize our slugs, but at the same time, the logic of slugs creation is located in one place. No code duplication. Win.

But with benefits comes problems. And again we come back to types and interfaces. Let's consider a bit more complex example of `Category` class. 

{% highlight php %}
<?php 

class Category extends Model 
{
    use HasSlug;

    protected $name;

    protected function getStringForSlug()
    {
        return $this->name;
    }

    public function parent() {
        // returns parent category
    }

    public function products() {
        // returns the products
    }   
}
{% endhighlight %}

What public interface does this class have? Before using trait it consisted of two methods: `parent` and `products`. But the trait has implicitly modified the class interface and added a new `slug` method to it. And there is no guarantee for other classes that `Category` provides this method. We need to visit its source code, then jump to trait source code and find it out. Looks like we need an interface here to make `Category` again trustable for our code base.

{% highlight php %}
<?php 

interface Sluggable 
{
    public function slug();
}

trait HasSlug 
{
   // ... 
}

class Category implements Sluggable
{
    use HasSlug;

    // ...
}
{% endhighlight %}

With `Sluggable` interface, we can safely type-hint it in places where we need to call `slug` method and sleep safely. The class that consumes `HasSlug` trait now also guarantees that it has `Sluggable` functionality by implementing the appropriate interface. So, any class that wants to consume `HasSlug` trait should also implement `Sluggable` interface. It's time to modify `Product` class.

{% highlight php %}
<?php 

class Product implements Sluggable
{
    use HasSlug;

    protected $name;

    // ...
}
{% endhighlight %}

With this approach, our application stays robust. For small and crud applications it will be too overwhelming, but if you have a large complex code base, may be a corresponding interface for a trait will not be superfluous.

One more problem can arise with traits when trait uses a property that is *supposed* to be in the consuming class. Remember our first implementation of `HasSlug` trait? I'll remind it's code to you:

{% highlight php %}
<?php 

trait HasSlug
{
    public function slug() 
    {
        $cleared = preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name);

        return strtolower(cleared);
    }
}
{% endhighlight %}

And then we use it in `Product` class that has `title` property instead of `name`. Now application will be broken every time we call `slug` method in this class. 

#### Summary

Traits are very powerful and flexible tool in languages without multiple inheritance. They can improve your code base and remove duplication from your classes. But from the other side, they can add more complexity or even break your application.

