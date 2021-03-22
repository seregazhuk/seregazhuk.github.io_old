---

title: "Traits in PHP: Good or Bad"
layout: post
description: ""
tags: [PHP, OOP]
image: ""

--

Since PHP 5.4.0 (2012) we have such a tool as traits. Many years have passed since then, but people
still argue about traits: should we use them in our code or not. Some people say that they are a 
great tool for removing code duplication. On the opposite we have opinions that traits break encapsulation, 
and that they make code even less readable. So, let's try to understand why many people are strongly 
against traits. We will try to find if there are any good use-cases for traits, or we should avoid them at all costs.

## Code reuse

Let's refresh in memory what traits actually are. Traits can be used to group a closely related 
set of functionality into a single unit, which then can be *"copy and pasted"* into
a regular class at compile time **before** the class definition is parsed. This is the way traits provide
a very powerful tool for supporting code reuse.

Let's proceed with a simple example. Most of our entities are going to have a creation date. In most
cases it is `created_at` field in the database. We want it be set automatically each time a new 
entity is created. So, looks like a good fit for a trait:

{% highlight php %}
namespace App\Profile\Entity;

final class User
{
    use HasCreated;
}
{% endhighlight %}

{% highlight php %}
namespace App\Catalog\Entity;

final class Book
{
    use HasCreated;
}
{% endhighlight %}

{% highlight php %}
namespace Infrastructure\Entity\Traits;

use Carbon\Carbon;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait HasCreatedAt
{
    /**
     * @ORM\Column(name="created_at", type="utc_datetime", nullable=false)
     * @Gedmo\Timestampable(on="create")
     */
    private $createdAt;

    public function createdAt(): Carbon
    {
        return Carbon::parse($this->createdAt);
    }
}
{% endhighlight %}

What we have done here? Classes `User` and `Book` now have some common functionality without having
a base parent class. Now, we can easily add the trait’s functionality to any class, simply by adding the 
`use HasCreatedAt;` statement. Also, because the reusable code is held within a single location, it is much
easier to maintain. If we need to provide some additional functionality (some date-formatting helpers, perhaps?), 
this is relatively easy to do. Win, right?

## Type guarantee

OK, let's move on to a more complex example. I think that it is a classic example from traits tutorials - a `Loggable` trait:

{% highlight php %}
namespace Infrastructure\Logging;

trait WithLogging
{
    public function log(string $message, string $level): void
    {
        ... // write to log
    }
}
{% endhighlight %}

The idea is to share logging logic between different classes (entities, services, whatever). Use this
trait inside the class, and it is ready to log anything:

{% highlight php %}
final class AnythingThatNeedsLogging
{
    use WithLogging;
}
{% endhighlight %}

What possible issues one can find here? The main problem with traits is that we have modified the 
interface of consuming class without providing any indication of it. That doesn't look like robust 
and resistance to bugs code. What do we usually use to explicitly define the behaviour? With traits, 
we can use interface:

{% highlight php %}
namespace Infrastructure\Logging;

interface Loggable
{
    public function log(string $message, string $level): void
}
{% endhighlight %}

From now the class that is consuming `WithLogging` trait also guarantees that it has `Loggable` 
functionality. This is the first *golden rule* for using traits:  

>*Every trait should also have a corresponding interface definition that covers the methods defined within that trait. 
> Thus, any object that consumes a trait also should implement the corresponding interface.*

## Leaky traits

It is obvious that traits have a **direct influence on the structure** of any class that consumes it. 
Having that we can easily create what I mean by a *leaky trait*. On the one hand the trait can be 
completely self-contained. It's boundaries are well-defined and such traits are safe to use. Everything
that trait need is encapsulated within the scope of its own curly braces.

However, it's very rare to find such traits. Often we have to deal with traits that rely on something
to be declared/implemented in the class that consumes them. Let's continue with `WithLogging` trait:

{% highlight php %}
trait WithLogging 
{
    private Logger $logger;
   
    public function log(string $msg, string $level): void
    {
        $filename = $this->getLogFileName();
        $this->logger->setFileName($filename)->log($msg, $level);
   }
   
   abstract public function getLogFileName(): string;
}
{% endhighlight %}

The trait now depends on the abstract method `getLogFileName()`, and any class that consumes this
trait must provide an implementation for it. This is the first type of leak - having an abstract method
inside the trait. And truth be told it is not a bad one. The trait here explicitly express its
external dependency which is need for the trait to work properly. Since everything is explicit we can
accordingly update `Loggable` interface. 
But, actually, should this interface contain `getLogFileName()`? I mean, do any client code of 
this interface need to get this filename? In this specific case probably not. We can fix it by making
`getLogFileName()` private or protected and removing it from the interface:

{% highlight php %}
trait WithLogging
{
    private Logger $logger;

    public function log(string $msg, string $level): void
    {
        $filename = $this->getLogFileName();
        $this->logger->setFileName($filename)->log($msg, $level);
    }

    abstract private function getLogFileName(): string;
}
{% endhighlight %}

The next leakiness comes from the point that the trait uses the property that *is supposed* to 
available in the consuming class. We cannot guard ourselves against it through the interface.

{% highlight php %}
trait WithLogging
{
    private Logger $logger;

    public function log(string $msg, string $level): void
    {
        $this->logger->setFileName($this->logFilename)->log($msg, $level);
    }
}
{% endhighlight %}

From the perspective of PHP this code is absolutely valid. But we mustn't allow this sort of thing
to happen in our code. Not for properties and not for class constants either.
The problem with `WithLogging` trait is that it has become too concerned with the
things that lay beyond its boundaries.

>*If it cannot be covered within the corresponding interface then it shouldn't 
> be included into the trait*.

Methods are safe if they appear in the corresponding interface. But when a trait 
uses a property that is defined outside its scope, it is never safe.

We continue with our `WithLogging` trait and try to fix the previous issue. If it is not
safe to use a property that is defined somewhere else, then we can add a constructor to 
the trait and define it right here. Let's add a constructor:

{% highlight php %}
trait WithLogging
{
    private Logger $logger;

    public function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
    }

    public function log(string $msg, string $level): void
    {
        $this->logger->log($msg, $level);
    }
}
{% endhighlight %}

Again PHP will not complain here. The code is valid, we can provide our own constructor
to any consuming class. To me it is not just a leak but a real crime. The constructor 
plays a very fundamental role in the setup of the object and thus trait shouldn't participate
in it. Imagine 






