---
title: "Tell Don't Ask"
layout: "post"
tags: ['PHP']
---

*Procedural code gets information then makes decisions. Oject-oriented code tells objects to do things.* - Alec Sharp.

### Example of issues

In PHP you can meet it very frequently and sometimes may consider that it is actually the right way to do things. Take a moment and remember
controller methods that you have written in the past or you deal with them now in your legacy application. You can easily find places, where
client code asks a model layer for data to use it to make some sort of logic decision base on these values. As for me, I have done it hundreds 
of times in the past.

{% highlight php %}
<?php 

// ... 

class InvoiceController 
{
    public function payAction()
    {
        // ...     
        $userBalance = $user->getBalance();
        if ($userBalance < $invoiceTotal) {
            throw new NotEnoughFundsException();
        }

        $newBalance = $userBalance - $invoiceTotal;
        $user->setBalance($newBalance);

        // ...
    }
}
{% endhighlight %}

Here in our controller, we have an action method, that gets access to models properties to make some logic decisions, based on 
their values. The first one is some sort of validation of the user's balance for the required invoice sum:

{% highlight php %}
<?php

$userBalance = $user->getBalance();
if ($userBalance < $invoiceTotal) {
    throw new NotEnoughFundsException();
}
{% endhighlight %}

And then we directly change the `balance` property outside of the *User* object:

{% highlight php %}
<?php

$newBalance = $userBalance - $invoiceTotal;
$user->setBalance($newBalance);

{% endhighlight %}

Let's take a close look the *User* object itself. 

- Should it's `balance` property be public and accessible outside an instance?
- Should we give a setter to this property to the client code? 
- When in any part of our application we can simply call `$user->setBalance()` and change it?

Of course not! Mostly in any domain, user's balance should be private property. Is should be *encapsulated* inside the object, and cannot be accessible directly outside of it. The first case in out example is bad, but the second one is **unforgivable**.

But why? Why we should tell out objects to do things, instead of asking them for details and performing the logic ourselves? 
When we expose the details of the object's state, as we have done twice in the example above, we are tightly coupling the consuming code to these details. In the future, when we will make changes in *User* class, those tightly coupled details of it's state may leave to bug-like scenarios.

For example, our business team has decided to maintain user's balance in pennies, rather that in dollars. Now our developers team need to search the code base for all the locations, where the balance value is extracted and modified. 

### Fixing 

Now, when we have all this knowledge, let's go and fix our controller's code:

{% highlight php %}
<?php

// ...

class InvoiceController
{
    public function payAction()
    {
        // ...
        try {
            $user->payInvoice($invoice);
        } catch (NotEnoughFundsException $e) {
            // ...
        }
        // ...
    }
}
{% endhighlight %}

First of all, we have moved the validation code to the model, because it is not the controller's job to have knowledge about a model object's state. Then we have removed the direct modification the instance's balance from the controller.

*Remember: in controllers actions should not modify model object properties directly. Actions should cause things to happen, not to do the work themselves.*

We have fixed the previous two issues with a single line of code:

{% highlight php %}
<?php

$user->payInvoice($invoice);
{% endhighlight %}

This sort of smells is most commonly repeated in the action methods of an MVC-applction's controllers.

### Conclusion

Some people may treat this principle as *add a method to an object for every single operation we need to perform on its state*. At this point of view, our classes will become overloaded with these sort of methods. We can also be fanatical of this principle and start creating lots of *getter* methods. 

For example, we need to create a `SalesReport` object, that colloborates with `Invoice` instances. What if `SalesReport` cannot get any information from those invoices for the report, that it is going to build. It is clear that `Invoice` instances need to colloborate with `SalesReport` object and provide their details to it.

So, next time, when you catch yourself making a decision based on the values held, within an object's state in order to determine which block of code will be executed, notice, that you are likely violating *Tell Don't Ask* principle.