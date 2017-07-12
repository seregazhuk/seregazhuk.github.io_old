---

title: "Singleton: Anti-Pattern Or Not"
tags: [PHP, OOP, DesignPatterns]
layout: post
description: "Why Singleton is considered as anti-pattern and testing with Singleton."
---

## Definition

> — "Do you know any Singleton jokes?" <br>
> — "Just one."

Singleton is one of the simplest patterns to understand. The main goal of it is to limit the existence of only one instance of the class. The reason for it is usually the following: 

> *only one class object is required during the lifecycle of the application and you need this object to be available anywhere in the application*.

<p class="text-center image">
    <img src="/assets/images/posts/singleton/meme.jpg" alt="cgn-edit" class="">
</p>

The Singleton pattern assumes that there is a static method for getting an instance of the class (`getInstance()`). When calling it a reference to the original object is returned. This original object is stored in a static variable, which allows keeping this original object unchanged between `getInstance()` calls. Also, a constructor is `private` to ensure that you always use only a static `getInstance` method to get the object. In PHP we have some *magic* methods which can be used to create a new instance of the class: `__clone` and `__wakeup`, they also should be `private`:

{% highlight php %}
<?php

class Singleton
{
    protected $instance;

    private function __construct();
    private function __clone();
    private function __wakeup();

    public static function getInstance() 
    {
        if( is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }
}
{% endhighlight %}

This pattern can be useful when we have some kind of a shared resource in our application: a classic example is a database connection. Different parts of the application might want to use this connection.

## Problems 

The problems with Singleton comes when we start using them as global instances. But the main problem is not with the globals, but how we use them. A *single instance* doesn't actually mean *globally accessible*. The common mistake is to always access to an instance of the singleton directly via its static `getInstance` method. 

Consider a classic Singleton example with a database connection:

{% highlight php %}
<?php

class DB 
{
    /**
     * @var PDO
     */
    protected static $connection;

    public static function getInstance(array $config = []) 
    {
        if(is_null(self::$connection)) {
            self::init($config);
        }    
        
        return self::$connection;
    }

    protected static function init($config)
    {
       try {
            self::$connection = new \PDO(
                $config['connection'] . ';dbname=' . $config['name'],
                $config['username'],
                $config['password'],
                $config['options'],
            );
            return self::$connection;
        }
        catch(PDOException $e) {
            die($e->getMessage());
        } 
    }

    /**
     * @param string $table
     * @return PDOStatement
     */
    public function executeSelect($table)
    {
        $statement = self::getInstance()->prepare("SELECT * FROM {$table}");

        $statement->execute();

        return $statement;
    }
}
{% endhighlight %}

And then in our application, we start using it to perform queries like this:

{% highlight php %}
<?php

class QueryBuilder 
{
    /**
     * @param string $table
     * @return array
     */
    public function selectAll($table)
    {
        return $this->executeSelect($table)->fetchAll(); 
    }

    /**
     * @param string $table
     * @return mixed
     */
    public function selectOne($table)
    {
         return DB::getInstance()->executeSelect($table)->fetch(); 
    }
}
{% endhighlight %}

In this case, our `QueryBuilder` is tightly coupled to `DB` class. We cannot use `QueryBuilder` without calling `DB` class. It is now impossible to test `QueryBuilder` without actually touching the database. Because of the hardcoded dependency, we cannot mock `DB` class with some fake connection.

*A database is actually **not** a good example for a Singleton. For example, a client wants to connect to the same database but with different credentials. Or a client wants to connect to several databases.*

## Solution

To fix this issue we should use Inversion of Control and pass singleton as a dependency, just like you would do it with any other object. Most of our code shouldn't event know it is dealing with a Singleton. For testing purposes we can also create the `Connection` interface, so we could mock it:

{% highlight php %}
<?php

interface Connection
{
    /**
     * @param string $table
     * @return PDOStatement
     */
    public function executeSelect($table);
}

class DB implements Connection 
{
    // ... 
}

{% endhighlight %}

The `QueryBuilder` should depend on the `Connection` interface. `QueryBuilder` completely depends on the database connection, so in our case, we can pass an instance of the database connection as a constructor dependency. 

{% highlight php %}
<?php

class QueryBuilder 
{

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $table
     * @return array
     */
    public function selectAll($table)
    {
        return  $this->connection->executeSelect($table)->fetchAll(); 
    }

    /**
     * @param string $table
     * @return mixed
     */
    public function selectOne($table)
    {
         return  $this->connection->executeSelect($table)->fetch(); 
    }
}
{% endhighlight %}

No more static calls and hardcoded dependencies. We can easily mock database connection and test `QueryBuilder` in isolation. `QueryBuilder` even doesn't know that it collaborates with a Singleton. With this approach, you may think that now we have to pass around a reference to a Singleton instance everywhere in our application, so actually, we don't have a Singleton anymore. But do you remember the main purpose of the Singleton? It is **not a global state** and **not a static access**, but **providing only one instance of the class**. 

## Singleton And Tests

With the *only one instance of the class*, we can have some problems in testing. Our tests may become dependable on each other because Singleton stores its state during the tests. If one test changes the state of the Singleton instance, the other test cannot start from scratch and has to deal with this *changed state*. Consider this simple logger class:

{% highlight php %}
<?php

class Logger
{
    protected static $instance = NULL;
    protected $logs = [];

    public static function getInstance() 
    {
        if(self::$instance === NULL) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * @param string $message
     */
    public function log($message) 
    {
        $this->logs[] = $message;
    }

    /**
     * @return array
     */
    public function getLogs() 
    {
        return $this->logs;
    }
};
{% endhighlight %}

If we start testing this class we can face some unexpected results:

{% highlight php %}
<?php

class LoggerTest extends TestCase
{
    /** @test **/
    public function it_stores_messages()
    {
        $logger = Logger::getInstance();
        $logger->log('test message');

        $this->assertEquals(['test message'], $logger->getLogs()); // <--- may fail!!!
    }
}
{% endhighlight %}

This test *may* fail if somewhere in other tests we have already logged something. There is a good recipe how to fix this issue in the book [Working Effectively with Legacy Code](https://www.amazon.com/Working-Effectively-Legacy-Michael-Feathers/dp/0131177052). The author advices to introduce a `setInstance()` method, which allows to replace the static instance of the Singleton: 

{% highlight php %}
<?php

class Logger
{
    protected static $instance = NULL;
    protected $logs = [];

    public static function setInstance(Logger $instance) 
    {
        self::$instance = $instance;
    }

    // ...
};
{% endhighlight %}

This allows us to mock the Singleton. Another option is when we need to *reset* the state, especially when testing the Singleton itself:

{% highlight php %}
<?php
class Logger
{
    protected static $instance = NULL;
    protected $logs = [];

    public static function reset() 
    {
        self::$instance = new static;
    }

    // ...
};
{% endhighlight %}

Method `reset()` simply overrides the current state of the Singleton, so we can start from scratch. Then in our tests, we can use `setUp` method to `reset` Singleton's state before each test:

{% highlight php %}
<?php

class LoggerTest extends TestCase
{
    protected function setUp()
    {
        Logger::reset();
        
        parent::setUp();
    }

    /** @test **/
    public function it_stores_messages()
    {
        $logger = Logger::getInstance();
        $logger->log('test message');

        $this->assertEquals(['test message'], $logger->getLogs()); 
    }
}
{% endhighlight %}

## Conclusion

In practice, the Singleton is just a programming technique, which can be a useful part of your toolkit. Singletons themselves are not bad, but they are *hard to do right*. We always consider singletons as globals. Singleton is **not a pattern to wrap globals**. The main goal of this pattern is to guarantee that **there is only one instance of the given class** during the application lifecycle. 

Don't confuse singletons and globals. When used for the purpose it was intended for, you will achieve only benefits from the Singleton pattern. Simply in most cases rather than teaching good examples of how to do Singletons we have tons of tutorials where we show bad examples and then later we make a conclusion that singleton is a bad design pattern. 