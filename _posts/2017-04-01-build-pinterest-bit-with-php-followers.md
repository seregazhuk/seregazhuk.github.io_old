---
title: "Build Pinterest Bot With PHP: Followers"
tags: [PHP, Pintereset]
layout: post
---

Next step in promoting our blog on Pinterest is to follow popular accounts in our niche. [Pinterest Bot for PHP](https://github.com/seregazhuk/php-pinterest-bot) library provides this functionality for us.

I'm not going to cover installation and setup of the library here. You can find it in the [documentation](https://github.com/seregazhuk/php-pinterest-bot#installation); I consider that you have already installed it and you are ready to go. So, we start.

{% highlight php %}
<?php
require('vendor/autoload.php'); 

use seregazhuk\PinterestBot\Factories\PinterestBot;
$bot = PinterestBot::create();

$bot->auth->login('mypinterestlogin', 'mypinterestpassword');
{% endhighlight %}


Firstly  we need to select people we are interested in. Very easy. If we are interested in people who have a lot of pins about cats, we can simple search for these people like this:

{% highlight php %}
<?php
require('vendor/autoload.php'); 

use seregazhuk\PinterestBot\Factories\PinterestBot;
$bot = PinterestBot::create();

$bot->auth->login('mypinterestlogin', 'mypinterestpassword');
$peopleToFollow = $bot->pins->search('cats')->toArray();
{% endhighlight %}

> [Here](https://github.com/seregazhuk/php-pinterest-bot#search) you can find more info about `search` method.

Nice! Now `$peopleToFollow` variable stores an array of users data. We can loop through this array and call `follow` method for every user. We need only user's id to follow:

{% highlight php %}
<?php
require('vendor/autoload.php'); 

use seregazhuk\PinterestBot\Factories\PinterestBot;
$bot = PinterestBot::create();

$bot->auth->login('mypinterestlogin', 'mypinterestpassword');
$peopleToFollow = $bot->pins->search('cats')->toArray();

foreach($peopleToFollow as $user) {
   $bot->pinners->follow($user['id']); 
}
{% endhighlight %}

Done! We have subscribed to all these people. 

If you have posted many pictures likely you already have some subscribers. It will be nice if we also can subscribe to them. To do this we need:

1. Receive all our followers.
2. Follow them.

Again, very easy, Several lines of code. We need to replace `search` method line with `followers` method.

{% highlight php %}
<?php
require('vendor/autoload.php'); 

use seregazhuk\PinterestBot\Factories\PinterestBot;
$bot = PinterestBot::create();

$bot->auth->login('mypinterestlogin', 'mypinterestpassword');
$peopleToFollow = $bot->pinners->followers('myusername')->toArray();

foreach($peopleToFollow as $user) {
   $bot->pinners->follow($user['id']); 
}
{% endhighlight %}

Done! Now we loop through our followers and subscribe to them. Followers is a nice way to expand a social net for our account, so more and more people will view our pins and visit our blog.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/php-pinterest-bot/blob/master/examples/followers.php).

Previous articles:

- [Build Pinterest Bot with PHP: Multiple Accounts and Proxy]({% post_url 2017-03-28-build-printerest-bot-with-php-multiple-accounts %})
- [Build Pinterest Bot with PHP: Automate pinning]({% post_url 2017-03-25-build-pinterest-bot-with-php-auto-pin %})
- [Build Pinterest Bot with PHP: Comments, Likes And Repins]({% post_url 2017-03-30-build-pinterest-bot-with-php-comments-and-repins %})