---
title: "OOP Design Principle: The Principle of Least Knowledge"
layout: "post"
description: "Design Principle: The Principle of Least Knowledge in PHP. Law of Demeter in PHP"
tags: [PHP, OOP]
---

In this article, I'm going to touch a problem known by many names, one of which is the *Law of Demeter*. But honestly speaking, it is not even a law, but a guideline. The rules promoted by this principle are:

1. Each unit should have only limited knowledge about other units: only units "closely" related to the current unit.
2. Each unit should only talk to its friends; don't talk to strangers.
3. Only talk to your immediate friends.

These rules sound a bit confusing: *units*, *friends*, *strangers*. How to apply all of this to the codebase? What does each of these terms mean within our codebase?

So, the idea behind this principle means that, inside your application, the code that we write should express knowledge only of its surroundings. This guideline promotes the notion of loose coupling in your codebase, which leads to more maintainability. And no let's change a bit the rules above and apply them to object-oriented programming. Imagine that we have a class which implements a given method. This method should only call the following objects:

1. The object that owns this method.
2. Objects passed as arguments to the method.
3. Objects that are dependencies of the owner instance (are held in instance variables).
4. Any object which is created locally in the method.
5. Global objects that can be accessed by the owner instance within the method.

On the other side, if an object knows too much about another (knowledge how to access the third one) is considered as bad design, because the object has to unnecessary traverse from top to bottom of the chain to get actual dependencies it needs to work with.


Lets' have a look at the common example:

{% highlight php %}
<?php

class InvoiceController
{
    public function create()
    {
        // ... 
        try {
            $user->getAccount()
                ->getBalance()
                ->substractSum($invoiceTotal);
        } catch (NotEnoughFundsException $e) {
            // handle expcetion
        }
    }
}
{% endhighlight %}

It looks like we are passing the total sum of the invoice through to `substractSum()` method and catching the exception, if the user doesn't have enough funds to pay the invoice.

To resume: we have an error handling here, and a lazy load in the background for *UserAccount* and *UserBalance* objects. This very small snippet of code looks fine **today**, but in the **future**, it can produce for us some potential problems.

### One arrow principle
In original, it is *The One Dot Principle*, but in PHP we don't have dots in method calls chains, we have arrows. If you find yourself using more that one arrow to access a property or method, the chances are high that you are not following *The Principle of Least Knowledge*. In our example we have this:

{% highlight php %}
<?php

$user->getAccount()
    ->getBalance()
    ->substractSum($invoiceTotal);
{% endhighlight %}

Of course we can't fix this issue by adhering to a single arrow, but on every new line like this:

{% highlight php %}
<?php

$account = $user->getAccount();
$balance = $account->getBalance();
$balance->substractSum($invoiceTotal);
{% endhighlight %}

Nothing has changed here. The key problem still remains. We call *getAccount()* method, which returns an object, that has the *getBalance()* method. And then the *getBalance()* method offers the *substractSum()* method. There is too much knowledge here. In the terms of the *Law of Demeter* this code snippet reached through the intermediate objects to invoke the *substractSum()* method at the end of the chain. It is another way of *tight coupling*, which we should always be trying to avoid in our codebase. *Tight coupling* reduces the quality of our application code, making it harder to maintain. When we modify one piece of tightly coupled code, then when need to review and modify all the other members of the tightly coupled relationships.

In our example, in six months the business can ask us to add some credit facilities instead of pre-funding invoices. For business, it is a very small change for business, but a massive change for our codebase, thanks to our tightly coupled design.

If we had respected the *Law of Demeter* in our code, all of this extra work might have been avoided. Instead of a chain of method calls to trigger an invoice payment, our controller might have looked like this:

{% highlight php %}
<?php

class InvoiceController 
{
    public function create()
    {
        // ...
        try {
            $user->payInvoice($invoice);
        } catch(InvoicePaymentException $e) {
            // handle exception
        }
    }
}
{% endhighlight %}

We have refactored the chain of method calls into a single line of code:

{% highlight php %}
<?php

$user->payInvoice($invoice);
{% endhighlight %}

We've hidden all the knowledge, that the controller's action method shouldn't have had. The knowledge of the inner process of the invoice payment now is encapsulated in `User` class and has been removed from the place it doesn't belong to. The controller shouldn't know anything about the process of payment, it simply triggers the action. In our case, the only knowledge that it has is that an exception can be thrown.

{% highlight php %}
<?php

class User
{
    public function payInvoice(Invoice $invoice)
    {
        try {
            $this->getAccount()->payInvoice($invoice);
        } catch (NotEnoughFundsException $e) {
            // throw new InvoicePaymentException();
        }
    }
}
{% endhighlight %}

In the code above there is a new `payInvoice()` method in the `User` class. It is a proxy method. It checks that it's parameter is an instance of the `Invoice` class. Then it passes this instance to this user's `UserAccount` instance. 

### Code smells
You can notice that this approach may lead to some code smells. For example, our `User` class can become a God object, with lots of proxy methods. You can also say, that we are violating the *Single Responsibility Principle*. Is it `User` responsibility to pay the invoice, if behind the scenes it proxies it to other objects?
There are two ways to handle these issues. If you want to stay with syntaсtic sugar *payInvoice* method in the `User` class, you can extract the `PaysInvoices` trait and place all related to payment process methods there. With this approach, our `User` class is not overwhelmed with proxy methods, and they are all located in one place:

{% highlight php %}
<?php

trait PaysInvoice 
{
    public function payInvoice(Invoice $invoice)
    {
        try {
            $this->getAccount()->payInvoice($invoice);
        } catch (NotEnoughFundsException $e) {
            // throw new InvoicePaymentException();
        }
    }
}


class User
{
    use PaysInvoice;

    // ...
}

{% endhighlight %}

Another way is to get the middle collaborator and work with it. In out example it is the `UserAccount` instance:

{% highlight php %}
<?php

class InvoiceController
{
    public function create()
    {
        // ...
        try{
            $userAccount->payInvoice($invoice);
        } catch(InvoicePaymentException $e) {
            // handle exception
        }
    }
}
{% endhighlight %}

In this case, we hide the entire logic of the payment process behind `payInvoice()` method in the `UserAccount` class. Our controller again knows nothing how the invoice is being paid.

### Summary
The *Law of Demeter* gives us a guideline how to achieve loose coupling in our code and helps to properly encapsulate the knowledge of some complicated operations or logic into the places where they should be.
In our first example we had a controller action method that was reaching through the chain of objects to get at the `substractAmount()` method of the `UserBalance` class. Not it is clear why it is a bad design and what problems it can cause. The developer that works on the `UserAccount` or the `UserBalance` class may not know that the controller was accessing the `$balance` instance through `User` and `UserAccount` instances.
When the process of the payment is encapsulated in one place the controller becomes ignorant to any changes being made to this process.