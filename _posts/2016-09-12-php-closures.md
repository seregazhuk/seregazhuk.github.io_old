---

title: "PHP Closure As a Mmacro"
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

This method allows you to get access to protected and private properties of other objects. It creates a 
clone of the closure, but one *bound* to another object. So if the closure has a reference to `$this`, 
the scope of `$this` can be changed dynamically:

{% highlight php %}
<?php

class Secret
{
    private $value = 'secret-value';
}

$secret = new Secret();
{% endhighlight %}

In the code above there is no way to get the value of the `$value` property. But we can do it with the help of
the *bindTo* method and a closure with the reference to `$this`:

{% highlight php %}
<?php

$closure = function() {
    return $this->value;
};

$getSecret = $closure->bindTo($secret, $secret);
echo $getSecret(); // "secret-value";
{% endhighlight %}

The *bindTo* method accepts two parameters. The first one is the object that closure is bound to. The second is
optional and provides a new scope for the closure. When we pass the same object as the second parameter, we bind
this closure to the object as it is a method of that object.

## Laravel's Implementation of Macros

Lets create a simple class:

{% highlight php %}
<?php

class Cat {
    
}

$cat = new Cat();
$cat->say();
{% endhighlight %}

This code will fail becouse of undefined method `say()`. Now we try to use Laravel's *MacroableTrait* trait:

{% highlight php %}
<?php

use Illuminate\Support\Traits\MacroableTrait;

class Cat {
    use MacroableTrait;
}

$cat = new Cat();

Cat::macro('say', function(){
    echo "Meow!";
});

$cat->say(); // "Meow!"
Cat::say();  // "Meow!"
{% endhighlight %}

This can be achieved with the help of the *MacroableTrait*. It allows us to dynamically add a method to any PHP class.
So, how it works?

The first method to pay attention is `macro`:

{% highlight php %}
<?php

/**
* Register a custom macro.
*
* @param  string    $name
* @param  callable  $macro
* @return void
*/
public static function macro($name, callable $macro)
{
    static::$macros[$name] = $macro;
}
{% endhighlight %}

This method stores passed closure in a static property, indexed by `$name`. 
There are also two magic methods: `__call` and
`__callStatic`. They are executed, when we try to call a method that does not exist in the object or in the class. 

First of all we check, if we have stored a macro with such method name with `hasMacro()` method. If `true` we create a 
new closure and bind it to our class, if it is a static call, or to an object, if not:

{% highlight php %}
<?php

public static function __callStatic($method, $parameters)
{
    if (static::hasMacro($method)) {
        if (static::$macros[$method] instanceof Closure) {
            return call_user_func_array(Closure::bind(static::$macros[$method], null, get_called_class()), $parameters);
                                        
        } else {
            return call_user_func_array(static::$macros[$method], $parameters);
                                        
        }
    }

    throw new BadMethodCallException("Method {$method} does not exist.");
                
}

// ...

 public function __call($method, $parameters)
{
    if (static::hasMacro($method)) {
        if (static::$macros[$method] instanceof Closure) {
            return call_user_func_array(static::$macros[$method]->bindTo($this, get_class($this)), $parameters);
                                        
        } else {
            return call_user_func_array(static::$macros[$method], $parameters);
        }
                
    }

    throw new BadMethodCallException("Method {$method} does not exist.");
                
}
{% endhighlight %}
