---
title: "Fast Web Scraping With ReactPHP. Part 3: Using Proxy"
tags: [PHP, Event-Driven Programming, ReactPHP, Proxy]
layout: post
description: "Using proxy for fast anonymous web scraping with ReactPHP"
image: "/assets/images/posts/fast-webscraping-reactphp-throttling/throttling-simpsons.jpg"

---

In the [previous article] we have improved our web scraper and added some throttling. We have used a simple in-memory queue to avoid sending hundreds or thousands of concurrent requests and thus to avoid being blocked. But what if you are already blocked? The site that you are scraping has already added your IP to its blacklist and you don't know whether it is temporal block or a permanent one. 

You can handle it using proxy. Using proxies and rotating IP addresses can prevent you from being detected as a scraper. The idea of rotating different IP addresses while scraping - is to make your scraper look like *real* users accessing the website from different multiple locations. If you implement it right, you drastically reduce the chances of being blocked.

In this article I will show you how to send concurrent HTTP requests with ReactPHP using a proxy.

First of all, to start using proxy we need to install [clue/reactphp-socks](https://github.com/clue/reactphp-socks) package:

{% highlight bash %}
composer require clue/socks-react
{% endhighlight %}

