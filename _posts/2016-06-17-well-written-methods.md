---

title: "Well written methods"
layout: post
tags: [PHP, OOP]

---

What is the main purpose for creating a new method? To reduce the complexity of the code. We create a 
method to hide and forget some logic. Later we use methods and ignore their logic details. One of the
signs of the bad method is deeply nested loops or conditional operators. 

{% highlight php %}

{% endhighlight %}

It is always a good idea to extract one of the loops into its own method:

{% highlight php %}
<?php 



{% endhighlight %}

Now logic details are separated in different methods, each of them is very simple and clear. Well-named
methods can help you to create a left-documented code.

## This is very simple operation to create a method for it

Sometimes the code looks very simple and we don't want to create a separate method for two or three lines
of code. But in the future business may change, and what today seems very simple tomorrow may grow into a
very complex object. For example, we want to create a task that deletes empty orders:

{% highlight php %}
<?php

if(!$order->isPaid && !$order->hasItems()) {
    $order->delete();
}

{% endhighlight %}

But then our manager tells us that we must delete only those orders that haven't been paid in a week and 
the total sum of the order should be not less than $1000.

Ok, no we must change this logic everywhere in the code. Now it is clear that it would be better to 
move this logic into its own method:

{% highlight php %}
<?php 

if(!$order->canBeDeleted()) $order->delete();
{% endhighlight %}

So, if I see some code that can be extracted to a method, I go and extract it. It doesn't matter how many lines
of code the method will be.

### Reasons for extracting a method:
**Avoid code duplication**. The most popular and the most obvious reason.
**Inheritance support**. It is much easier to override simple and clear methods.
**Hiding an order of actions** We can hide the order of the operations from the client if the client is not interested in them.
**Simplifying complex boolean operations**. When we meet complex boolean operations, we must parse them to understand them. 
Hide complex boolean conditions behind the method with the clear name describing these operations.
