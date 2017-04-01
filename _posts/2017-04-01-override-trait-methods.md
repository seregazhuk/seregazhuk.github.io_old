---
title: "PHP: How to override trait method and call it from the overridden one?"
tags: [PHP, OOP]
layout: post
---

Consider this example, when we have a trait and a class that uses this trait. But we want to override a trait's method and also we want to call the initial trait's method. How can we do it? We can't use `parent`, `self`, `static` or anything like this because traits are only copy-and-paste code.

{% highlight php %}
<?php

trait Calculates {
    function calc($value) {
        return $v + 1;
    }
}

class Calculator {
    use Calculates {
    }

    function calc($value) {
        $v++;
        // call trait's method
    }
}
{% endhighlight %}

We can use traits conflict resolution mechanism. We can rename traits method name by using `as` keyword. We can also change it visibility to remove it from the class public interface:

{% highlight php %}
<?php

trait Calculates {
    function calc($value) {
        return $v + 1;
    }
}

class Calculator {
    use Calculates {
        calc as protected calculate;
    }

    function calc($value) {
        $v++;
        return $this->calculate($this);
    }
}
{% endhighlight %}

Now, this does the trick!