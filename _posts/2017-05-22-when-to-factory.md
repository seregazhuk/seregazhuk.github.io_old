---
title: "When to use Factory instead of direct object construction"
tags: [PHP, OOP, Design Patterns]
layout: post
---

> *Factory is an object responsible for creating other objects.*

It is often considered a *good practice* to move the process of object creation from the consumer's code into the factory. Even more, some people say that you should avoid the use of `new` keyword in your code as much as possible. As for me, I think that you should be careful when someone says that *you should always ...*.  Instead, it is better to understand why in some cases factories are useful, but sometimes they are a needless overengineering, so you can make a right decision according to text context.

First of all, don't be afraid to create an object with `new` keyword if you need it. Keep [KISS principle]({% post_url 2017-04-13-kiss %}) in mind. The world won't blow up if you couple one of your classes to another one. By default, you should **not** use factories. Consider this example of some `Api` class.

{% highlight php %}
<?php

class Api 
{    
    public function __construct(HttpClient $http)
    {
        $this->client = $http;
    }

    // ...
}

$api = new Api(new HttpClient());
{% endhighlight %}


Someone can tell that it is a *terrible* code. We are coupled to `HttpClient`'s constructor. When it changes, we need to change this code. Ok, let's use a factory here and see what changes.

{% highlight php %}
<?php

class Api 
{    
    public function __construct(HttpClient $http)
    {
        $this->client = $http;
    }

    // ...
}

class HttpClientsFactory
{
    public static function create()
    {
        return new HttpClient();
    }
}

$api = new Api(HttpClientsFactory::create());
{% endhighlight %}

We have added a new class in our system and increased its complexity only to replace one line of code. Our factory doesn't have any benefits. It *only* creates a new object. It doesn't make any decision on how an object should be created or what kind of object it should be. Only a plain `new` keyword and that is all. The whole class for only one line of code. Furthermore, if `Api` is the only place in the system where `HttpClient` is being used, we still need to change the code if `HttpClient` constructor changes. The only difference with the factory is that this line is now placed in the factory.

Let's add a bit more complexity. Here is method `execute` to perform various requests:

{% highlight php %}
<?php

class Api 
{    
    public function execute($method, $uri, array $options = [])
    {
        $request = new Request($method, $uri);

        $response = $this->client->send($request, $options);

        return $this->parseResponse($response);
    }    
}
{% endhighlight %}

And again we are creating a `Request` object. Should we use a factory here? Or it will add nothing but complexity to the system?

{% highlight php %}
<?php

class Api 
{    
    public function execute($method, $uri, array $options = [])
    {
        $request = RequestFactory::createFor($method, $uri);

        $response = $this->client->send($request, $options);

        return $this->parseResponse($response);
    }    
}

class RequestFactory
{
    public static function createFor($method, $uri)
    {
        return new Request($method, $uri);
    }
}
{% endhighlight %}

Obviously, there is no difference with the previous example. Again there is a whole class to replace one simple line of code. There is no need in factories when we don't have any complex creation logic. We won't get any benefits replacing a line with `new` keyword with a static factory call. In our example, the client code (`Api` class) *knows* how to create and how to use `Request` object. The client code knows exactly what it wants. It wants an object of the `Request` class. And the process of `Request` creation is very simple, so the client code can handle it itself. The factory, in this case, will be a needless overengineering and needless abstraction. 

### Another example

This example shows a controller that is used to display reports in different formats: *json*, *pdf* and *HTML*. According to the passed type we create different report objects. 

{% highlight php %}
<?php

class StatisticsController 
{
    public function report(Request $request)
    {
        $type = $request->get('type');

        switch($type) {
            case 'pdf': 
                $report = new PdfReport();
                break;
            case 'json':
                $report = new JsonReport();
                break;
            case 'html':
                $report = new HtmlReport();
                break;
            default:
                throw new Exception(
                    "No report for type $type";
                );
        }

        return $report->getData();
    }
}
{% endhighlight %}


Even if the creation logic of every report in separate is very simple, we should consider this `switch` statement as a creation logic of the report object:

{% highlight php %}
<?php
switch($type) {
    case 'pdf': 
        $report = new PdfReport();
        break;
    case 'json':
        $report = new JsonReport();
        break;
    case 'html':
        $report = new HtmlReport();
        break;
    default:
        throw new Exception(
            "No report for type $type";
        );
}
{% endhighlight %}

And this logic is enough complex. We are making a decision what instance should be created according to some parameter. The controller class doesn't really care what concrete class it uses, it only needs a report object. It is not the controller's responsibility to decide what type of report will be better to use for the certain report type. In this case adding a factory comes with some benefits. We can abstract away this complex creation logic and use a simple factory method call:

{% highlight php %}
<?php
class StatisticsController 
{
    public function report(Request $request)
    {
        $type = $request->get('type');

        $report = ReportFactory::create($type);

        return $report->getData();
    }
}

class ReportFactory
{
    public static function create($type)
    {
         switch($type) {
            case 'pdf': 
                return new PdfReport();
            case 'json':
                return new JsonReport();
            case 'html':
                return new HtmlReport();
            default:
                throw new Exception(
                    "No report for type $type";
                );
        }   
    }
}
{% endhighlight %}

This code is much better. The creation logic for reports is concentrated in one place. If this logic will be used in many places, it can be easily **reused**. There is no need to copy and paste all these conditions, especially when we add a new class, there is no risk that we can miss it somewhere.

One more advantage of using factories is that you can use **explicit methods names**. 

```php
class RandomMoneyGenerator 
{
    public static function between($min, $max)
    {
        $amount = rand($min, $max);
        return new Money($amount);
    }

    public static function smallerThan($max)
    {
        $amount = rand(0, $max);
        return new Money($amount);
    }
}
```

The problem with constructors is that they have no names. I think that you will not argue, that `RandomMoneyGenerator::smallerThan(10)` looks much more explicit than `new Money(rand(0, 10))`.

Factories also decrease the complexity of your tests. You can test your factory that it returns correct objects, and then the client code should be tested that it calls required methods on the returned object. That means that we should test that `ReportFactory` returns a correct instance of the report for every type and then test `StatisticsController::report` method only once with any type that we like. 

### Summary

The factory is not *only for creating objects*. It is more complex than that. The factory pattern decides on certain criteria what object should be created, so it is easy to maintain this logic in one place, instead of searching it through the whole system. This creation logic also becomes **extensible** with the factory. It is easy to add a new class to the factory, without touching the client code, that uses this factory.

Factory will be useful when:

- You need an external object, but you don't know exactly which one.
- Construction is very complex and you need to reuse it.

And on the contrary, if the factory doesn't make any decision, the creation logic is very simple or is used only in a single place, the factory will be a needless abstraction. Also, just because you have moved the creation logic into another class, doesn't mean that the client code becomes decoupled. It is still coupled to the factory class. Don't use this *`new` keyword is bad* mindset, it will not improve your code.

## Want to know more?

<div class="row">
    <div class="col-sm-9">
    You can check my book <a href="https://leanpub.com/phpoopway">PHP OOP Way</a>. Note, that it is not a beginners book and it is concerned with <strong>advanced topics</strong> of the object-oriented programming in PHP such as abstraction, dependency injection, composition and 
    object-oriented design.
    </div>
    <div class="col-sm-3">
        <a href="https://leanpub.com/phpoopway">
            <img src="/assets/images/books/phpoopway.jpeg" class="book-promote pull-right" alt="PHP OOP Way">
        </a>    
    </div>
</div>

