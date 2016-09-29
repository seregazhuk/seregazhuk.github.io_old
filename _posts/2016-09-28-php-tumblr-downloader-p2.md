---
title: "PHP Tumblr Downloader Part 2. Creating console application"
layout: post
tags: [PHP, Tumblr, Symfony]
---

In the [previous chapter]({% post_url 2016-09-20-php-tumblr-downloader %}), we have created a script, that can download all the photos from any Tumblr blog. But it is not very convenient to use. 
The path to a folder for saved photos and the blog itself are hardcoded in the `index.php` file. It will be more usefulto use this script as 
a console app and pass the blog with the folder for saving files as the params to it:

{% highlight bash %}
tumblr-downloader cats.tumblr.com path_to_folder
{% endhighlight %}

## Create An Entry Point
It can be done with the help of the [Symfony Components](http://symfony.com/components). There is a bunch of different usefulcomponents, but 
we need only one of them: [Console](http://symfony.com/components/Console) component.

{% highlight bash %}
composer require symfony/console
{% endhighlight %}

In the next step, we need to create an *executable* php script. For this purpose let's create a file `tumblr-downloader` *without any extension* with the following content:

{% highlight php %}
#! /usr/bin/env php

<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;

$app = new Application('Tumblr Downloader', '1.0'); 
$app->run();
{% endhighlight %}

This file should be in the root folder of our project, not in the `src` directory. It will be the enter point to our application. Lets make it executable and then run it:

<p class="text-center image">
    <img src="/assets/images/posts/php-tumblr-downloader-p2/app-first-run.jpg" alt="cgn-edit" class="">
</p>

Now our console application entry point is ready.

## Add Command

Next step is to create a *command* for grabbing photos. Let's create a class `PhotosCommand` in the `src` folder. To use it as a *command*
we need it to extend Symfony's Console Component `Command` class. `Command` class basically has two methods: `config` to set some 
command settings and `execute` to process the entire logic:

{% highlight php %}
<?php

namespace TumblrDownloader;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhotosCommand extends Command
{
    public function configure()
    {
        $this->setName('photos')
            ->setDescription('save photos from a specified blog.')
            ->addArgument('blog', InputArgument::REQUIRED, 'Blog to save photos from.');
    }    

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $message = 'Saving photos from ' . $input->getArgument('blog');
        $output->writeLn("<info>$message</info>");
    }
}
{% endhighlight %}

First of all we configure out command. We set its name and description, then we add a required argument *blog* to it.
Next we implement `execute` method. Now it is very simple, and does nothing useful. Now it is used only to check, if
our command works fine and recieves an argument. 

Then we need to add our `PhotosCommand` to the console application in the `tumblr-downloader` file:

{% highlight php %}
#! /usr/bin/env php

<?php
require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use TumblrDownloader\PhotosCommand;

$app = new Application('Tumblr Downloader', '1.0') ;
$app->add(new PhotosCommand());
$app->run();
{% endhighlight %}

Now, if we run it, we will see the following output:

{% highlight bash %}
./tumblr-downloader photos test
Saving photos from test
{% endhighlight %}

It means that our command works fine and it is time to implement the main saving logic. Our application logic is implemented in the 
`Downloader` class, so we need to get access to it in out command. To achieve this, we use dependency injection via constructor:

{% highlight php %}
#! /usr/bin/env php

<?php
require 'vendor/autoload.php';

use Tumblr\API\Client;
use TumblrDownloader\Downloader;
use TumblrDownloader\PhotosCommand;
use Symfony\Component\Console\Application;

$client = new Client(
    'YourConsumerKey', 
    'YourConsumerSecret', 
    'YourToken', 
    'YourSecret'
);

$downloader = new Downloader($client);

$app = new Application('Tumblr Downloader', '1.0') ;
$app->add(new PhotosCommand($downloader));
$app->run();
{% endhighlight %}

We build an instance of the `Downloader` class like we did it in [the previous chapter]({% post_url 2016-09-20-php-tumblr-downloader %}) and then pass as an argument to the 
`PhotosCommand` constructor. To make it work, we also need to modify `PhotosCommand` itself. We change the constructor
to get an instance of the `Downloader` class and save it then protected property `$downloader`. Then in the `execute` method
we use it and call its `photos` method with the passed to our command argument *blog*:

{% highlight php %}
<?php

namespace TumblrDownloader;

use TumblrDownloader\Downloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhotosCommand extends Command
{
    /**
     * Downloader
     */
    protected $downloader;

    public function __construct(Downloader $downloader)
    {
        $this->downloader = $downloader;

        parent::__construct();
    }

    // ... configure() method

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $blog = $input->getArgument('blog');

        $message = 'Saving photos from ' . $blog;
        $output->writeLn("<info>$message</info>");

        $this->downloader->photos($blog);

        $output->writeLn('Finished.');
    }
}
{% endhighlight %}

Let's try it:

{% highlight bash %}
./tumblr-downloader photos catsof.tumblr.com
Saving photos from catsof.tumblr.com
Finished.
{% endhighlight %}

## Progress Bar

Now out command is almost ready. We can add one more feature to it, to make it more convenient in use - progress bar. And again we will
use one of the Symfony Console Components called [Progress Bar](http://symfony.com/doc/current/components/console/helpers/progressbar.html).
To show a progress bar we need to know the total amount of posts. So we need to add a method to `Downloader` class, that returns total number of
posts of a blog:

{% highlight php %}
<?php

// src/Downloader.php

/**
 * @param string $blogName
 * @return integer
 */
protected function getTotalPosts($blogName)
{
    return $this->client->getBlogPosts($blogName, ['type' => 'photo'])->total_posts;
}

{% endhighlight %}

We use already familiar method `getBlogPosts` to get total posts with photos. Then we need to modify `Downloader` class and add some
progress bar logic. First of all, to use progress bar we need to inject it. Then in `photos` method we can call its methods: `start`, 
`advance` and `finish`:

{% highlight php %}
<?php

namespace seregazhuk\TumblrDownloader;

use stdClass;
use Tumblr\API\Client;
use Symfony\Component\Console\Helper\ProgressBar;

class Downloader 
{   
    // ...

    /**
     * @var ProgressBar 
     */
    protected $progress;

    /**
     * @param ProgressBar $progress
     */
    public function setProgressBar(ProgressBar $progress) 
    {
        $this->progress = $progress;

        return $this;
    }

    /**
     * @var string $blogName
     */
    public function photos($blogName)
    {
        $options = [
            'type' => 'photo',
            'limit' => 20,
            'offset' => 0
        ];

        $totalPosts = $this->getTotalPosts($blogName);
        $this->progress->start($totalPosts);

        while(true) {
            $posts = $this->client->getBlogPosts($blogName, $options)->posts;
            if(empty($posts)) break;

            foreach($posts as $post) {
                $this->saveImagesFromPost($post, $blogName);
                $this->progress->advance();
            }

            $options['offset'] += $options['limit'];
        }

        $this->progress->finish();
    }

{% endhighlight %}

When we are ready with `Downloader` class, then we need to instantiate an instance of the `ProgressBar` class to inject it to the `Downloader`. 
It can be done in the `execute` method of our `PhotosCommand`:

{% highlight php %}
<?php

namespace seregazhuk\TumblrDownloader;

use seregazhuk\TumblrDownloader\Downloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhotosCommand extends Command
{
    // ...
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $blog = $input->getArgument('blog');
        
        $message = 'Saving photos from ' . $blog;
        $output->writeLn("<info>$message</info>");

        $progress = new ProgressBar($output);

        $this->downloader
            ->setProgressBar($progress)
            ->photos($blog);

        $output->writeLn('');
        $output->writeLn('Finished.');
    }
{% endhighlight %}

And that is all. Now our console command looks much better. It accepts blog name as an argument and outputs a process of the downloading photos.

<p class="text-center image">
    <img src="/assets/images/posts/php-tumblr-downloader-p2/progress-bar.gif" alt="cgn-edit" class="">
</p>