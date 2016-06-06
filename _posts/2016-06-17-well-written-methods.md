---

title: "Well written methods rule: one level of indentation"
layout: post
tags: [PHP, OOP]

---

What is the main purpose of creating a new method? To reduce the complexity of the code. We create a 
method to hide and forget some logic. Later we use methods and ignore their logic details. One of the
signs of the bad method is deeply nested loops or conditional operators. 

{% highlight php %}
<?php

public function processEmailLogs($dirName) {
    foreach(scandir($dirName) as $file)
    {
        $file = fopen('mail_log.txt', 'r');

        if($file) {
            $line = fgets($file);
            if($line !== false) {
                // process line
            } else {
                echo "Log is empty\n";
                return false;
            }
        } 
        else {
            echo "Couldn't open file!\n";
            return false;
        }
    }

    return true;
}
{% endhighlight %}

It is always a good idea to extract one of the loops into its own method:

{% highlight php %}
<?php 

public function processEmailLogs($dirName) {

    $file = fopen('mail_log.txt', 'r');

    foreach(scandir($dirName) as $file)
    {
        $this->processLogFile($file);
    }

    return true;
}

protected function processLogFile($fileName)
{
    $file = fopen($fileName, 'r');

    if(!$file) {

        echo "Couldn't open file!\n";
        return false;
    }

    $line = fgets($file);
    if(!$line){
        echo "Log is empty\n";
        return false;
    }

    // process line
    
    return true;
}

{% endhighlight %}

Now logic details are separated in different methods, each of them is very simple and clear. Well-named
methods can help you to create a left-documented code.

## This is very simple operation to create a method for it

*Simple methods always tend to be comples while the application grows*.

Sometimes the code looks very simple and we don't want to create a separate method for two or three lines
of code. But in the future a business may change, and what today seems very simple tomorrow may grow into a
very complex object. For example, we want to create a task that deletes useless orders:

{% highlight php %}
<?php

public function clearOrders($orders)
{
    if(!$order->isPaid && !$order->hasItems()) {
        $order->delete();
    }  
}
{% endhighlight %}

But then our manager tells us that we must also delete those orders that haven't been paid in a week and 
the total sum of the order is less than $50.

{% highlight php %}
<?php

public function clearOrders($orders)
{
    if(!$order->isPaid && !$order->hasItems() ||
        ($order->created_at <= self::ORDER_EXPIRED_TILL && $order->getTotalSum() < self::MAX_ORDER_SUM){
        $order->delete();
    }  
}
{% endhighlight %}

Ok, no we must change this logic everywhere in the code. Even if this logic is written only here, every time we meet this boolean operation
we must spend some minutes to parse it and to understand what is going on. The best way to handle it is to move this logic into its own method.
This method should have the name, that describes the whole operation so we can forget this operation and remember only this name.

{% highlight php %}
<?php 

if(!$order->canBeDeleted()) $order->delete();

{% endhighlight %}


In the code above we hide this terrible boolean operation behind a nice method `canBeDeleted`.

So, if I see some code that can be extracted to a method, I go and extract it. It doesn't matter how many lines
of code the method will be.

### Reasons for extracting a method:

- **Avoid code duplication**. The most popular and the most obvious reason.
- **Inheritance support**. It is much easier to override simple and clear methods.
- **Hiding an order of actions**. We can hide the order of the operations from the client if the client shouldn't know them.
- **Simplifying complex boolean operations**. When we meet complex boolean operations, we must parse them to understand them. 
Hide complex boolean conditions behind the method with the clear name describing these operations.
