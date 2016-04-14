---
title: Event Dispatcher
layout: post
tags: [PHP, OOP, Design Patterns]
---

The idea of event dispatcher is to allow communication between different parts of application 
without coupling them. In this way application can be easily maintainded and extended,
and it does not have to know about the classes that do it.

Event dispatcher can be used when refactoring legacy code, creating new application, or adding
new functionality to existing code with minimal changes.

## Ways of extending behaviour

## Inheritance
The most common way. Simply extend a class and original behaviour can be overwritten.

{% highlight php %}
<?php
class Car
{
    protected $modelName;

    public function drive() {
        $this->startEngine();
        $this->accelerate();
    }

    protected function startEngine() { /* ... */ }
    protected function accelerate() { /* ... */ }
}

class SportCar extends Car 
{
    /* New implementations */
    protected function startEngine() { /* ... */ }
    protected function accelerate() { /* ... */ }
}
{% endhighlight %}

Here new SportCar class overwrites appropriate methods to extend base functionality.

Of course you must be very careful overwriting behaviuor. It is important to ensure that
there was no violation of *Liskov Substitution Principle* and all your class hierarchy
behaves as **one data type**.

## Composition

Composition - is a way to create complex objects from single ones. In our example
with cars we should abstract changable functionality and place it into specialized 
classes.

In our case changable functionality consists of two methods:

{% highlight php %}
<?php
 protected function startEngine() { /* ... */ }
 protected function accelerate() { /* ... */ }
{% endhighlight %}

So we can extract an interface from them:

{% highlight php %}
<?php

interface CarDriveInterface 
{
    public function startEngine() { /* ... */ }
    public function accelerate() { /* ... */ }

}
{% endhighlight %}

Then we create a family of classes that implement this interface. Each class will be
specialized version of changable behaviour.

{% highlight php %}
<?php

class CarDriveControl implements CarDriveInterface {
    public function startEngine() { /* ... */ }
    public function accelerate() { /* ... */ }
}

class SportCarDriveControl implements CarDriveInterface {
    public function startEngine() { /* ... */ }
    public function accelerate() { /* ... */ }
}

{% endhighlight %}

Now we can inject these classes in our Car class through constructor. So we no longer need inheritance and 
SportCar class.

{% highlight php %}
<?php
$car = new Car(new CarDriveControl());
$sportCar = new Car(new SportCarDriveControl());

{% endhighlight %}

Here now we have polymorphism (one name - different logic). A single class Car can use different versions of
CarDriveInterface. By using different implementations our car can behave like a simple car, like a sport car or
any other implementation.

## Mediator design pattern

Interface limits us with its methods. But what if we want to extend behaviour beyound interface functionality? Here 
comes *Mediator Pattern*. The Mediator pattern is a behaviour pattern. It's main purpose is to allow classes to 
communicate without knowing anything about each other. To achieve this pattern defines an intermediary class as 
*dispatcher*. *Dispatcher* becomes a central hub for all communications between classes.

There are the key aspects in the pattern.

### Registration/Subscription

The *consumer* registeres with the dispatcher to *listen* to events. Then the dispatcher  *notifies* the consumer when event
raises. Both the consumer and the producer of events know about dispatcher, but don't know anything about each other.

### Event Dispatching
When the *producer* raises event he sends it to the *dispatcher*. Event can be sent with an *event object* associated with 
this event. This object may contain information about the event. The producer doesn't have to know anything about what
happens next. The dispatchers job is to notify then the *sonsumer* which is waiting for the event.

## Symfony Event Dispatcher

In Symfony the consumers are called *listeners*. Listeners are callable objects: class objects or functions. The Symfony 
Event Dispatcher component works with 2 interfaces: *EventDispatcherInterface* and *EventSubscriberInterface* and a class *Event*.

*Subscriber* 
