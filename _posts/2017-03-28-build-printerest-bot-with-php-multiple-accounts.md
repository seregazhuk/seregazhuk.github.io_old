---
title: "Build Pinterest Bot with PHP: Multiple Accounts and Proxy"
tags: [PHP, Pinterest]
layout: post
---

In the [previous post]({% post_url 2017-03-25-build-pinterest-bot-with-php-auto-pin %}), we have created a small script to automate pins creation on Pinterest. It uses only one your account, but what if you have several accounts and want to use them all. And of course, it is a good practice to use a proxy, because it looks suspicious when you frequently create pins from different accounts but from one IP.

## Multiple Accounts

Let's consider that we have two accounts. One for cats and another for dogs. Each account has it's our credentials, folder with images, keywords dictionary and promoting link (for example a blog). We place them in a separate `accounts.php` file in the same directory without `pin.php` file, from the [previous post]({% post_url 2017-03-25-build-pinterest-bot-with-php-auto-pin %}). This file will be used as accounts config.

{% highlight php %}
<?php

// accounts.php

return [
    [
        'login' => 'mylogin1',
        'password' => 'mypass1',
        'username' => 'cats_account',
        'images' => 'images/cats_pics',
        'link' => 'http://awasome-blog-about-cats.com',
    ],
    [
        'login' => 'mylogin2',
        'password' => 'mypass2',
        'username' => 'dogs_account',
        'images' => 'images/dogs_pics',
        'link' => 'http://awasome-blog-about-dogs.com'
    ]
];
{% endhighlight %}

Then in we need to `require` it in our `pin.php`.

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';
$accounts = require __DIR__ . '/accounts.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;
{% endhighlight %}

Now, all our accounts data is stored in `$accounts` variable. Next step is to modify existing code and replace hardcoded account data. To make it simple, we will loop through our accounts and for every account, we will create pins. Now let's refactor a bit our `pin.php` file. We can extract a function for selecting an image and put it in a separate file `functions.php`:

{% highlight php %}
<?php
// functions.php

/**
 * @param string $folder
 * @return string
 */
function getImage($folder) {

    $images = glob("$folder/*.*");
    if(empty($images)) {
        echo "No images for posting\n";
        die();
    }

    return $images[0];
}
{% endhighlight %}

And again we need to require it in our main script:

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
$accounts = require __DIR__ . '/accounts.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$blogUrl = 'http://awasome-blog-about-cats.com';
$keywords = ['cats', 'kittens', 'funny cats', 'cat pictures', 'cats art'];

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

if ($bot->user->isBanned()) {
    echo "Account has been banned!\n";
    die();
}

// get board id
$boards = $bot->boards->forUser('my_username');
$boardId = $boards[0]['id'];

// select image for posting
$image = getImage('images');

// select keyword
$keyword = $keywords[array_rand($keywords)];

// create a pin
$bot->pins->create($image, $boardId, $keyword, $blogUrl);

// remove image
unlink($image);

{% endhighlight %}

Now, we are ready to add a loop through our accounts and replace all the hardcoded values:

{% highlight php %}
<?php

// pin.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
$accounts = require __DIR__ . '/accounts.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;
$bot = PinterestBot::create();

foreach($accounts as $account) {
    $bot->auth->login($account['login'], $account['password']);

    if ($bot->user->isBanned()) {
        $username = $account['username'];
        die("Account $username has been banned!\n");
    }

    // get board id
    $boards = $bot->boards->forUser($account['username']);
    $boardId = $boards[0]['id'];

    // select image for posting
    $image = getImage($account['images']);

    // select keyword
    $keywords = $account['keywords'];
    $keyword = $keywords[array_rand($keywords)];

    // create a pin
    $bot->pins->create($image, $boardId, $keyword, $account['link']);

    // remove image
    unlink($image);   
    $bot->auth->logout();
}
{% endhighlight %}

## Proxy

Next step is a proxy. Assume, that we will use one IP for each account. So, we need to update our `accounts.php` config and add proxy data there:

{% highlight php %}
<?php

// accounts.php
return [
    [
        'login' => 'mylogin1',
        'password' => 'mypass1',
        'username' => 'cats_account',
        'images' => 'images/cats_pics',
        'link' => 'http://awasome-blog-about-cats.com',
        'proxy' => [
            'host' => '123.123.21.21',
            'post' => 1234
        ],
    ],
    [
        'login' => 'mylogin2',
        'password' => 'mypass2',
        'username' => 'dogs_account',
        'images' => 'images/dogs_pics',
        'link' => 'http://awasome-blog-about-dogs.com',
        'proxy' => [
            'host' => '123.123.22.22',
            'post' => 5678
        ]
    ]
];

{% endhighlight %}

Perfect. Then we need to tell our bot to use a proxy. Here is a [full documentation](https://github.com/seregazhuk/php-pinterest-bot#use-proxy) about proxy usage. Only one line change is required to use a proxy:

{% highlight php %}
<?php

$bot->getHttpClient()->useProxy($account['proxy']['host'], $account['proxy']['port']);
{% endhighlight %}

Very easy, right? So, the final version for our `pin.php` script, now with multiple accounts and proxy:

{% highlight php %}
<?php

// pin.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
$accounts = require __DIR__ . '/accounts.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;
$bot = PinterestBot::create();

foreach($accounts as $account) {
    $bot->auth->login($account['login'], $account['password']);

    // add proxy
    if(isset($account['proxy'])) {
        $proxy = $account['proxy'];
        $bot->getHttpClient()->useProxy($proxy['host'], $proxy['port']);
    }

    if ($bot->user->isBanned()) {
        $username = $account['username'];
        die("Account $username has been banned!\n");
    }

    // get board id
    $boards = $bot->boards->forUser($account['username']);
    $boardId = $boards[0]['id'];

    // select image for posting
    $image = getImage($account['images']);

    // select keyword
    $keywords = $account['keywords'];
    $keyword = $keywords[array_rand($keywords)];

    // create a pin
    $bot->pins->create($image, $boardId, $keyword, $account['link']);

    // remove image
    unlink($image);   
    $bot->auth->logout();
}
{% endhighlight %}

Congratulations! We have upgraded our script. Now it is more flexible, we can add new accounts to it, without modifying the entire script. It is also easy to set up proxy connections for our accounts.

Further reading:
- [Build Pinterest Bot with PHP: Comments, Likes And Repins]({% post_url 2017-03-30-build-pinterest-bot-with-php-comments-and-repins %})