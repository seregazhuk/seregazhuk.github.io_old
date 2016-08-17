---
title: "Laravel: Autoload Console Commands In Kernel"
layout: post
tags: [Laravel]
---

When your application grows and if you often use console commands, you can find out that your 'app/Console/Kernel.php' file may look
something like this:

{% highlight php %}
<?php

/**
 *
 * The Artisan commands provided by your application.
 *
 * @var array
 */
 protected $commands = [
    'App\Console\Commands\CommandOne',           
    'App\Console\Commands\CommandTwo',           
    // ... other over 100500 commands
    'App\Console\Commands\TheLastOneCommand',           
 ];
{% endhighlight %} 

The definition of the `$commands` property grows very quickly and looks very ugly. How to solve this problem?
All of our commands are usually located in one folder. Of course they can me placed in different subfolders, but the main folder is always one.
So, we can dynamically scan it and fill the `$commands` property. Let's override a constructor of the `Kernel` class. It accepts two arguments:
the application instance and the event dispatcher.

{% highlight php %}
<?php

// ...
class Kernel extends ConsoleKernel {
    // ...
    public function __construct(Application $app, Dispatcher $events) {
        $this->loadCommands('Console/Commands');
        parent::__construct($app, $events);
    }
}
{% endhighlight %}

In the code above we simply proxy the passed params to the parent constructor, but we call a new `loadCommands` method. Here is the code of it:

{% highlight php %}
<?php


class Kernel extends ConsoleKernel {

    // ...

    /**
     * @param string $path
     * @return $this
     */
    protected function loadCommands($path) {
        collect(candir($realPath))
            ->each(function($item) use ($path, $realPath){
                if (in_array($item, ['.', '..'])) return;

                if (is_dir($realPath . $item)) {
                    $this->loadCommands($path . $item . '/');
                }

                if (is_file($realPath . $item)) {
                    $item = str_replace('.php', '', $item);
                    $this->commands[] = str_replace('/', '\\', "App\\{$path}$item");
                }
            });
    }
}
{% endhighlight %}

It recursively scans the passed directory as an argument and appends all founded files to the `$commands` property. 
