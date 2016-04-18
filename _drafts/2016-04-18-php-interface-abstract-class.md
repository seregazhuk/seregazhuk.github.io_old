---

title: 'PHP: Interface and Abstract Class'
layout: post
tags: [PHP, OOP]

---
One of the most popular questions on the interview is "What is the difference in interfaces and abstract classes?".
So let's see the differenece.

## Interface
Before getting into theory, let's refresh in memory how *interface* is defined:

{% highlight php %}
<?php

interface InterfaceName {
    public function method($parameter);
}
{% endhighlight %}

An interface can contain methods and constants, but can't contain any variables. 
Methods must be public and have no implementation. In PHP one interface 
can be inherited from another one by *extends* keyword:

{% highlight php %}
interface ParentInterface {
    public function method($parameter);
}

interface ChildInterface {
    public function another_method($parameter);
}
{% endhighlight %}

One difference with classes that interfaces can't override it's parent's methods. So, when one
interface extends another they can't have methods with the same names.
