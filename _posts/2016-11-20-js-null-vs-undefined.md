---
title: "JavaScript: NULL vs Undefined"
layout: post
description: "What is the difference between NULL and undefined in Javascript."
tags: [JS]
---

What is the difference between them? Let's type some code:

{% highlight javascript %}
a.val = 1; // Uncaught ReferenceError: a is not defined(…)

a = null;
a.val = 1; // Uncaught TypeError: Cannot set property 'val' of null(…)
{% endhighlight %}

In both cases, we have similar errors.

Next, we create a function:

{% highlight javascript %}
function a(){ this.bar = 'baz';}
(new a).bar // "baz"
(new a).foo // undefined
{% endhighlight %}

But what if we declare `bar` property as `null`:

{% highlight javascript %}
function a(){ this.bar = null;}

(new a).bar; // null
(new a).foo; // undefined

(new a).hasOwnProperty('bar'); // true
(new a).hasOwnProperty('foo'); // false
{% endhighlight %}

Now it is clear, that *null* means that variable `is defined`, but has `no value`. *Undefined* means that there is no such variable at all.