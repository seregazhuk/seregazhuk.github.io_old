---
title: "Does Static Factory Violate Open/Closed Principle?"
tags: [PHP, OOP, SOLID, Design Patterns]
layout: post
image: "/assets/images/posts/factory-open-closed/logo.png"
description: "Does the Static Factory Design Pattern violate the Open/Closed Principle"
---

Consider an application that provides some statistics reports. Reports are present in different formats: JSON for API, HTML for viewing in a browser and pdf for printing on the paper. It has `StatisticsController` that receives a required format from the request and returns a formatted report. 

{% highlight php %}
<?php

class StatisticsController 
{
    public function report(Request $request)
    {
        $format = $request->get('type');
        $report = new StatisticsReport();
        $formatter = FormatStrategiesFactory::makeFor($format);

        return $report->formatWith($formatter)->getData();
    }
}
{% endhighlight %}

The logic for choosing a formatting strategy is hidden behind the factory. `FormatStrategiesFactory` implements Static Factory design pattern and knows what formatter should be instantiated according to a specified format:

{% highlight php %}
<?php

class FormatStrategiesFactory 
{
    public static function makeFor($format) 
    {
        switch($format) {
            case 'html':
                return new HtmlFormatStrategy();
            case 'pdf':
                return new PdfFormatStrategy();     
            case 'json':
                return new JsonFormatStrategy();        
        }

        throw new \Exception("Unknown report format $format");
    }
}
{% endhighlight %}

Having this code we receive a request for a new *feature* from our clients that they also want to have reports in *csv* format. Looks like we need one more formatting strategy:

{% highlight php %}
<?php

class CsvFormatStrategy implements FormatStrategy 
{
    public function formatData(array $data) 
    {
        $lines = [];

        foreach($this->data as $row) {
            $lines = implode(",", $row);
        }

        return implode("\n", $lines);
    }
}
{% endhighlight %}

And the last step is to update the factory and add one more `case` statement for a new just created strategy:

{% highlight php %}
<?php

class FormatStrategiesFactory 
{
    /**
     * @param string $format
     * @return FormatStrategy
     */
    public static function makeFor($format) 
    {
        switch($format) {
            case 'html':
                return new HtmlFormatStrategy();
            case 'pdf':
                return new PdfFormatStrategy();     
            case 'json':
                return new JsonFormatStrategy();   
            case 'csv':
                return new CsvFormatStrategy();    
        }

        throw new \Exception("Unknown report format $format");
    }
}
{% endhighlight %}

And we are done, no changes in controller code. But you can remember [Open-Closed Principle]({% post_url 2017-03-27-open-closed-principle %}){:target="_blank"}:

>*software entities (classes, modules, functions, etc.) should be open for extension, but closed for modification*

and ask a reasonable question: 
>*Does this factory violate this principle?* 

Because every time we need to extend our reporting system with a new format we need to modify the factory. And the answer is *Yes, but...* 

<p class="text-center image">
    <img itemprop="image" src="/assets/images/posts/factory-open-closed/logo.png" alt="logo" class="">
</p>

Yes, technically it is a violation, but not the worst one because it is limited to this particular place. In OOP we always try to avoid `if` and `switch` statements and replace them with dynamic calling of overridden methods. Also, the main point of the factory pattern is to hide individual classes from you, so there is no need to know about them and you can deal only with an abstract class (or interface). The idea is that the factory knows better than you which specific class needs to be instantiated.

We even can create a factory without any conditional statements. If the use-case is pretty simple and all classes that are going to be instantiated by the factory come from one namespace we can dynamically resolve a class name, according to some naming convention. For example, if we assume that our formatting strategies have the same naming pattern: `{format}FormatStrategy`, then the factory can be rewritten like this:

{% highlight php %}
<?php

class FormatStrategiesFactory 
{
    /**
     * @param string $format
     * @return FormatStrategy
     */
    public static function makeFor($format) 
    {
        $formatterClass = __NAMESPACE__ . ucfirst($format) . 'FormatStrategy';
        if (!class_exists($formatterClass)) {
          throw new \Exception("Unknown report format $format");
        }
        
        return new $formatterClass();
    }
}
{% endhighlight %}

Maybe such on-the-fly class name resolving doesn't look so explicit as implementation with conditionals, but you can very easily add new formatters in this case. Just create a new class and you are ready to go, the factory already knows how to resolve this class name and create an instance of it. The only thing you have to do - is following the naming convention.

Implementations of the factory can differ: it can be a map of configuration, a registry where classes can register themselves, a set of conditional statements or the factory can resolve class-names on the fly according to some naming pattern. And there is nothing wrong with using `switch` statement if the number of classes is small and changes infrequently. In this way adding a new format type to the list is relatively simple and robust. 

According to Open-Closed Principle the *"correct"* solution would be to create a new factory with the same interface. That said, adherence to this principle should always be weighed against other design principles like KISS and YAGNI. 

## Maybe It Doesn't Matter

Extending the conditional to add support for a new subclass is indeed strictly speaking a violation of the Open-Closed Principle. In practice it is extremely difficult to write pure Open-Closed code and often it is not worth it. There is nothing criminal in modifying only one method which is clearly an initialization list. In this case the factory enables the rest of the system to be Open-Closed, but the factory itself violates the principle.

But what is a static class? Actually, it has nothing to do with OOP. We are not going to create an instance of the static factory. We have a set of statements (constructors) that are being executed one by one. It is a procedural code, not an object-oriented one. Static factory represents a procedure written with class syntax. So, maybe we shouldn't treat static factory as an OOP code and try to apply SOLID principles to it. Just think of it as a procedure: we give it some data, it does something, we get the result and then we continue.
