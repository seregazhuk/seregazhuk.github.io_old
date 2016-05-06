---

title: "Rediscovering PHP"
layout: post
tags: [PHP]

---

I like PHP, it is an easy, flexible language. We type something, refresh our browser and see results. No 
compilation, everything works "out of the box". But we should be very carefull with PHP, with it's 
flexibility out of the box it comes with some strange behaviours. Probably you will never find them in
your code base, but forewarned is forearmed.

I'm not going to tell you about trick with floating numbers, I think it is commonly known between PHP
developers. Just try to guess the output of this sample code:

{% highlight php %}
<?php

echo (int) ((0.1 + 0.7) * 10);
{% endhighlight %}

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
only after all references will be destroyed.

## Unset

Let's continue our research in *unset* operator. PHP manual says: *"unset() destroys the specified variables".* 
Very clear, yes? But it becomes very tricky, when it is used inside a user function.

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
What do you think about the value of *$bar* variable? We know that when we pass arguments by reference into a
function, these variables may change their values in the parent scope. But what about *unset()* call?

It turnes out that PHP will destroy only a local variable, a parent scope will not be touched. The variable in
the calling environment will be the same as before *unset()* call. The output of the above code sample will
be:

{% highlight python %}
test
test
{% endhighlight %}

### Static variables

What do we know about static variables? They save their values between function calls. But what if we unset 
a static variable?

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

Here PHP will destroy a variable only in the context of the rest of a function.

{% highlight python %}
Before unset: 1, after: test
Before unset: 2, after: test
Before unset: 3, after: test
{% endhighlight %}

## Switch

*Switch* operator is the basics. When we learned PHP  we also learned *switch* opeator and it's behaviour.

{% highlight php %}
<?php

$a = 'foo';

switch($a) {
    default: echo 'default'; break;
    case 'foo': echo 'foo'; break;
    case 'bar': echo 'bar'; break;
    case 'baz': echo 'baz'; break;
}
{% endhighlight %}

What is the output? From the manual we know about *default* section that *... this case matches anything that wasn't matched by the other cases*. 
It is also written that *It is important to understand how the switch statement is executed in order to avoid mistakes. The switch statement executes line by line (actually, statement by statement)*.
But it turnes out that not always in order line by line. *Default* block will be executed the last, even it is on the 
first line. So the output will be ``foo``. 

## Strings increment/decrement

Eveything is clear when we use increment/decrement with numbers. Just add or sub 1 from the number. And what if 
we use strings?

{% highlight php %}
<?php

$string = 'string';
echo ++$string, "\n"; 

$string = 'aa';
echo $string, "\n";

$string = 'zz';
echo $string, "\n";

$string = '12';
echo $string, "\n";

$string = 'string';
echo --$string, "\n";

$string = '12';
echo --$string, "\n";

{% endhighlight %}

The output of this code is very interesting.

{% highlight python %}
strinh
ab
aaa
13
string
11
{% endhighlight %}

When we have a number which is represented as a string, the logic is clear. Something strange happens with
other strings. And it is impossible to guess the result without reading manual. PHP follows Perl's rules when dealing 
with arithmetic operations on character variables. Another words you should consider characters as their ASCII codes.
And one notice here, that they cannot be decremented. 

## Ternary Operator

Imagine that you work with a legacy code and you have found something like this:

{% highlight php %}
<?php

echo (true?'true':false?'t':'f');
{% endhighlight %}

A stack of ternary expressions. What result do you expect to see? True? If so, you are wrong. When you stack ternary expressions they are 
evaluated from left to right. Let's be more verbous in the previous example.

{% highlight php %}
<?php

echo ((true ? 'true' : false) ? 't' : 'f');
{% endhighlight %}

Parentheses help now to unserstand how stack of ternary expressions works. Now it's clear that the output will be ``t``.

## Conclusion
Ofcourse you can say that you will never write such code, and you are right. Here we have a set of bad practices. 
But no one is safe from the legacy code and you should be prepared to understand how PHP behaves in such situations.

