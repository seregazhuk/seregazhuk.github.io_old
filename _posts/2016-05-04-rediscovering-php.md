---

title: "Rediscovering PHP"
layout: post
tags: [PHP]

---

I like PHP, it is an easy, flexible language. We type something, refresh our browser and see results. No 
compilation, everything works "out of the box". But we should be very carefull with PHP, with it's 
flexibility out of the box it comes with some strange behaviours. Probably you will never find them in
your code base, but forewarned is forearmed.

## References

When we unset the reference, we only break the binding between variable's name and variable's content. This
does not mean that variable's content will be destroyed.

{% highlight php %}
<?php

$foo = 'bar';
$baz = &$foo;
unset($foo);

echo $baz; // 'bar'
{% endhighlight %}

In the example above variable *$baz* still contains reference to *bar* content. PHP will remove this content
only after all references will are destroyed.

## Unset

Let's continue our research in *unset* operator. PHP manual says: *"unset() destroys the specified variables".* 
Very clear, yes? But it becomes very tricky, when it is used inside user function.

### Globalized variable

If we unset a globalized variable inside a function, only the local variable is destroyed. The variable in 
the calling environment will retain the same value as before unset() was called.

{% highlight php %}
<?php

function destroy_val() 
{
    global $val;
    unset($val);
}

$val = 'test';
destroy_val($val);
echo $val(); // test

{% endhighlight %}

If we want to unset a global variable we should use *$GLOBALS* array:

{% highlight php %}
<?php

function destroy_val() 
{
    unset($GLOBALS['val']);
}
{% endhighlight %}

### Variable passed by reference.

Here is some code sample:

{% highlight php %}
<?php 

function foo(&$bar) 
{
    unset($bar);
    $bar = 'baz';
}

$bar = 'test';
echo $bar, "\n"; // test

foo($bar);
echo $bar, "\n"; // ?

{% endhighlight %}
What do you think about the value of *$bar* variable? We know that when we pass arguments be reference into a
function, these variables may change their values in the parent scope. But what about *unset()* call?

It turnes out that PHP will destroy only a local variable, a parent scope will not be touched. The variable in
the calling environment will be the same as before *unset()* call. The output of the above code sample will
be:

{% highlight python %}
test
test
{% endhighlight %}

### Static variables

We know that static variables save their values between function calls. But what if we unset a static variable?

{% highlight php %}
<?php

function foo()
{
    static $bar = 1;
    $bar ++;

    echo "Before:", $bar, ", ";
    unset($bar);

    $bar = 'test';
    echo "after:", $bar, "\n";
}

foo();
foo();
foo();
{% endhighlight %}

Do you know the output of this code? Here PHP will destroy a variable only in the context of the rest of a
function.

{% highlight python %}
Before unset: 1, after: test
Before unset: 2, after: test
Before unset: 3, after: test
{% endhighlight %}
