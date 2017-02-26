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

But in our case, our class `StatisticsReport` is closed for extension, because every time when we need to add/extend some functionality, we should go and change its source code.
