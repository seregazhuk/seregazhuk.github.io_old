---
title: "Antipattern Poltergeist"
tags: [PHP, OOP]
layout: post
description: "Poltergeist design antipattern with examples in PHP"
---

The opposite of the [God class]({% post_url 2017-03-11-oop-god-objects %}) is Poltergeist. It are some sort of *useless class*. The main characteristic of a poltergeist: it represents a piece of control logic for making things happen and then it disappears, no state, no data and no behavior. Let me explain. 

Assume that we have payment process in the controller:

{% highlight php %}
<?php

class OrderController
{
    public function payOrder($orderId)
    {
        if(isset($_POST['Order'])) {
            $order = Order::findOrFail($orderId);
            $invoice = new Invoice();
            $invoice->setOrder($order);
            $invoice->setUser(Auth::user());

            if($invoice->save()) {
                $email = new Email();
                $email->setSubject('Invoice for order#' . $order->id);

                // ...

                if($email->send()) {
                    $this->redirectTo('orders');
                } else {
                    // ... fail
                }
            } else {
                // ... fail
            }
        }
    }
}
{% endhighlight %}

I can bet that you have written this sort of code, or surely have met it in some application. The code doesn't look very readable with all these nested conditionals. So, we decided to refactor it and replace the payment process into the object and then call a method on it. The idea looks great and SOLID: our object will have a single responsibility - payment process.

{% highlight php %}
<?php

class InvoicePaymentHandler
{
    public function make(array $data)
    {
        $order = Order::findOrFail($orderId);
        $invoice = new Invoice();
        $invoice->setOrder($order);
        $invoice->setUser(Auth::user());

        if($invoice->save()) {
            $email = new Email();
            $email->setSubject('Invoice for order#' . $order->id);
            ...

            return $email->send();
        } 

        return false;
    }
}
{% endhighlight %}

And the controller now looks very nice and clear. We have removed all this messy code behind the `make` method call. Now the controller follows *Tell, Don't Ask Principle*. It tells the instance of `InvoicePaymentHandler` to make an invoice, and behind the scenes, this instance validates data from the request, creates an invoice record in the database and on success sends an email with payment details.

{% highlight php %}
<?php

class OrderController
{
    public function payOrder($orderId)
    {
        if($_POST['Order']) {
            $invoicePayment = new InvoicePaymentHandler();
            if($invoicePayment->make($_POST['Order'])) {
                $this->redirectTo('orders');
            }
            
            $errors = $invoicePayment->getErrors();
            $this->redirectBack($errors);
        }
    }
}
{% endhighlight %}

So, what's wrong here? Take a look at the poltergeist description and then at the `InvoicePaymentHandler` class. This class doesn't carry any internal state and its only responsibility is to trigger methods on the other objects. As a result, we have added an unneeded layer of abstraction in the whole application architecture, just to replace some code into another place.
We think that we followed *Single Responsibility Principle* and *Tell, Don't Ask Principle*, but instead, we have an object-oriented container for some procedural code.

`InvoicePaymentHandler` class doesn't play any solid role in the application, it is used only as a container for some code and this code is *hardcoded* there, so there is no way to reuse `InvoicePaymentHandler` in the system. Now we have extra code to maintain and test. It is often hard to read and understand code with a poltergeist, because at first, we need to find out what poltergeist does and then mentally replace it to see the real code flow.

To remove a poltergeist, delete the class and insert its functionality in the invoked class:

{% highlight php %}
<?php

class OrderController
{
    public function payOrder($orderId)
    {
        if($_POST['Order']) {
            $order = Order::findOrFail($orderId);

            try {
                $order->makeInvoice(
                    $_POST['Order'], Auth::user()
                );
            } catch(InvoiceCreationException $e) {
                $this->redirectBack(
                    $e->getMessage()
                );
            }

            $this->redirectTo('orders');
        }
    }
}
{% endhighlight %}

We have delegated the process of invoice creation to `Order` class. Previously we had to ask `Order` about its state (total products and their price), now `Order` itself can create an invoice according to its own state. Then we can trigger an event and send an email. There is no more need in `InvoicePaymentHandler` class. We have also removed the process of payment creation out of the controller and now can reuse it in another place of our application.

### Poltergeist vs Commands

At first sight, poltergeist objects looks very similar to Command Pattern. The key difference is that commands are often more generic and can contain some state to be reused. On the opposite poltergeist objects are often special purpose objects with a single method, they exist to *make some noise in the system* and then they disappear. You can treat poltergeists as crunches that help to construct or initialize other objects. Poltergeists represent a static action, but commands represent a configurable action.

### Summary

Is is important to know when your classes add some value and simplify the whole design, instead of increasing complexity of the system without providing any benefits. Poltergeist classes are being used only to invoke methods in another class. They have neither their own clear responsibility nor internal state, and as a result, they add an unneeded layer of abstraction in the application design. The code becomes less readable and less maintainable. It often happens when you have some procedural code and move it as it is into another class, that is being used as a container for this code.
