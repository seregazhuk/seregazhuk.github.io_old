---
layout: post
title: Abstraction in PHP
tags: [OOP, PHP]
---

## A trap of code reuse
What is abstraction in PHP? Everybody understands it, but it is sometimes difficult to explain. 
The first thing that comes to mind is *"it is about code reuse"*. And it is what we have learnt from 
many OOP tutorials all over the internet. You want to reuse some code? Ok! Go and create an abstract 
class, then extend your classes from it and done!

When we come the PHP MVC-framework world, here we see the same approach and idea of an abstraction as 
a code reuse. Many modern frameworks (Larave, Yii, Phalcon) force you to extend a base model class in order to implement
your own models (User, Post, Product) if you want to take advantage of some common, model related code 
in the parent:

{% highlight php %}
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // ...
}
{% endhighlight %}

If you extend your models or controllers from framework's base model, have a look at the code of this base 
model. What about code reuse? Do you really need all of theese methods in your child classes?

And here is a trap. There are many different ways to achieve code resuse and abstraction is the worst one.

## Define a new data type

Lets look at an abstraction from another side. PHP as a language has different data types (integer, float, string, array and so on). 
If we need to implement operations with a math logic, we can use integers and floats. If we need some boolean 
logic values, we use boolen variables. You know it, I know it, everybody knows it, becouse it's basics.

But what if we need more complex data, for example we want to hold user and information, associated with him. Of course
it can be done with arrays:

{% highlight php %}
<?php 
$user = [
    'name' => 'John',
    'email' => 'johndoe@mail.com',
    'age' => 30
];
{% endhighlight %}

This structure is valid, but it isn't very usefull. And here come classes. Let's consider them 
like new data types in our application. In PHP we don't have appropriate data type to hold user
information data, so we create a new one. We define properties and methods to implement the ways in
which we can use variables of our new particular type.

{% highlight php %}

<?php

class User {
    protected $name;
    protected $email;
    protected $age;

    public function __construct($name, $email, $age)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
        ...
    }

    public function isYoung()
    {
        return $tis->age < 18;
    }
}

{% endhighlight %}

Move back to our common types: integers and arrays. We know that we can't use square brackets with integer variables:

{% highlight php %}
<?php
$integerVar = 3;
echo $integerVar[0];
{% endhighlight %}

We simply know it from our experience. We have been using language enough to understand difference
between integers and arrays and their behaviour. 

In our case we have created a new data type in our code base: class User. Now every developer who works with our
code base can read through the code and unserstand new data type.

Creating a new abstract class, we have a new interface for this data type. When we provide public methods we are saying 
*"Hey, with variable of this data type you can do this and it will behave in this particular way"*. Like it is with arrays, 
integers and strings, so it should be with the new objects.

{% highlight php %}
<?php

$user = new User('John', 'john@mail.com', 30);
var_dump($user->isYoung()); // false

{% endhighlight %}

## Summary

Using an abstraction to avoid duplications is not a very good approach for the DRY principle. The idea is
that the creation of an abstract class is the process of defining a new data type in our langauage. 
Defining a new abstract class we are saying that array types are not enough for the data we are
handling, so we are gonna add some more types.
