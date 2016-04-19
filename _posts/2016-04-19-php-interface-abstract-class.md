---

title: 'PHP: Interface and Abstract Class'
layout: post
tags: [PHP, OOP]

---
One of the most popular questions on the interview is *"What is the difference in interfaces and abstract classes?"*.
So let's see the differenece.

## Interfaces
Before getting into theory, let's refresh in memory how an *interface* is defined:

{% highlight php %}
<?php

interface InterfaceName {
    public function method($parameter);
}
{% endhighlight %}

An interface can contain methods and constants, but can't contain any variables. 
All methods must be public and have no implementation. 

### Inheritance
In PHP one interface can be inherited from another by *extends* keyword:

{% highlight php %}
<?php

interface ParentInterface {
    public function method($parameter);
}

interface ChildInterface extends ParentInterface {
    public function another_method($parameter);
}
{% endhighlight %}

One difference with classes comes with the multiple inheritance, which is available for interfaces:

{% highlight php %}
<?php

interface LoggerInterface extends WritableInterface, ReadableInterface {
    // ... methods
}
{% endhighlight %}

### Implementation
When we create an interface we declare that methods defined in it will be available in any
class that implements the interface. For example, when we need to guarantee that an object
accepts an array of data, we go and create an interface:

{% highlight php %}
<?php

interface ArrayDataTransformer {
    public function getTransformedData(array $data = []);
}

class CsvTransformer implements ArrayDataTransformer {
    public function getTransformedData(array $data = [])
    {
        // implementation
    }
}
{% endhighlight %}

And then every class that implements this interface **must** have implementations for the
interface methods. And the rest of our code base now knows that *CsvTransformer* class objects
have *getTransformedData* method. 

But a class can't implement two interfaces that share the same function names, because they 
have no bodies and it would cause ambiguity.

## Abstract Class
An abstract class is also an interface. But the key difference here is that an
abstract class provides the implementation logic. Let's rewrite CsvTransformer class
with the use of abstract class:

{% highlight php %}
<?php

abstract class ArrayDataTransformer {
    private $data;

    public function setData($data) {
        $this->data = $data;
    }

    abstract public function getTransformedData($data = []);
}
{% endhighlight %}

Now with the abstract class we have declared a new data type in our langauage, called
*ArrayDataTransformer*. This data type provides the interface of two public methods. The first
one is a setter. The second will be specialized in child classes:

{% highlight php %}
<?php

class CsvTransformer extends ArrayDataTransformer {
    public function getTransformedData($data = []) {
        // child implementation
    }
}
{% endhighlight %}

## True Constants
When using abstract classes our constants may have variable values:

{% highlight php %}
<?php

abstract class AbstractTable 
{
    const TABLE = null;   
}

class UsersTable extends AbstractTable
{
    const TABLE = 'users';
}

class OrdersTable extends AbstractTable 
{
    const TABLE = 'orders';
}
{% endhighlight %}

We can override and redeclare constants of a parent class in it's child classes.
Sometimes it may be usefull, like in previous example of an application's persistance
layer.

But if we need the real unchangable constants, we can write them into interfaces. For 
example PI constant, than can't be redeclared:

{% highlight php %}
<?php

interface MathInterface {
    const PI = 3.14159;
}

class MathOperations implements MathInterface {}

var_dump(MathOperations::PI); // 3.14159
{% endhighlight %}

## Summary
With the abstract class with define a new data type in our langauage, which has it's own 
*interface*. In child classes we may override this interface or implement new specializations.
Abstract classes may be usefull when we need to declare *a new data type with it's own logic
implementation*.

The interface provides only a public declaration of the methods. It is a guarantee for the client 
code that will iteract with the objects that implement this interface. The client code 
doesn't care about an object's class, it's private or protected methods. We may use interfaces 
when we *don't care about the concrete logic implementation* and our client code only needs a special
functionality, for example in dependency injection.
