---
title: "Build Pinterest Bot with PHP: Comments, Likes And Repins"
layout: post
tags: [PHP, Pinterest]
---

<div class="alert alert-danger">
    Notice, that likes functionality <a href="https://help.pinterest.com/en/feedback-about-removing-likes">has been disabled</a> by Pinterest.
</div>

In the previous articles, we have created a script that [makes pins every five minutes]({% post_url 2017-03-25-build-pinterest-bot-with-php-auto-pin %}) and [uses multiple accounts]({% post_url 2017-03-28-build-printerest-bot-with-php-multiple-accounts %}). But this is not enough, nobody will see your pins if you have no subscribers and nobody follows you. So, in this article are going to comment, like and repin other users pins. We are going to use a library called [Pinterest Bot for PHP](https://github.com/seregazhuk/php-pinterest-bot).

## Before we start

Every action that we are going to perform requires pins. We need something to comment/like/repin. So the first thing we need is to find some pins. For example, we are targeted on *cats* topics and we need pins related to cats. We can *search* for them.

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

$pins = $bot->pins->search('cats')->toArray();
{% endhighlight %}

Now, we have an array of pins stored in our `$pins` variable.

>Method `search` returns `Pagination` object. This object makes a request to Pinterest and receives results according to their pagination (20 items on the page). To get all the results at once as an array we use method `toArray` here. You can find more information about `Pagination` object [here](https://github.com/seregazhuk/php-pinterest-bot#pagination).


## Likes

We start with the most simple thing - likes. Every item in `$pins` array now contains a lot of different information about every pin we have found, but we only need an id. Next step is to put *like* on every pin in this array.

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

$pins = $bot->pins->search('cats')->toArray();

foreach($pins as $pin) {
    $bot->pins->like($pin['id']);
}
{% endhighlight %}

Very simple, right? Only one line of code to create a like.

## Repins

Repins are very similar, the only difference is that we need to provide a board id, where we want to put this pin. So, it looks like we need a board. Let's create one:

{% highlight php %}
<?php
$bot->boards->create('Cats repins', 'Repins from other users');
{% endhighlight %}

Now we can receive its id. Method `info` accepts your username and a board title and returns an array of board data. We can find `id` there.

{% highlight php %}
<?php
$board = $bot->boards->info('my_username', 'Cats repins');
{% endhighlight %}

So, now we are ready to make repins:

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');
$board = $bot->boards->info('my_username', 'Cats repins');

$pins = $bot->pins->search('cats')->toArray();

foreach($pins as $pin) {    
    // put like
    $bot->pins->like($pin['id']);
    // repin to our board
    $bot->pins->repin($pin['id'], $board['id']);
}
{% endhighlight %}

And again at least one line to repin to your board. Very simple!

## Comments

The final step is to make comments. At first, we need some sort of dictionary for our comments. Everybody likes good comments. We are not going to disappoint anyone.

{% highlight php %}
<?php

$comments = ['Nice!', 'Cool!', 'Very beautiful!', 'Amazing!'];
{% endhighlight %}

You can continue this list as you wish, but for this demo, this is enough. We will randomly select a word and write it as a pin comment.

{% highlight php %}
<?php

// ...
foreach($pins as $pin) {    
    // ...
    $comment = $comments[array_rand($comments)];
    $bot->comments->create($pin['id'], $comment);
}
{% endhighlight %}

And here is the final script, that put likes, makes repins and writes comments:

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$comments = ['Nice!', 'Cool!', 'Very beautiful!', 'Amazing!'];

$bot = PinterestBot::create();
$bot->auth->login('mypinterestlogin', 'mypinterestpassword');

$board = $bot->boards->info('my_username', 'Cats repins');

$pins = $bot->pins->search('cats')->toArray();

foreach($pins as $pin) {    
    // put like
    $bot->pins->like($pin['id']);
    // repin to our board
    $bot->pins->repin($pin['id'], $board['id']);
     // write a comment
    $comment = $comments[array_rand($comments)];
    $bot->comments->create($pin['id'], $comment);
}
{% endhighlight %}

Done! Very simple. So much functionality and so little code.

## Summary
We have covered the only bot functionality here, but this script may be improved a lot. For example, we can store in a database our comments, likes and repins history. It will be very useful if you want to look and behave as a real person and not a bot. For example, it looks silly if you write your comment twice on the same pin. Comments history can help us to skip pins that we have already commented. That same is true with likes and repins. There is no need to repin the same pin again and again.

<hr>
You can find examples from this article on [GitHub](https://github.com/seregazhuk/php-pinterest-bot/blob/master/examples/comments_likes_repins.php).

Previous articles:

- [Build Pinterest Bot with PHP: Multiple Accounts and Proxy]({% post_url 2017-03-28-build-printerest-bot-with-php-multiple-accounts %})
- [Build Pinterest Bot with PHP: Automate pinning]({% post_url 2017-03-25-build-pinterest-bot-with-php-auto-pin %})
