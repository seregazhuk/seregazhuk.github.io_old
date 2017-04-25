---
title: "Dependency Injection: Constructor vs Setter"
tags: [PHP, OOP]
layout: post
---

In an object-oriented application, objects constantly interact with each other, either by calling methods and receiving information from another object, or changing the state of this object. In any case, objects often *depend* on each other. 

Consider this simple. `Delivery` class that sends orders to a delivery system API. It has an HTTP client that is used to make HTTP requests to API endpoints.

{% highlight php %}
<?php

class Delivery
{
    /** 
     * @var HttpClient
     */
    protected $client;

    public function __construct()
    {
        $this->client = new HttpClient();
    }

    public function send(Order $order)
    {
        $response = $this->client->post('/orders/create', $order->toJson());

        return json_decode($response, true);
    }

    public function getStatus($orderId)
    {
        $response = $this->client->get('/orders/info', $orderId);

        return json_decode($response, true);   
    }
}
{% endhighlight %}

Here we have a hardcoded dependency of the `HttpClient` class. Why is it bad? Imagine, that one of your colleges has changed `HttpClient` class constructor and added a required `$baseUrl` parameter to it. And suddenly your `Delivery` class is broken. Of course, modifying a class constructor in a production code is already a strong sign of poor application design. But, bad things happen. 

Every time we use one class name inside another class, we couple them together. It is OK to depend on an abstract class, but you should not depend on any concrete implementation (remember Dependency Inversion Principle). The most danger thing is to instantiate an object, where it should not be created. You can argue, that how we can determine where is the right place for a particular object to be created? Let's go from the opposite, and try to find wrong places to create objects. As we have already seen in the previous example, is was not a great idea to instantiate `HttpClient` object right in the another class constructor. When suddenly its constructor signature has changed we have to search through the entire code base and fix every instantiation of the `HttpClient` class. With modern IDE's it may be not so overwhelming. But we code in PHP, a dynamically typed language. Your IDE is useless with statements like this:

{% highlight php %}
<?php

function makeInstance($class) {
    return new $class;
}

$http = makeInstance('HttpClient');
{% endhighlight %}

We can use a variable, that stores a class name to create an object of this class. You will find this only when something will be broken. So, to fix this issue we should remove instantiation of the HTTP client outside and pass it as an argument to the constructor, assign it to a property and later any method can use it. Here is a rule of thumb: *if an object cannot be used without a dependency it should be passed as a constructor argument*. And here is the correct version of `Delivery` class with dependency injection applied:

{% highlight php %}
<?php

class Delivery
{
    protected $client;

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }
}
{% endhighlight %}

But why should we use a constructor to inject dependencies? Maybe it is better to use `setClient` method, and configure object after creation. Looks flexible, that we can keep the constructor tidier and reconfigure our object later with a new dependency using a single method call. So, let's remove the constructor and use `setClient` setter to supply the dependency and see what happens. 

{% highlight php %}
<?php

class Delivery
{
    /** 
     * @var HttpClient
     */
    protected $client;

    public function setClient(HttpClient $client)
    {
        $this->client = $client;
    }

    public function send(Order $order)
    {
        $response = $this->client->post('/orders/create', $order->toJson());

        return json_decode($response, true);
    }

    public function getStatus($orderId)
    {
        $response = $this->client->get('/orders/info', $orderId);

        return json_decode($response, true);   
    }
}
{% endhighlight %}

When we remove the constructor and leave only a setter for the dependency we immediately end up with an anti-pattern. We have a successfully created object, but it still should be configured, before we can start using it. Consider this example:

{% highlight php %}
<?php 

class DeliveryController 
{
    protected $delivery;

    public function __constructor(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function sendOrder($orderId)
    {
        $order = Order::find($orderId);

        $delivery->sendOrder($order); 
    }
}

$delivery = new Delivery();
$controller = new DeliveryControler($delivery);

$controller->sendOrder(111); // This will error, because an object is not configured and cannot be used
{% endhighlight %}


`DeliveryControler` has been given a successfully created instance of the `Delivery`. But `DeliveryControler` cannot make the assumption that `Delivery` object is fully configured because there is no way to know whether `setClient` method has been called before or not. It is an ambiguity here because we have broken encapsulation in the `Delivery` class. The client code shouldn't care about the internal dependencies of the objects it uses. We should avoid such incomplete objects in the application, they cause bugs which are very difficult to find out and test. In the tests, a mock for `Delivery` class will work perfectly, but in production, `DeliveryControler` will be broken. We can avoid such incomplete objects and problems related to them by using constructor injection instead. That is why *if an object cannot be used without a dependency it should be always passed as a constructor argument*

In all other scenarios, we can safely use setter injections. For example, in `QueryBuilder` we can use logger to log some of the database queries. We can create a setter for a logger like this:

{% highlight php %}
<?php

class QueryBuilder 
{
    protected $logger;

    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function execute($sql, $params, Logger $logger = null)
    {
        // ...
        if($this->logger) {
            $this->logger->info(
                    'DB: ' . $sql . ';' . implode(', '. $params)
                );
        }
    }
}
{% endhighlight %}


In this example, the dependency is passed as an argument to the setter, that requires it. Now there will be no unexpected side effects because the dependency is not encapsulated in `QueryBuilder` class (the class internal state doesn't depend on the dependency). When we provide a setter for a dependency, the client code can reconfigure an object with a new dependency. It doesn't make sense if the dependency only extends the object's functionality like for example, a logger does. But when it changes the internal object state, we can easily break encapsulation. The rule of thumb for setter injection is: *use setter injections for dependencies that are not required for an object. These dependencies should not replace the internal object's functionality, instead, they should extend it.*