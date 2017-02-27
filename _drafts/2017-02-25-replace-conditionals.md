---
title: "Replace Conditionals With Composition And Polymorphism"
tags: [PHP, OOP]
layout: post
description: "How to replace conditionals code smell with composition and Polymorphism"
---

Conditionals are an integral part of any programming language. We use them every day, so why in OOP they are considered as a *code smell*?
Conditional becomes a *code smell* when we have to check an object's type in order to make some logic or behavior decision. It is often used with a `switch` statement:

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
                $lines = [];
                foreach($this->data as $row) {
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

Here we have `getData` method, that returns statistics in a specified format. Our report can be returned as a *cvs* string, as an *array* or even as *HTML* string. Then lately the business can ask us for reports in *pdf* and *json* formats. What shall we do then? Of cource we can add two more `case` blocks to `switch` statement. Our `getData` will grow and grow, even if we refactor it and put formatting logic in different methods, this doesn't change the whole picture:

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

            case 'json':
                return json_encode($this->data);
        }
    }
}
{% endhighlight %}

This class violates **Open-Closed Principle**, which says:

*software entities (classes, modules, functions, etc.) should be open for extension, but closed for modification*

But in our case, our class `StatisticsReport` is closed for extension, because every time when we need to add/extend some functionality, we should go and change its source code. Now it is clear, that condition here is a *code smell*. So how to fix it? How we can make this class opened for extension, and add new functionality to it without chaning its source code?

There are several refactoring recipes for removing conditionals from your code. On is *Replace Conditional With State/Strategy* and *Replace Conditional With Polymorphism*.
The first one places conditional branches into new objects, one of which is selected and used at a runtime. This recipe uses *composition*. The second one removes conditionals be creating a class hierarchy with a base class for the default condition branch and subclasses for each specialization. And again the required object is chosen at a runtime. This recipe uses *inheritance*.

There is no *right* or *wrong* recipe. It always *depends* on your current application context. Both recipes lead to new objects in your system, that hold conditional logic from each conditional branch.

Lets try both recipes with our example of the `StatisticsReport` class.

## Replace Conditionals With Polymorphism

In OOP world *polymorphism* in a very simplistic translation means *same name, different logic*. This word consists of two greek words: *polys* which means "many" and *morph* which means form or shape. In most cases, when we replace conditional with polymorphism, we deal with a *subtype polymorphism*. This type of polymorphism in OOP means the ability to change the behavior of the method by providing a method with the same name in a child class.
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

            case 'json':
                return json_encode($this->data);
        }
    }
}
{% endhighlight %}

It looks like we have a *base* `array` behavior, and several *specializations*: `csv`, `html`, `pdf` and `json`. Now we map these concepts to classes. We leave `StatisticsReport` class with an `array` implementation and extract several classes each for every format: `CvsStatisticsReport`, `HtmlStatisticsReport`, `PdfStatisticsReport` and `JsonStatisticsReport`. And then in these *specialization* classes, we override base `array` behavior in `getData()` method:

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

class JsonStatisticsReport extends StatisticsReport 
{
    public function getData()
    {
        // return report as json string
    }
}
{% endhighlight %}

With this hierarchy, our `StatisticsReport` class now is open for extension. Every time our business needs a report in a new format, we can extend this class and create new *subtypes* for every new report format. The only thing left is how we create all these classes? Where to put this creation logic? 

You can notice that we actually didn't replace the condition, instead, we have moved it to another place. But the key thing here was to make `StatisticsReport` class opened for extension. Before refactoring it was impossible to extend this class without touching its source code. Before refactoring this class made a decision how to behave according to some condition. Now we have small classes each with its own behavior. The conditional logic now is used for creation, not for choosing the right behavior.