---

title: 'Liskov Substitution Principle in PHP'
layout: post
tags: [OOP, PHP, SOLID]
comments: true

---

## Program to an Interface, not implementation

Let's refresh a classic definition:
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

When we define an abstraction we also define an interface for our client code. When dealing with an *interface* everything
was simple: we must implement all methods or there will be an error.

Within a class abstraction everything comes more tricky. In child classes we can override and change behaviour of their parent. 
And PHP will not complain. For example, if a parent class returns string from it's method, we can override it and return an 
array in a child. From PHP's point of view everything is fine. In a parent class a method gets an array as a parameter, but
in a child you can change this behaviour and wait for a number as a method parameter. Everything you like!

And when PHP is silent, LSP sais: *"Hey, Child classes should never break the parent class' type definitions"*. But why?
PHP does not complain about it. Why should I care about type definitions?

The unswer is in the question. The key word here is *type*. You *should* care about types, bacause when you define a new class, 
you define a new *type* in your language. And like a creator you have a full access to define rules for this new type. That's why 
PHP is silent here. You simply say:*"Hey, PHP, your basic types are not ennugh for me, so I'm going to create a new one."*. 
And PHP has nothing else to do but to listen to you. PHP sais: *"OK, go and create a new type!".*

After this dialog with PHP and atfer creating a new data type it's your responsibility to achieve 
the same behaviour of objects in a hierarchy. As soon as we have a parent class and a child, we have a hierarchy. 
And we have additional responsibilities.

{% highlight php %}
<?php

abstract class Vehicle {
     
    public function startEngine() {
        // Default engine start functionality                
    }
     
    public function accelerate() {
        // Default acceleration functionality
    }
}


class Car extends Vehicle
{
    // Empty class
}

class Motorcycle extends Vehicle
{
    // Empty class
}


{% endhighlight %}

In example above we have our parent class, which defines a new data type *Vehicle*. The characteristics of this 
data type are described in two public methods. Under abstract class we have two empty child classes, they 
are our data type specializations. While they are empty they behave *exactly* the same as the parent type *Vehicle*.
When creating an empty child class that extends another one, inctances of the empty child class will inherit all
the public and protected properties and methods of the parent. Everything is clear.

But problems arrive when we begin to add a new code to child classes. It's important not to alter the characteristics 
of the parent's interface. Of course we can override parent methods in order to get the specialized behaviour in 
child classes, but we should implement these changes very carefully. We should care to ensure that child's methods
are compatible with parent's specification. There are three rules to achieve this.

### Rule 1. Input parameters.
This rule is about the parameters of the overriding methods. The number of the input parameters in child class' 
method  *should be the same or more than* the number of the input parameters in the parent's method. 
And ofcourse acording to the number of parameters we should pay attention on their data types. These types
*should be the same or more generic* than the types of the parent's method paremeters. May be it sounds a bit complex, 
let's see an example.

{% highlight php %}
<?php

abstract class Mechanic
{
    public function fixVehicle(Car $car)
    {
        // implementation
        return $car;
    }

}

// Vehicle data type hierarchy
class Vehicle
{
    
}

class Car extends Vehicle
{

}

class SportsCar extends Car
{
    
}
{% endhighlight %}

In the previous example pay attention to *Mechanic* class. It requires an instance of *Car* in a call of
*fixVehicle* method. Let's create a child of *Mechanic* class.

{% highlight php %}
<?php

class SportsCarMechanic extends Mechanic
{
    public function fixVehicle(Car $car)
    {
        // implementation
        return $car;
    }
}
{% endhighlight %}

The input parameters in the child *SportCarMechanic* class are the same as in the parent's method. Nothing to worry here.
Now let's change the method's singature, so our *SportCarMechanic* class requires an instance of *SportCarVehicle*. 

{% highlight php %}
<?php

class SportsCarMechanic extends Mechanic
{
    public function fixVehicle(SportsCar $car)
    {
        return $car;
    }

}
{% endhighlight %}

The code seems to be quite logical. Our specialized *Mechanic* class requires a specialized *Car* version, right? But 
it's completely wrong. Our mind tells us that logically it's OK, but it is logically right in the real world, not in 
OOP world.

Our client code consider the abstract parent class as the single source of truth about it's data type. And it sais that
variables of data type *Mechanic* have *fixVehicle* method, that accepts an instance of *Car* as input parameter. So
for safety our client code *always* provide an instance of *Car* to the method. But *SportsCarMechanic* class has 
broken the abstract parent's contract, because it accepts *SportCar* instances or more specialized versions. When our client
code sends *Car* instance to *SportsCarMechanic* method, our application dies. Why? Because of specialized version 
of *SportCar* class.

{% highlight php %}
<?php

class SportCar extends Car 
{
    public function specializedMethod()
    {
        // some implementation
    }
}

class SportsCarMechanic extends Mechanic 
{
    public function fixVehicle(SportCar $car)
    {
        $car->specializedMethod(); // !!!
        // ...
        return $car;
    }
}
{% endhighlight %}

As I metioned before cliend code sends an instance of *Car* to all versions of *Mechanic* class. But *SportCarMechanic*
requires an instance of *SportCar*, becouse it uses it's method *specializedMethod* which does not exist in *Car* data type. 
And here our application successfully dies. How to fix it? Simply replace the type hint of $car parameter to more generic one.

{% highlight php %}
<?php

class SportsCarMechanic extends Mechanic 
{
    public function fixVehicle(Car $car)
    {
        // ...
        return $car;
    }
}
{% endhighlight %}

I know that it does not look logical in a real world. It looks wrong. It sounds wrogns. But acording to OOP it is right. 
Now our *SportsCarMechanic* class does not break it's parent contract.

### Rule 2. Return values.

The second rule is about the return values from the overriden method. The types of the returned values of the overriden method
*should be the same or more specific* as the types returned by the same method in the parent class.

This rule is much easier to understand than the previous one. 

{% highlight php %}
<?php

abstract class CarFactory 
{
    /**
     * @return Car
     */
     public function getCar()
     {
        // ... some logic
        return new Car;
     }
}

class SportCarFactory
{
    public function getCar()
    {
        return new SportCar();
    }
}

{% endhighlight %}

PHP does not allow us to define return types of the methods, so it's our job to tell in doc blocks about
return types. Let's come back to our client code that work with *CarFactory* class. The client code considers our 
*CarFactory* as the data type that will return an instance of *Car* from *getCar* method. In *SportCarFactory* 
class we return an instance of *SportCar* class, which itself is an instance of *Car* class. So there are 
no suprises here for our client code.

But what if we return a instance of a more generic type, for example an instance of *Vehicle* class.

{% highlight php %}
<?php

class SportCarFactory 
{
    public function getCar()
    {
        return new Vehicle();
    }
}
{% endhighlight %}

And again from PHP's point of view there is no criminal here. And even logically it looks right: *Vehicle* 
class is the parent of *Car*, so they both may be considered as objects of the same data type. But the problem 
here is that *Vehicle* is higher in hierachy than *Car*. It may happen that there will be no method in *Vehicle* class
that our client code is expecting to call and our application here dies.

### Rule 3. Exceptions.

The last rule is the easiest one. It is about throwing exceptions in the parent class and in it's child classes. The 
overriden methods in child classes *should throw the same or more specialized exceptions* that can be thrown in the 
parent class. 
Here everything is similar to the previous rule. Let's start with a bad example, when we violate this rule.

{% highlight php %}
<?php 

class VehicleException extends Exception {}
class CarException extends Exception {}
class SportCarException extends CarException {}

abstract class CarFactory 
{
    public function getCar()
    {
        throw new CarException();
    }
}

class SportCarFactory
{
    public function getCar()
    {
        throw new VehicleException;
    }
}

{% endhighlight %}

We have exceptions hierarchy: Expcetion => VehicleException => CarException => SportCarException. 
Our client code works with an instance of *SportCarFactory*. Client code handles exceptions of*CarException* 
class. But we throw an instance of *VehicleException*. Poor client code now has an uncought exception 
and again in this case our application dies.

How to fix it? As rule says *always return exceptions of the same type or more specialized*.

{% highlight php %}
<?php 

class SportCarFactory extends CarFactory 
{
    public function getCar()
    {
        // ...
        throw new SportCarException
    }
}
{% endhighlight %}

## Summary

Many tutorials tell us that Liskov Substitution Principle is about interfaces and replaceable instances. It's 
partly true. But it's out job to know that this principle is also about types definitions, about
parent's class contract and it's child classes specializations.

