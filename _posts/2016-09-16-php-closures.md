---

title: "PHP Closures"
layout: post
tags: [PHP]

---


## Macros

When we talk about closures we often think about anonymus functions. Functions without name:

{% highlight php %}
<?php

$heyFunc = function($name) {
    return "Hey, {$name}";
}

echo $heyFunc('John');
{% endhighlight %}

If we take a context of a single web request, named functions exist for the request life cycle. Anonymus
functions exist only as long as you need them to be. So they can be considered as little macros. In the body
of the anonymus function we code some logic, and then we simply execute the macro where we need it.

{% highlight php %}
<?php

$arr = [1, 2, 3, 4, 5, 6];
array_walk($arr, function(&$number){
    $number *= $number;
});

print_r($arr);

/*
Array
(
    [0] => 1,
    [1] => 4,
    [2] => 9,
    [3] => 16,
    [4] => 25,
    [5] => 36
)
*/
{% endhighlight %}

Here we have a marco to count a square of a number and it exists only for as long as it
is needed.

## Objects
When we create an anonymus function and assing it to the variable, PHP turns it into the object of
the *Closure* class. The *Closure* class is an extraordinary class. We can't create instances of it
by this code: `$closure = new Closure();`. And we can't extend it with child classes, becouse it is
marked as *final*. But this class has an interesting method `bindTo()`.
