---
title: "PHP Tumblr Downloader Part2. Creating console application"
layout: post
tags: [PHP, Tumblr, Symfony]
---

In the [previous chapter](#) we have created a script, that can download all the photos from any Tumblr blog. But it is not very convenient to use. 
The path to folder for saved photos and the blog itself are hardcoded in the `index.php` file. It will be more usefull to use this script as 
a console app and pass the blog with the folder for saving files as the params to it:

{% highlight bash %}
tumblr-downloader cats.tumblr.com path_to_folder
{% endhighlight %}

## Create An Entry Point
It can be done with the help of the [Symfony Components](http://symfony.com/components). There is a banch of different usefull components, but 
we need only one of them: [Console](http://symfony.com/components/Console) component.

{% highlight bash %}
composer require symfony/console
{% endhighlight %}

In the next step we need to create an *executable* php script. For this purpose lets create a file `tumblr-downloader` *without any extension* with the following content:

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

Next step is to create a *command* for grabbing photos. Lets create a class `PhotosCommand` in the `src` folder. To use it as a *command*
we need it to extend Symfony's Console Component `Command` class. `Command` class basically has two methods: `config` to set some 
command settings and `execute` to process the entire logic:

{% highlight php %}
<?php

namespace TumblrDownloader;

use Symfony\Component\Console\Command\Command;

class PhotosCommand extends Command
{
    public function configure()
    {

    }    
}
{% endhighlight %}

