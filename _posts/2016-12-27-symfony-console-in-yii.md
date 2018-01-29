---
title: Symfony Console Component In Yii
layout: post
tags: [Symfony Components, PHP, Yii]
description: "Use Symfony Console Component in Yii, progress bar in yii console"
---

In Laravel 5 I enjoy its nice console output based on [Symfony Console Component](http://symfony.com/doc/current/components/console.html). Working with legacy code in Yii in console we have a very poor output. We can use `echo` and format the output ourselves. The better choice is to integrate Symfony component in Yii and use it for formatted output.

## Setup

First of all, we need to install it via composer:

{% highlight base %}
composer require symfony/console
{% endhighlight %}

Then we need to add Composer's *autoload.php* file to Yii console entry point file `yiic.php`:

{% highlight php %}
<?php

// ... 
require __DIR__ . '/../../vendor/autoload.php';

$app = new CConsoleApplication(dirname(__FILE__) . '/config/console.php');
$app->commandRunner->addCommands(YII_PATH.'/cli/commands');
$app->run();
{% endhighlight %}

## Extend ConsoleCommand class
Next, we can extend Yii CConsoleCommand class and create our own console command class with some helper methods:

{% highlight php %}
<?php

<?php

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EConsoleCommand.php
 */
class EConsoleCommand extends CConsoleCommand
{

}
{% endhighlight %}

To use Symfony formatted output we need to have an instance of the `OutputInterface`. The better place to instantiate it is Yii components `init()` method:

{% highlight php %}
<?php

<?php

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EConsoleCommand.php
 */
class EConsoleCommand extends CConsoleCommand
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function init()
    {
        $this->output = new ConsoleOutput();
    }
}
{% endhighlight %}

There are several implementations of the `OutputInterface`. We will use `ConsoleOutput` one. Next step is to add some helper methods for formatted output: `error`, `info`, `line` and `comment`. Behind the scenes they will delegate to `OutputInterface::writeln()` methods with different tags:

{% highlight php %}
 <?php

/**
 * @param string $message
 */
public function info($message)
{
    $this->output->writeln("<info>$message</info>");
}

/**
 * @param string $message
 */
public function error($message)
{
    $this->output->writeln("<error>$message</error>");
}

/**
 * @param string $message
 */
public function comment($message)
{
    $this->output->writeln("<comment>$message</comment>");
}

/**
 * @param string $message
 */
public function line($message)
{
    $this->output->writeln("$message");
}
{% endhighlight %} 

Now in our console command we can inherit from the new `EConsoleCommand` and use its methods:

{% highlight php %}
<?php

class StatisticsCommand extends EConsoleCommand
{
    protected $description = 'Aggregate statistics for the last week';

    public function actionIndex()
    {
        $this->line($this->description);

        // some code 
        // ...

        $this->line("");
        $this->info("Done.");
    }
}
{% endhighlight %}

## Progress bar
Progress bar is very useful working with long-running commands. You can see the current pretty formatted progress of your command. Let's add this functionality to our commands. `ProgressBar` is already available in Symonfy Console Component. Its constructor requires an instance of the `OutputInterface`, which we already have in our base command class:

{% highlight php %}
<?php

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EConsoleCommand.php
 */
class EConsoleCommand extends CConsoleCommand
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function init()
    {
        $this->output = new ConsoleOutput();
    }

    /**
     * @param int $count
     * @return ProgressBar
     */
    public function makeProgressBar($count = 0)
    {
        return new ProgressBar($this->output, $count);
    }
}
{% endhighlight %}

Then, in our `StatisticsCommand`, we can output the current progress of the command. There are three common methods in `ProgressBar` class for progress output: `start()`, `finish()` and `advance()`:

{% highlight php %}
<?php

class StatisticsCommand extends EConsoleCommand
{
    protected $description = 'Aggregate statistics for the last week';

    public function actionIndex()
    {
        $this->line($this->description);

        $data = $this->getDataForAggregation();

        $progress = $this->makeProgressBar(count($data));
        $progress->start();

        foreach($data as $record) {
            // aggregate
            $progress->advance();
        }

        $progress->finish();
        $this->line("");
        $this->info("Done.");
    }
}
{% endhighlight %}

Symfony Console Component is a very powerful tool. There are many advanced formatted output options: questions, tables and other. Also, we can configure a progress bar as we want. For more details take a look at [Symfony Console Component docs](http://symfony.com/doc/current/components/console.html) and [ProgressBar docs](http://symfony.com/doc/current/components/console/helpers/progressbar.html).
