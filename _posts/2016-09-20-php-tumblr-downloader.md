---
title: "PHP Tumblr Downloader Part 1. Saving photos from blogs"
layout: post
description: "Create Tumblr images parser in PHP."
tags: [PHP, Tumblr]
---

## Registering App in Tumblr

I'm going to create a script to rip all images/videos from one's blog from [Tumblr](http://tumblr.com). To interact with Tumblr I will
use their [official PHP client](https://github.com/tumblr/tumblr.php). The client itself requires API credentials: 

{% highlight php %}
<?php

$client = new Tumblr\API\Client($consumerKey, $consumerSecret);
$client->setToken($token, $tokenSecret);
{% endhighlight %}

To get API token and secret we need to have an active Tumblr account. Next, we need to register our application to get access to [Tumblr API](https://www.tumblr.com/docs/en/api/v2). To register an application go to the Apps section of your account settings or [Applications](https://www.tumblr.com/oauth/apps) page and register a new application:

<p class="text-center image">
    <img src="/assets/images/posts/php-tumblr-downloader/register-app.jpg" alt="cgn-edit" class="">
</p>

After you have filled the form, click *register* and you will be redirected to the page with all your registered applications. Here you can find 
your consumer key and secret key. To get tokens we need to authorize our application. To do it click *Explore API* link right below your
*consumer key*:

<p class="text-center image">
	<img src="/assets/images/posts/php-tumblr-downloader/apps.jpg" alt="cgn-edit" class="">
</p>

Here your should allow access to your account for your application. 

<p class="text-center image">
	<img src="/assets/images/posts/php-tumblr-downloader/authorize-app.jpg" alt="cgn-edit" class="">
</p>

Then you will be redirected to [API Console](https://api.tumblr.com/console/calls/user/info) where you can find a ready example with their 
official clients for different languages. We are interested here in PHP, so we grab our API credentials and now we are ready to start coding.

## Start Coding

Firstly we need to do some initial set up. Let's create a new directory for our downloader called `tumblr-downloader` and move to it:

{% highlight bash %}
mkdir tumblr-downloader
cd tumblr-downloader
{% endhighlight %}

Next, we need to install the [official Tumblr PHP client](https://github.com/tumblr/tumblr.php):

{% highlight bash %}
composer require tumblr/tumblr
{% endhighlight %}

Then we need to setup composer `autoload` section in the *composer.json* file and then run `composer dump -o` in the console 
to create an autoload file:

{% highlight json %}
{
    "autoload": {
       "psr-4" : {
            "TumblrDownloader\\" : "src"    		
        }
    }
}	
{% endhighlight %}

Now everythin is ready. We can start coding with our *Downloader* class in the `src` folder:

{% highlight php %}
<?php

// src/Downloader.php

namespace TumblrDownloader;

use Tumblr\API\Client;

class Downloader 
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
      $this->client = $client;
    }
}
{% endhighlight %} 

In this class in the constructor we require an instance of the official Tumblr PHP client. Next, create an `index.php` file in out root `tumblr-downloader` folder. It will be our enter point for the application:

{% highlight php %}
<?php

require 'vendor/autoload.php';

use Tumblr\API\Client;
use TumblrDownloader\Downloader;

$client = new Client(
    'YourConsumerKey', 
    'YourConsumerSecret', 
    'YourToken', 
    'YourSecret'
);
$downloader = new Downloader($client);
{% endhighlight %}

First of all we require composer autoload file, then instantiate a `Client` instance with our application credentials, and lastly create an instance
of our `Downloader` class.

## Grab photos from the blog

To get all posts from the blog we will use [posts](https://www.tumblr.com/docs/en/api/v2#posts) API method. We need to grab all posts of
type `photo` for a specified blog. So let's create a method for it:

{% highlight php %}
<?php

// src/Downloader.php

/**
 * @param string $blogName
 */
public function save($blogName)
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
            $this->savePhotosFromPost($post, $blogName);
        }

        $options['offset'] += $options['limit'];
    }
}
{% endhighlight %}

This method is very simple. We loop through the blog by retrieving 20 (API limit) posts at a time. Every post then is handled with 
the `savePhotosFromPost` method. Every post of `photo` type has `photos` array. Each element is an instance of *stdClass* class. We are 
interested in its `original_size` property. It contains also an instance of the *stdClass* class with `url` property, which contains URL 
to the needed image:

{% highlight php %}
<?php

// src/Downloader.php

/**
 * @param stdClass $post
 * @param string $directory
 */
protected function savePhotosFromPost($post, $directory)
{
    foreach($post->photos as $photo) {
        $imageUrl = $photo->original_size->url;

        $path = $this->getSavePath($directory);
        file_put_contents(
            $path . basename($imageUrl), 
            file_get_contents($imageUrl)
        );
    }
}

{% endhighlight %}

Grabbed photos will be placed in the `photos` directory in the root of our project. For each blog we automatically create a subfolder with its name and put grabbed pictures there:

{% highlight php %}
<?php

// src/Downloader.php

/**
 * @param string $directory
 * @return string
 */
protected function getSavePath($directory)
{
    $path = 'photos' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR;
    if(!is_dir($path))  {
        mkdir($path,  0777, true);
    }

    return $path;
}
{% endhighlight %}

Now our downloader is ready to rip images from a blog. Let's update our `index.php` files with the following code and save some cats photos:

{% highlight php %}
<?php

// index.php

$downloader = new Downloader($client);
$downloader->save('catsof.tumblr.com');
{% endhighlight %}

It will take some time to grab all photos from the `catsof.tumblr.com` blog. All these photos will be available in the `photos/catsof.tumblr.com` folder:

<p class="text-center image">
    <img src="/assets/images/posts/php-tumblr-downloader/parsed-images.jpg" alt="cgn-edit" class="">
</p>

## Conclusion
In this chapter, we have registered in Tumblr API our apllication. We have created our downloader, which can save photos from any Tumblr blog. In the next chapter, we will add some interactive functionality with Symfony Components. We will create a real console command and add a progress bar to it.