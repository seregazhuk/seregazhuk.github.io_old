---
title: "Open/Closed Principle"
layout: post
tags: [PHP, OOP, SOLID]
description: "Open-Closed design principle in PHP with examples"
---

> Software entities (classes, modules, functions, etc.) should be open for extension, but closed for modification.

Let's decrypt this description a bit. *Open* means that it should be simple to change the behaviour of a particular entity. *Closed* means chaging behavior without modifying existing source code. But рow can this be possible at all? To change the behavior wihtout modifying a source code? Did you notice a word *extension* in the description? It is a key. We are going to change behavior of the entity by extending it.

Sounds not very clear, right? I think at this point we need some code.

{% highlight php %}
<?php 

class Programmer
{
    public function code()
    {
        echo 'coding an app';
    }
}

class ProjectManager
{
    public function manage($programmer)
    {
        $programmer->code();
    }
}
{% endhighlight %}

In this a very simple example we have to nice classes from our day-to-day experience. We have a `Programmer`, who writes code, and a `ProjectManager` who manages programmers. Pretty simple. Then, as our team grows and we decide that we need a tester:

{% highlight php %}
<?php 

class Tester 
{
    public function test()
    {
        echo 'testing an app';
    }
}
{% endhighlight %}

So, we create a new class `Tester`, but our project manager can only manage programmers. We need to add a new *feature* for managing testers.

{% highlight php %}
<?php 

class ProjectManager
{
    public function manage($worker)
    {
        if($worker instanceof Programmer) $worker->code();
        if($worker instanceof Tester) $woker->test();
    }
}
{% endhighlight %}

Do you see the problem here? Every time when we need to *extend* a behavior, we need to modify existing code. But Open-Closed Principle sais the opposite. That we shouldn't modify existing code. How? We need to somehow abstract this behavior. We know, that to create an abstraction we can use *abstract class* or *interface*. In our case these two classes are not supposed to be of one type, instead, they have a common behavior, so we can extract an interface. 

>When we have a class that we want to extend without modifying, we can hide this extensible behavior behind an interface, and then change the dependencies.

So, first we need to do, is to extract an interface. From the project manager's point of view, both programmer and test *work*. The fact that one writes the code and the other tests it - just details. They all *work*. It seems that we can extract `Workable` interface with a one simple method `work`. And then we can hide implementation details from `ProjectManager`, leaving him alone only with `Workable` interface. 

{% highlight php %}
<?php 

interface Workable 
{
    public function work();
} 

class Programmer implements Workable
{
    public function work()
    {
        echo 'coding an app';
    }
}

class Tester implements Workable
{
    public function work()
    {
        echo 'testing an app';
    }
}

class ProjectManager
{
    public function manage(Workable $worker)
    {
        $woker->work();
    }
}
{% endhighlight %}

`ProjectManager` now accepts any `Workable` worker and manages him. We can remove all of these type checks since project manager doesn't get deep into details, how every worker does his job. Next time when our team again grows and we decide to hire a designer, there is no need to modify `ProjectManager` to work with `Designer`. The only thing we need to do is to implement `Workable` interface and we are ready to go. Now we can *add features* and *extend behavior* only by writing a new code, and without modifying the existing one. Win! 

## Don't over-engineer

As in the case of Single Responsibility Principle, you should use open/closed carefully. In real life, it doesn't mean, that once you have written your code and your tests pass, you should never open this class and change it again. Requirements often change and you will never predict the future. So, if you need to change some lines in your class, do it. Maybe it will be a better idea, then creating a new class with an interface and increase the complexity of the system. If you add a new class every time you need a change or a feature, instead of a maintainable system you will have a complex system which is harder to change.
So, this principle should be applied for those classes, which are most likely to be changed.

Don't try to *predict the future* as much as you can. For example, we have `PaymentGateway` class with `makePayment` method, that accepts only credit cards. If there is no need in other *payable* classes, leave it as it is, unless it's crystal clear where any extensions would take place.

{% highlight php %}
<?php 

class PaymentGateway
{
    public function makePayment(CreditCard $card)
    {
        // ...
    }
}
{% endhighlight %}

Of course, requirements may change, and in the future, the system should also process vouchers. And then we need to extract `Payable` interface and type hint in `makePayment` method.

{% highlight php %}
<?php 

class CreditCard implements Payable {}
class Voucher implements Payable {}

class PaymentGateway
{
    public function makePayment(Payable $payable)
    {
        // ...
    }
}
{% endhighlight %}

But, take care of Open-Closed Principle only *after* you were forced to make changes. Don't try to predict the future, when implementing a class for the first time.



