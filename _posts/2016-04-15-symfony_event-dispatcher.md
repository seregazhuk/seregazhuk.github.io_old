---
title: Symfony Event Dispatcher
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

### Registration/Subscription

The *consumer* registeres with the dispatcher to *listen* to events. Then the dispatcher  *notifies* the consumer when event
raises. Both the consumer and the producer of events know about dispatcher, but don't know anything about each other.

### Event Dispatching
When the *producer* raises event he sends it to the *dispatcher*. Event can be sent with an *event object* associated with 
this event. This object may contain information about the event. The producer doesn't have to know anything about what
happens next. The dispatchers job is to notify then the *sonsumer* which is waiting for the event.

## The EventDispatcher Component

In Symfony the consumers are called *listeners*. Listeners are callable objects: class objects or functions. 
<a href="http://symfony.com/doc/current/components/event_dispatcher/introduction.html" target="_blank">
The Symfony Event Dispatcher component</a> consists of 2 interfaces: *EventDispatcherInterface* and *EventSubscriberInterface* and a class *Event*.

The *EventDispatcherInterface* has methods to add, remove, get and check listeners, 2 methods to add and remove
subscribers. A *subscriber* - is a specialized listener, that implements *EventSubscriberInterface* ("auto-configured" listener).
In addition it also provides a method for dispatching events.
{% highlight php %}
<?php

interface EventDispatcherInterface
{
    /**
     * Dispatches an event to all registered listeners.
     */
    public function dispatch($eventName, Event $event = null);

    /**
     * Adds an event listener that listens on the specified events.
     */
    public function addListener($eventName, $listener, $priority = 0);

    /**
     * Adds an event subscriber.
     */
    public function addSubscriber(EventSubscriberInterface $subscriber);

    /**
     * Removes an event listener from the specified events.
     */
    public function removeListener($eventName, $listener);

    /**
     * Removes an event subscriber.
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber);

    /**
     * Gets the listeners of a specific event or all listeners sorted by descending priority.
     */
    public function getListeners($eventName = null);

    /**
     * Gets the listener priority for a specific event.
     */
    public function getListenerPriority($eventName, $listener);

    /**
     * Checks whether an event has any registered listeners.
     */
    public function hasListeners($eventName = null);
}

{% endhighlight %}

Let's create listener - a simple function that will send an email to a registered user.

{% highlight php %}
<?php

// Listener definition, these can be functions or classes.
// They represent the consumers.
$registrationListener = function(GenericEvent $event){
    $user = $event['user'];
    // send email
};
{%endhighlight %}

Then we need to add our listener to the event dispatcher object. It is done by 
*EventDispatcher::addListener()* method. It has 3 parameters: an event to listen for,
a callable listener and a priority value. Priority indicates in which order to 
call listeners, the higher the number, the higher the priority.

{% highlight php %}
<?php 

use Symfony\Component\EventDispatcher\EventDispatcher;

// Setup dispatcher, this code is setup by the client
$dispatcher = new EventDispatcher();
$dispatcher->addListener('user.registered', $registrationListener);
{% endhighlight %}

Every time the producer needs to dispatch an event it calls the dispatcher object with the 
name of the event:

{% highlight php %}
<?php

use Symfony\Component\EventDispatcher\GenericEvent;
use App\User;

// producer code
$user = User::create('John', 'john@mail.com');

$event = new GenericEvent();
$event['user'] = $user;
$dispatcher->dispatch('user.registered', $event);

{% endhighlight %}

## Listeners and Subscribers
So what is the difference between them? 

### Listeners
Let's create a listener class and then add it to our dispatcher object.

{% highlight php %}
<?php

use Symfony\Component\EventDispatcher\GenericEvent;

class MailSender {
    public function sendRegistrationEmail($user)
    {
        // ...
        echo 'Mail has been sent to ' . $user->email;
    }
}

$mailSender = new MailSender();
$dispatcher = new EventDispatcher();
$dispatcher->addListener(
   'user.registered',
   [$mailSender, 'sendRegistrationEmail']
);

{% endhighlight %}

Here, for listener the client code is responsible for telling the dispatcher what 
event each listener is registered to.

### Subscribers

The difference with listeners comes in that subscribers *can tell* the dispatcher 
exactly which events they are listening for. That's why all subscribers must
implement the EventSubscriberInterface. This interface defines a static method 
*getSubscribedEvents* that returns an array with methods names, that shoucalled by 
the dispatcher for each event they subscribe. Let's change mailSender class from a
listener to subscriber.

{% highlight php %}
<?php
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSender implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'user.registered' => ['sendRegistrationEmail']
        ];
    }
}

// add a subscriber to the dispatcher
$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new MailSender());

{% endhighlight %}

Adding a subscriber to the dispatcher is simplier than adding listeners, becouse
we don't need to tell the dispatcher events names and priorities.
