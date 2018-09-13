---
title: "Replace Conditionals With Composition And Polymorphism"
tags: [PHP, OOP]
layout: post
description: "How to replace conditionals code smell with composition and Polymorphism"
---

Conditionals are an integral part of any programming language. We use them every day, so why in OOP they are considered as a *code smell*?
Conditioт becomes a *code smell* when we have to check an object's type in order to make some logic or behavior decision. It doesn't matter whether it is a stack of `if/else` block or a `switch` statement. Consider this `StatisticsReport` class, which is used to generate reports in different formats:

{% highlight php %}
<?php

class StatisticsReport
{

    protected $data;

    protected function initData() 
    {
        // ...
    }

    public function getData($format = 'csv')
    {
        switch($format) {
            case 'csv':
                $lines = [];
                foreach ($this->data as $row) {
                    $lines = implode(",", $row);
                }

                return implode("\n", $lines);

            case 'array': 
                return $this->data;

            case 'html':
                $html = '';
                // format as HTML ...
                return $html;
        }
    }
}
{% endhighlight %}

Here we have `getData` method, that returns statistics in a specified format. Our report can be returned as a *cvs* string, as an *array* or even as *HTML* string. Then lately the business can tell us that they need reports in *pdf* to send them by email. What shall we do then? Of cource we can add two more `case` blocks to `switch` statement. Our `getData` will grow and grow, even if we refactor it and put formatting logic in different methods, this doesn't change the whole picture:

{% highlight php %}
<?php

class StatisticsReport
{

    protected $data;

    protected function initData() {
        // ...
    }

    public function getData($format = 'csv')
    {
        switch($format) {
            case 'csv':
               return $this->getDataAsCsv();

            case 'array': 
                return $this->data;

            case 'html':
                return $this->getDataAsHtml();

            case 'pdf':
                return $this->getDataAsPdf();
        }
    }
}
{% endhighlight %}

This class violates **Open-Closed Principle**, which says:

*software entities (classes, modules, functions, etc.) should be open for extension, but closed for modification*

But in our case, our class `StatisticsReport` is closed for extension, because every time when we need to add/extend some functionality, we should go and change its source code. Now it is clear, that condition here is a *code smell*. So how to fix it? How can we make this class opened for extension, and add new functionality to it without changing its source code?

There are several refactoring recipes for removing conditionals from your code. On is *Replace Conditional With State/Strategy* and *Replace Conditional With Polymorphism*.
The first one places conditional branches into new objects, one of which is selected and used at a runtime. This recipe uses *composition*. The second one removes conditionals be creating a class hierarchy with a base class for the default condition branch and subclasses for each specialization. And again the required object is chosen at a runtime. This recipe uses *inheritance*.

There is no *right* or *wrong* recipe. It always *depends* on your current application context. Both recipes lead to new objects in your system, that hold conditional logic from each conditional branch.

Lets try both recipes with our example of the `StatisticsReport` class.

## Replace Conditionals With Polymorphism

In OOP world *polymorphism* is a very simplistic translation means *same name, different logic*. This word consists of two greek words: *polys* which means "many" and *morph* which means form or shape. In most cases, when we replace conditional with polymorphism, we deal with a *subtype polymorphism*. This type of polymorphism in OOP means the ability to change the behavior of the method by providing a method with the same name in a child class.
Let's consider the most known example from OOP tutorials:

{% highlight php %}
<?php

abstract class Shape 
{
    abstract public function getArea();
}

class Square extends Shape
{
    protected $length;

    public function __construct($length) {
        $this->length = $length;
    }

    public function getArea() {
        return pow($this->length, 2);
    }
}

class Triangle extends Shape
{
    protected $height;
    protected $base;

    public function __construct($height, $base) {
        $this->base = $base;
        $this->height = $height;
    }

    public function getArea() {
        return $this->height * $this->base / 2;
    }
}

$square = new Square(4);
echo $square->getArea(); // 4

$triange = new Triange(2,4);
echo $triangle->getArea(); // 4
{% endhighlight %}

The code above very clearly demonstrates the subtype polymorphism. We have a base type `Shape` and two *subtypes* (specifications): `Square` and `Triangle`. Method `getArea()` here represents the definition of *polymorphism*: "same name, different logic".

Now let's move back to our `StatisticsReport` class again:

{% highlight php %}
<?php

class StatisticsReport
{

    protected $data;

    protected function initData() {
        // ...
    }

    public function getData($format = 'csv')
    {
        switch($format) {
            case 'csv':
               return $this->getDataAsCsv();

            case 'array': 
                return $this->data;

            case 'html':
                return $this->getDataAsHtml();

            case 'pdf':
                return $this->getDataAsPdf();
        }
    }
}

$report = new StatisticsReport();
$dataInCsv = $report->getData('csv');
{% endhighlight %}

It looks like we have a *base* `array` behavior, and several *specializations*: `csv`, `html`, `pdf` and `json`. Now we map these concepts to classes. We leave `StatisticsReport` class with an `array` implementation and extract several classes each for every format: `CvsStatisticsReport`, `HtmlStatisticsReport` and `PdfStatisticsReport`. And then in these *specialization* classes, we override base `array` behavior in `getData()` method:

{% highlight php %}
<?php

class StatisticsReport
{
    protected $data;

    protected function initData() {
        // ...
    }

    public function getData()
    {
        return $this->data;
    }
}

class CvsStatisticsReport extends StatisticsReport 
{
    public function getData()
    {
        // return report as csv string
    }
}

class HtmlStatisticsReport extends StatisticsReport 
{
    public function getData()
    {
        // return report as HTML string
    }
}

class PdfStatisticsReport extends StatisticsReport 
{
    public function getData()
    {
        // return report as pdf
    }
}

{% endhighlight %}

With this hierarchy, our `StatisticsReport` class now is open for extension. Every time our business needs a report in a new format, we can extend this class and create new *subtypes* for every new report format. The only thing left is how we create all these classes? Where to put this creation logic? 

You can notice that we actually didn't replace the condition, instead, we have moved it to another place. But the key thing here was to make `StatisticsReport` class opened for extension. Before refactoring it was impossible to extend this class without touching its source code. Before refactoring this class made a decision how to behave according to some condition. Now we have small classes each with its own behavior. The conditional logic now is used for creation, not for choosing the right behavior.

So, now we are faced with another problem. Where to put this creation logic? How we can get the required object? For this purpose, we can use *Factory* design patterns. The main responsibility for factories is to create objects. In our case, we need different objects according to different formats:

{% highlight php %}
<?php

class StatisticsReportFactory
{
    public static function makeFor($format) {
        switch($format) {
            case 'csv':
               return new CvsStatisticsReport();
            case 'array': 
                return new StatisticsReport();
            case 'html':
                return new HtmlStatisticsReport();
            case 'pdf':
                return new PdfStatisticsReport();           
        }
    }
    
}

{% endhighlight %}

Next time, when our manager comes to us and asks to build API for reports, so these reports now should be available in *json* format, it can be done easily. And without touching existing classes, of course except for the factory. We simply go and create a new `JsonStatisticsReport` and add a new `case` statement for it:

{% highlight php %}
<?php 

class JsonStatisticsReport extends StatisticsReport 
{
    public function getData()
    {
        // return report as json string
    }
}

class StatisticsReportFactory
{
    public static function makeFor($format) {
        switch ($format) {
            case 'csv':
               return new CvsStatisticsReport();
            case 'array': 
                return new StatisticsReport();
            case 'html':
                return new HtmlStatisticsReport();
            case 'pdf':
                return new PdfStatisticsReport();     
            case 'json':
                return new JsonStatisticsReport();        
        }
    }
}
{% endhighlight %}

And then somewhere in api controller in our application:

{% highlight php %}
<?php


public function getReport(Request $request)
{
    $jsonReport = StatisticsReportFactory::makeFor('json');
    // set report parameters from request
    return $jsonReport->getData();
}

{% endhighlight %}

## Replace Conditionals With Composition

First of all, let's remember, what does the *composition* mean in object-oriented programming. Composition combines different simple, transparent and independent objects into one complex whole thing. 

With composition, we have two options here. We can replace conditional with *Strategy* or with *State*. These two patterns are closely related. In both patterns, we inject some encapsulated behavior in the original object. In *State* in choose behavior according to an object's internal *state* (one or many property values). And in *Strategy*, we make a decision what kind of behavior we need according to how we want things to be processed. 

`StatisticsReport` class doesn't have any internal state based on its property values. Instead, we want different strategies to process data according to some format value. So, in our case, we can use *Strategy* pattern to remove conditionals. We will encapsulate each algorithm for every report format in its own object and then unify all of them with one common interface.

## Strategy

In [Wikipedia](https://en.wikipedia.org/wiki/Strategy_pattern):

>the strategy pattern (also known as the policy pattern) is a behavioral software design pattern that enables an algorithm's behavior to be selected at runtime. 

The strategy pattern:

- defines a family of algorithms,
- encapsulates each algorithm, and
- makes the algorithms interchangeable within that family.

In our case, that means:
- common interface for different formats logic
- classes for every format
- a factory and a setter for injecting a behavior object

The common interface will be very simple and consist of the only one method:

{% highlight php %}
<?php

interface FormatStrategy 
{
    public function formatData(array $data);
} 

class JsonFormatStrategy implements FormatStrategy 
{
    public function formatData(array $data) 
    {
        return json_encode($data);
    }
}

class CsvFormatStrategy implements FormatStrategy 
{
    public function formatData(array $data) 
    {
        $lines = [];

        foreach ($this->data as $row) {
            $lines = implode(",", $row);
        }

        return implode("\n", $lines);
    }
}

class PdfFormatStrategy implements FormatStrategy 
{
    public function formatData(array $data) 
    {
        // build and return pdf document
    }
}

class HtmlFormatStrategy implements FormatStrategy 
{
    public function formatData(array $data) 
    {
        // make and return html
    }
}

{% endhighlight %} 

**Notice:** I think that it is not a good idea to have design pattern words in your classes and interfaces. But when we have these out of other code examples, I think it is better to have very explicit class names. Also some classes, for example, `StatisticsPdfReportStrategy` and `StatisticsHtmlReportStrategy` may have their own dependencies for creating pdf and html documents, but they were omitted in this example, because I want to pay your attention to the classes design, but not to the different formats algorithms.

The last step is to provide our `StatisticsReport` with a required behavior. Here comes a factory, the only place with conditionals in our design. It will look very similar to `StatisticsReportFactory` from the *Inheritance* chapter. The only difference is that now our factory returns a *behavior* (strategy) for every format:

{% highlight php %}
<?php 

class FormatStrategiesFactory 
{
    public static function makeFor($format) 
    {
        switch ($format) {
            case 'csv':
               return new CsvFormatStrategy();
            case 'html':
                return new HtmlFormatStrategy();
            case 'pdf':
                return new PdfFormatStrategy();     
            case 'json':
                return new JsonFormatStrategy();        
        }
    }
}

{% endhighlight %}

Then we need a setter for a strategy to be injected in our `StatisticsReport` class. And also we need to update its `getData` method. Now this method will use the provided strategy for formatting:

{% highlight php %}
<?php 

class StatisticsReport 
{
    /**
     * @var FormatStrategy
     */
    protected $formatter;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var FormatStrategy $formatter
     * @return $this
     */
    public function formatWith(FormatStrategy $formatter) 
    {
        $this->formatter = $formatter;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData() 
    {
        if (isset($this->formatter)) {
            return $this->formatter->formatData($this->data);
        }

        return $this->data;
    }
}

$strategy = FormatStrategiesFactory::makeFor('json');
$report = new StatisticsReport();

$formattedData = $report->formatWith($strategy)->getData();
{% endhighlight %}

The key difference with the inheritance approach is that our strategies are reusable within our application, and are not coupled to statistics and reports logic. Strategies know nothing about the objects that use them.
