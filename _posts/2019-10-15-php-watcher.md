---

title: "Live Reloading PHP Applications With Nodemon"
layout: post
description: "How to reload your PHP application when changing its source code"
tags: [PHP, ReactPHP, Development]
image: "/assets/images/posts/live-reload-php-with-nodemon/logo-cool.jpg" 

---

I like PHP for it's simplicity: you code something, refresh the page in the browser and you can see your changes. There is no need to wait, to compile something, just refresh the page and you are ready to go. But, PHP is not only about request-response. Sometimes we create some CLI tools and some of these tools are long-living processes. For example, we create an asynchronous HTTP server for uploading files on server. This server should be always running and listening for incoming connections. The development process of a long-running command in PHP can become really painful. In the terminal we cannot "refresh the page" to see the changes. Each time we make changes in the source code, we have to manually interrupt our command and restart it. Being a developer I always strive to optimize my workflow. In PHP I haven't found any appropriate solution, so I have chosen [nodemon](https://github.com/remy/nodemon). It is a tool from NodeJS ecosystem and was built to help in developing NodeJs applications. But with some tweaks it can be easily used with PHP script. Sometimes it is OK, and sometimes we don't want to install NodeJS and NPM in order to just restart my app. Actually, I don't want to have a bunch of unknown npm packages which are totally redundant for a PHP asynchronous application. So, I have decided to create my own solution which can be used in PHP ecosystem. I have announced it on Twitter and many people liked the idea of having such a tool, that is available via Composer.

<blockquote class="twitter-tweet" data-lang="en"><p lang="en" dir="ltr">Finally, it is ready! PHP-Watcher - a package to automatically restart PHP application once the source code changes. Can be very useful for developing long-running PHP applications 🎉👍<a href="https://t.co/wIeuJejDvq">https://t.co/wIeuJejDvq</a></p>&mdash; Sergey Zhuk (@zhukserega) <a href="https://twitter.com/zhukserega/status/1179722274414436352?ref_src=twsrc%5Etfw">October 3, 2019</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script> 

PHP-watcher

What is PHP-watcher? Consider it as nodemon but for PHP. It helps develop long-running PHP applications by automatically restarting them when file changes are detected.

Here's how it looks like:

<img src="/assets/images/posts/PHP-watcher/demo.svg?sanitize=true" alt="watcher screenshot" style="max-width:100%;">
