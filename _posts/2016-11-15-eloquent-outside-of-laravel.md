---
title: "Eloquent Outside of Laravel"
layout: post
tags: [Laravel]
---

First of all, we need to install the required component via composer:

{% highlight base %}
composer install illuminate/database
{% endhighlight %}

Let's create our `index.php` file to start experimenting:

{% highlight php %}
<?php

require 'vendor/autoload.php'

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule();
{% endhighlight %}

*Capsule* class is a sort of helper to work with a database. It uses Laravel's Container inside to manage connections and to create a *DatabaseManager* object. 

The next step is to add a connection with the specified settings (driver, login, password):

{% highlight php %}
<?php
// index.php
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/../database.sqlite'
]);

$capsule->bootEloquent();
{% endhighlight %}

*AddConnection()* method adds specified to the container's `['config']['database.connections']` array. Then *bootEloquent()* passes a *DatabaseManager* instance as a connection resolver to the *Eloquent\Model* class:

{% highlight php %}
<?php 
// Illuminate\Database\Capsule\Manager.php

/**
 * Register a connection with the manager.
 *
 * @param  array   $config
 * @param  string  $name
 * @return void
 */
public function addConnection(array $config, $name = 'default')
{
    $connections = $this->container['config']['database.connections'];

    $connections[$name] = $config;

    $this->container['config']['database.connections'] = $connections;
}

/**
 * Bootstrap Eloquent so it is ready for usage.
 *
 * @return void
 */
public function bootEloquent()
{
    Eloquent::setConnectionResolver($this->manager);

    // If we have an event dispatcher instance, we will go ahead and register it
    // with the Eloquent ORM, allowing for model callbacks while creating and
    // updating "model" instances; however, if it not necessary to operate.
    if ($dispatcher = $this->getEventDispatcher()) {
        Eloquent::setEventDispatcher($dispatcher);
    }
}

{% endhighlight %}

So, this was our setup step and now we are ready to start implementing models. We start with creating a special folder `models` for them. And lets create a User model:

{% highlight php %}
<?php

use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email'
    ];
}
{% endhighlight %}

And that is all! Now we can create and use Eloquent models in our application. The only required steps are:

1. Create a capsule manager and add a connection to it.
2. Boot eloquent on the capsule manager.
3. Create and use Eloquent models by extending *Eloquent\Model* class.