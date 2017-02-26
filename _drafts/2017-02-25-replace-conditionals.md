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

In OOP world *polymorphism* in a very simplistic stranslation means *same name, different logic*. This word consists of two greek words: *polys* which means "many" and *morph* which means form or shape.But what on the earth doest it mean? How can we implement such things in PHP? 

So, in PHP we have these kinds of polymorphism: 

- **subtype polymorphism**
- **parametric polymorphism** 
- **delegate polymorphism**

### Subtype Polymorphism
This type of polymorphism in OOP means the ability to change the behaviour of the method by providing a method with the same name in a child class.

### Parametric Polymorphism
We have this type of polymorphism out of the box in PHP, becouse of the language dynamically typed nature. We can pass as arguments anything we want:

{% highlight php %}
<?php

function sum($a, $b) {
    return $a + $b;
}

echo sum((int)2, (int)5); // 7
echo sum((double)2.5, (double)3.5); // 5
echo sum('a', 'bc'); // 0
{% endhighlight %}

In the code above we have `sum` function, that supports parametric polymorphism. It successfully accepts argument with different data types.
