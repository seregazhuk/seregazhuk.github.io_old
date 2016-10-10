---
title: "PHP Tumblr Downloader Part 2. Creating console application"
layout: post
tags: [PHP, Tumblr, Symfony Components]
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

Next step is to create a *command* for grabbing photos. Let's create a class `SaveCommand` in the `src` folder. To use it as a *command*
we need it to extend Symfony's Console Component `Command` class. `Command` class basically has two methods: `config` to set some 
command settings and `execute` to process the entire logic:

{% highlight php %}
<?php

namespace TumblrDownloader;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SaveCommand extends Command
{
    public function configure()
    {
        $this->setName('save')
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

Then we need to add our `SaveCommand` to the console application in the `tumblr-downloader` file:

{% highlight php %}
#! /usr/bin/env php

<?php
require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use TumblrDownloader\SaveCommand;

$app = new Application('Tumblr Downloader', '1.0') ;
$app->add(new SaveCommand());
$app->run();
{% endhighlight %}

Now, if we run it, we will see the following output:

{% highlight bash %}
./tumblr-downloader save test
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
use TumblrDownloader\SaveCommand;
use Symfony\Component\Console\Application;

$client = new Client(
    'YourConsumerKey', 
    'YourConsumerSecret', 
    'YourToken', 
    'YourSecret'
);

$downloader = new Downloader($client);

$app = new Application('Tumblr Downloader', '1.0') ;
$app->add(new SaveCommand($downloader));
$app->run();
{% endhighlight %}

We build an instance of the `Downloader` class like we did it in [the previous chapter]({% post_url 2016-09-20-php-tumblr-downloader %}) and then pass as an argument to the 
`SaveCommand` constructor. To make it work, we also need to modify `SaveCommand` itself. We change the constructor
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

class SaveCommand extends Command
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

        $this->downloader->save($blog);

        $output->writeLn('Finished.');
    }
}
{% endhighlight %}

Let's try it:

{% highlight bash %}
./tumblr-downloader save catsof.tumblr.com
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
public function getTotalPosts($blogName)
{
    return $this->client
        ->getBlogPosts($blogName, ['type' => 'photo'])
        ->total_posts;
}

{% endhighlight %}

We use already familiar method `getBlogPosts` to get total posts with photos. Then we need to modify `Downloader` class and add support to call 
a closure on every saved post. We will use this closure to output the progress:

{% highlight php %}
<?php

namespace TumblrDownloader;

use stdClass;
use Tumblr\API\Client;
use Symfony\Component\Console\Helper\ProgressBar;

class Downloader 
{   
    // ...

    /**
     * @var string $blogName
     * @param callable $processCallback
     */
    public function save($blogName, callable $processCallback = null)
    {
        $options = [
            'type' => 'photo',
            'limit' => 20,
            'offset' => 0
        ];

        while(true) {
            $posts = $this->client->getBlogPosts($blogName, $options)->posts;
            if(empty($posts)) break;

            foreach($posts as $post) {
                $this->saveImagesFromPost($post, $blogName);

                if($processCallback) $processCallback($post);
            }

            $options['offset'] += $options['limit'];
        }
    }

{% endhighlight %}

`$progressCallback` can be a closure. An it accepts current saving post as an arguent. 

When we are ready with `Downloader` class, then we need to instantiate an instance of the `ProgressBar` and create a closure to pass it to the `Downloader`. 
It can be done in the `execute` method of our `SaveCommand`:

{% highlight php %}
<?php

namespace TumblrDownloader;

use TumblrDownloader\Downloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SaveCommand extends Command
{
    // ...
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $blog = $input->getArgument('blog');
        
        $message = 'Saving photos from ' . $blog;
        $output->writeLn("<info>$message</info>");

        $progress = new ProgressBar($output);

        $progress->start($totalPosts);
        $this->downloader
            ->save($blog, function() use ($progress){
                    $progress->advance();
                });

        $progress->finish();
        $output->writeLn('');
        $output->writeLn('Finished.');
    }
{% endhighlight %}

And that is all. Now our console command looks much better. It accepts blog name as an argument and outputs a process of the downloading photos.
At last we can add a counter for saved photos. And after saving has been completed, we can show the total number of the saved photos:

src/Downloader:

{% highlight php %}
<?php

namespace TumblrDownloader;

use stdClass;
use Tumblr\API\Client;
use Symfony\Component\Console\Helper\ProgressBar;

class Downloader 
{   
    // ...

    /**
     * @var int
     */
    protected $totalSaved = 0;

    // ... 
        /**
     * @param stdClass $post
     * @param string $directory
     */
    protected function saveImagesFromPost($post, $directory)
    {
        foreach($post->photos as $photo) {
            $imageUrl = $photo->original_size->url;

            $path = $this->getSavePath($directory);
            file_put_contents(
                $path . basename($imageUrl), 
                file_get_contents($imageUrl)
            );

            $this->totalSaved ++;
    }

    // ...

    /**
     * @return int
     */
    public function getTotalSaved() 
    {
        return $this->totalSaved;
    }
}
{% endhighlight %}

src/SaveCommand:

{% highlight php %}
<?php

// ...

class SaveCommand extends Command
{
    // ...
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $blog = $input->getArgument('blog');
        
        $message = 'Saving photos from ' . $blog;
        $output->writeLn("<info>$message</info>");

        $progress = new ProgressBar($output);

        $progress->start($this->downloader->getTotalPosts($blog));
        $this->downloader  
            ->save($blog, function() use ($progress) {
                $progress->advance();
        });
        $progress->finish();

        $output->writeLn('');
        $output->writeLn("<comment>Finished. $saved photos saved. </comment>");
    }
}

{% endhighlight %}

The final output of our command in action:

<p class="text-center image">
    <img src="/assets/images/posts/php-tumblr-downloader-p2/progress-bar.gif" alt="cgn-edit" class="">
</p>
