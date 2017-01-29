---
title: "Testing: Mocks vs Stubs"
tags: [PHP, Testing]
layout: post
description: "Testing: The difference between mocks and stubs."
---

In testing, we often have to deal with a lot of different jargon: *dummies*, *stubs*, *mocks*, *fakes*.

## Stubs
A stub is a generic term for any kind of pretend object used in place or a real one for testing purposes. They provide canned answers to calls and usually don't respond to anything outside. They also may record some information about calls, for example, `Email` stub can remember the messages it sent. Stubs can verify their state.
## Mocks
Mocks are pre-programmed objects with expectations about what methods should be called, with what parameters, and what they should return. So, during the test, we can assert those expectations. Mocks allow us to observe their behaviour, can be verified upon it.

## Messages between objects
To understand a difference between purposes for usage stubs and mocks we should consider different types of messages being sent from one object to another. When testing, we think about our application as a series of messages passing between a set of black boxes. These messages can be divided into two main categories: **incoming** and **outgoing** messages.

The incoming messages represent the public interface of the receiving object.
The outgoing messages are incoming into other objects and are part of some other object's interface.

We are interested in outgoing messages. Some of them have no side effects and matter only to their senders. The sender cares about the message result, but the entire application doesn't care if the message was sent. This type of message is called *queries* and they should be tested by the sending object. Query messages are the part of the receiver public interface, which already should have its own test for a state. Queries often look like *asking a question*:

{% highlight php %}
<?php

class Order 
{
    public function getTotalPrice() 
    {
        // return total price of all the items in the order.
    }
}
{% endhighlight %}

Method `getTotalPrice()` in the `Order` class is an example of the *query* message. No matter how many times it will be called, there will be no side effects to the application.

But many outgoing messages do have side effects (a mail was sent, a file was written, a database row was saved), upon which the entire application depends. These messages are *commands*. The sending object should prove that they have been properly sent. In the case of tests, this means that we should assert the number of times and with what arguments the message was sent.

Commands may be considered as *instructions*:

{% highlight php %}
<?php 

class Order
{
    public function sendToClient()
    {
        // updating order status in the database
        // send an email to the customer 
    }
}
{% endhighlight %}

Method `sendToClient()` is a command. It will update a record in the database to change the order status and an email will be sent to the customer.

## Stub queries
It doesn't matter how many times *queries* have been sent, because they don't make any change to the system state. With `getTotalPrice()` method we want to know only the result price, *it doesn't matter how* it was calculated:

{% highlight php %}
<?php

$item = Mockery::mock(OrderItem::class);

// creating a stub
$item->shouldReceive('getPrice')->andReturn(100);

// wrong, we shouldn't set expectations!
$item->shouldReceive('getPrice')->once()->andReturn(100);
{% endhighlight %}

## Mock commands
When we need to check if the certain message was really sent, we set expectations for it. Because commands change the state of the system, they need to be verified.
For example, `sendToClient()` method calls a `Mailer` class `send` a message. So, we should inject it, and mock `send` method:

{% highlight php %}
<?php

$mailer = Mockery::mock('Mailer');
// setting expectations
$mailer->shouldReceive('sendMail')->once();

$order->sendToClient($mailer);
{% endhighlight %}

Mocks use *behavior verification*. `send` is a *command* message, so we set an expectation on it, that proves that it was called when we send an order to a client. We do this check by telling the mock what to expect during setup and asking the mock to verify itself during verification.

## Summary
1. Try to avoid methods that are both queries and commands. Don't mock something that returns a meaningful value.2
2. Don't set any mock expectations of query messages. Only ask object a question, don't give it any commands.
3. You may need to set up expectations when mocking commands.