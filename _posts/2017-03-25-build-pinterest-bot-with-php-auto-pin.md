---
title: "Build Pinterest Bot with PHP: Automate pinning"
tags: [Pinterest, PHP]
layout: post
description: "Build Pinterest Bot with PHP, automate pins creation."
---

Our goal is to create an account on Pinterest, that behaves as a real person. He creates pins, likes or dislikes pins of other users, writes comments, so, behaves as a real person. For example, we are going to promote our own site or blog and we need to get targeted traffic from social networks. Pinterest has [its public API](https://developers.pinterest.com/docs/getting-started/introduction/?), but it is very poor: there is a very limited set of functions, you can call. You cannot send messages to other users, you cannot search, write comments and so on. It looks like a cut version of their website. But we need to use all the possibilities of [pinterest.com](http://pinterest.com). My [Pinterest Bot for PHP](https://github.com/seregazhuk/php-pinterest-bot) allows you to do it. This library simulates a real user, as he is logged in a browser. It will help us a lot. We start with simple functionality and gradually will move to advanced features. 

*I will skip installation step, it is [well described on the library page](https://github.com/seregazhuk/php-pinterest-bot#installation).*

At this moment, all we have is a newly created empty Pinterest account. We have login, password, and that is enough to start. The first strategy is to fill our account with pins. We are going to automate this process, so, every day our account will add 50 new pins to its board. But, don't forget that we are promoting our own blog about cats `http://awasome-blog-about-cats.com`. This means that only cats images are not enough. We can place a link to our blog under the pin and when a user clicks on the pin, he will be redirected to our blog. Also, we can add a description with some target keywords.

Now, let's list what we need to automate pins creation:

1. Create a board
2. Pre-saved pictures
3. A dictionary of keywords

First of all, we need a board. It is very easy. For simplicity, we start with all-in-one script `pin.php`. We will create *Cats* board. All the pins from an auto-pinning script will be placed here.

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

$bot->boards->create('Cats', 'Nice cats and kittens pics');
{% endhighlight %}

After running this script we will create *Cats* board with *Nice cats and kittens pics* description. I assume that you have already prepared your images and placed them in `images` directory in the same folder as you `pin.php` file. And also you need a small dictionary for your targeted keywords. Our dictionary will be a small array right in the script. 

{% highlight php %}
<?php
require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$blogUrl = 'http://awasome-blog-about-cats.com';
$keywords = ['cats', 'kittens', 'funny cats', 'cat pictures', 'cats art'];

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

// get board id
$boards = $bot->boards->forUser('my_username');
$boardId = $boards[0]['id'];

// select image for posting
$images = glob('images/*.*');
if(empty($images)) {
    echo "No images for posting\n";
    die();
}

$image = $images[0];

// select keyword
$keyword = $keywords[array_rand($keywords)];

// create a pin
$bot->pins->create($image, $boardId, $keyword, $blogUrl);

// remove image
unlink($image);

{% endhighlight %}

The script works according to the following algorithm:

1. We log in and select a board id.
2. Select an image to be posted.
3. Randomly select keyword for pin description
4. Create a pin with selected image, keyword and URL to our blog
5. Remove posted image, so that the pictures do not repeat

Done! Now, we can put our script in cron and execute it every 5 minutes.

{% highlight bash %}
*/5 * * * php /home/user/scripts/pinterest_bot/pin.php
{% endhighlight %}

And one more finishing touch, let's add a check just in case our account has a ban. We put it right after login.

{% highlight php %}
<?php

if ($bot->user->isBanned() {
    echo "Account has been banned!\n";
    die();
}

{% endhighlight %}

So the final script `pin.php` looks like this:

{% highlight php %}
<?php
require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$blogUrl = 'http://awasome-blog-about-cats.com';
$keywords = ['cats', 'kittens', 'funny cats', 'cat pictures', 'cats art'];

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

if ($bot->user->isBanned() {
    echo "Account has been banned!\n";
    die();
}

// get board id
$boards = $bot->boards->forUser('my_username');
$boardId = $boards[0]['id'];

// select image for posting
$images = glob('images/*.*');
if(empty($images)) {
    echo "No images for posting\n";
    die();
}

$image = $images[0];

// select keyword
$keyword = $keywords[array_rand($keywords)];

// create a pin
$bot->pins->create($image, $boardId, $keyword, $blogUrl);

// remove image
unlink($image);
{% endhighlight %}

And it's all! Nothing complicated here! We have a bot, which creates a pin in our board every 5 minutes. The pin contains relevant description and a link to our blog to get some traffic from Pinterest. 
