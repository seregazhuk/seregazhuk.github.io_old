---
title: "Build Pinterest Bot with PHP: Parsing Pins"
tags: [PHP, Pinterest]
layout: post
description: "Build Pinterest Bot with PHP: Parsing Pins"
---

Today we are going to create a simple Pinterest images parser. It will search for pins according to some keywords and then save them to disk. So, let's start. For interacting with Pinterest in PHP we will [Pinterest Bot for PHP](https://github.com/seregazhuk/php-pinterest-bot). 

> *I will skip installation step, it is [well described on the library page](https://github.com/seregazhuk/php-pinterest-bot#installation).*

For example, we need to parse all *cats* images that we can find. Ok. First of all, we need to find pins related to *cats* keyword:

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

$bot = PinterestBot::create();

$pins = $bot->pins->search('cats')->take(100)->toArray();
{% endhighlight %}

In the last line, we have an array of one hundred search results by *cats* keyword. Nice, now we need to save them on disk in the `image` folder. At first, let's *dump* one of these array elements.

{% highlight php %}
<?php 

// ...
$pins = $bot->pins
    ->search('cats')
    ->take(100)
    ->toArray();
print_r($pins[0]);
{% endhighlight %}

Each item in `$pins` array also is a huge array of different pin data:

{% highlight php %}
Array
(
    [domain] => boredpanda.com
    [grid_description] => Princess Aurora - A Photogenic Cat Royalty
    [image_signature] => c30e9bbaef3532e9b5b8964024f25a71
    [done_by_me] => 
    [like_count] => 7
    [images] => Array
        (
            [736x] => Array
                (
                    [url] => https://s-media-cache-ak0.pinimg.com/736x/c3/0e/9b/c30e9bbaef3532e9b5b8964024f25a71.jpg
                    [width] => 736
                    [height] => 736
                )

            [474x] => Array
                (
                    [url] => https://s-media-cache-ak0.pinimg.com/474x/c3/0e/9b/c30e9bbaef3532e9b5b8964024f25a71.jpg
                    [width] => 474
                    [height] => 474
                )

            [orig] => Array
                (
                    [url] => https://s-media-cache-ak0.pinimg.com/originals/c3/0e/9b/c30e9bbaef3532e9b5b8964024f25a71.jpg
                    [width] => 880
                    [height] => 880
                )

            [136x136] => Array
                (
                    [url] => https://s-media-cache-ak0.pinimg.com/136x136/c3/0e/9b/c30e9bbaef3532e9b5b8964024f25a71.jpg
                    [width] => 136
                    [height] => 136
                )

            [236x] => Array
                (
                    [url] => https://s-media-cache-ak0.pinimg.com/236x/c3/0e/9b/c30e9bbaef3532e9b5b8964024f25a71.jpg
                    [width] => 236
                    [height] => 236
                )

        )
    [id] => 541698661416444198
)
{% endhighlight %}

For our needs, we pay attention only to `images` subarray. It has URLs of different image sizes. We are going to use original image size, so to save it we need to get its URL from `$pin['images']['orig']['url']`. And then save it on disk like this:

{% highlight php %}
<?php

$originalUrl = $pin['images']['orig']['url'];
$destination = 'images' . DIRECTORY_SEPARATOR . basename($originalUrl);
file_put_contents($destination, file_get_contents($originalUrl));
{% endhighlight %}

We use `basename()` function to get file name and then `file_put_contents()` to store the original pin image on our disk. So, here is the final version:

{% highlight php %}
<?php

require __DIR__ . '/vendor/autoload.php';

use seregazhuk\PinterestBot\Factories\PinterestBot;

const IMAGES_DIR = 'images';
$bot = PinterestBot::create();

$pins = $bot->pins->search('cats')->take(100)->toArray();
foreach($pins as $pin) {
    $originalUrl = $pin['images']['orig']['url'];
    $destination = IMAGES_DIR . DIRECTORY_SEPARATOR . basename($originalUrl);
    file_put_contents($destination, file_get_contents($originalUrl));
}
{% endhighlight %}

We loop through search results and save every pin in `images` folder. That's it! Very simple.

<hr>

You can find examples from this article on [GitHub](https://github.com/seregazhuk/php-pinterest-bot/blob/master/examples/pins_parser.php).

Previous articles:

- [Build Pinterest Bot with PHP: Multiple Accounts and Proxy]({% post_url 2017-03-28-build-printerest-bot-with-php-multiple-accounts %})
- [Build Pinterest Bot with PHP: Automate pinning]({% post_url 2017-03-25-build-pinterest-bot-with-php-auto-pin %})
- [Build Pinterest Bot with PHP: Comments, Likes And Repins]({% post_url 2017-03-30-build-pinterest-bot-with-php-comments-and-repins %})
- [Build Pinterest Bot With PHP: Followers]({% post_url 2017-04-01-build-pinterest-bit-with-php-followers %})
