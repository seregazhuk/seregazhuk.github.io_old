---
title: "Building a RESTful API Using ReactPHP: Basic Authentication"
tags: [PHP, Event-Driven Programming, ReactPHP, API, RESTful API, Basic Authentication]
layout: post
description: "Authenticate ReactPHP RESTful API with Basic HTTP authentication"
---

When it comes to securing our RESTful API things became interesting because a truly RESTful API should remain stateless. It means that the server doesn't store sessions, all the information that the server needs to handle
each request should be contained in the request itself.

## Basic HTTP Authentication

Basic authentication is the most basic type of HTTP authentication, in which login credentials are sent along with the headers of the request.

<div class="row">
    <p class="text-center image col-sm-6 col-sm-offset-3">
        <img src="/assets/images/posts/reactphp-restful-api-authentication/basic-auth.png">
    </p>
</div>

The client requests a protected URL and the server responses with `401 Not Authorized` code. In return the client sends back the same request but with login credentials as a base64-encoded string in formtat `username:password`. This string is being sent via the `Authorization` header as the following:

{% highlight bash %}
Authorization: Basic {base64_encode(username:password)}
{% endhighlight %}

For example, if the username is `user` and password is `secret`, the following header will be sent within the request:

{% highlight bash %}
Authorization: Basic cm9vdDpzZWNyZXQ=
{% endhighlight %}

To enable Basic HTTP Authentication in ReactPHP HTTP server we can use a [PSR-15 middleware](https://github.com/middlewares/http-authentication#basicauthentication){:target="_blank"} for it.

Install it:

{% highlight bash %}
$ composer require middlewares/http-authentication
{% endhighlight %}

This middleware requires any [PSR-7 HTTP library](https://github.com/middlewares/awesome-psr15-middlewares#psr-7-implementations){:target="_blank"}. Let's can use [Guzzle implementation](https://github.com/guzzle/psr7):

{% highlight bash %}
$ composer require guzzlehttp/psr7
{% endhighlight %}

But it still not enough. In ReactPHP we can't use plain PSR-15 middleware with ReactPHP server, instead, we should use [PSR15Middleware adapter](https://github.com/friends-of-reactphp/http-middleware-psr15-adapter){:target="_blank}:

{% highlight bash %}
$ composer require for/http-middleware-psr15-adapter
{% endhighlight %}

Now, we are ready to make our RESTful API secure. Instantiate PSR-15 `BasicAuthentication` middleware and provide credentials:

{% highlight php %}
<?php

// ...

$credentials = ['user' => 'secret'];

$basicAuth = \Middlewares\BasicAuthentication::class, [$credentials]);
{% endhighlight %}

Here we have a middleware that supports only one user. Then we need to wrap it in a special ReactPHP adapter:

{% highlight php %}
<?php

// ...

$credentials = ['user' => 'secret'];

$basicAuth = new PSR15Middleware(
  $loop, 
  \Middlewares\BasicAuthentication::class, [$credentials]
);
{% endhighlight %}

Now, we can use this middleware inside the server. Place `$basicAuth` middleware **before** the router. It is important because if the authentication failed, there is no need to dispatch the route:

{% highlight php %}
<?php

// ...

$credentials = ['user' => 'secret'];

$basicAuth = new PSR15Middleware(
  $loop,
  \Middlewares\BasicAuthentication::class, [$credentials]
);

$server = new Server([$basicAuth, new Router($routes)]);
{% endhighlight %}

Done. From now, to get access to our API the client should provide `Authorization` header. For example, for our credentials it will be the following value:
{% highlight bash %}
Authorization: Basic dXNlcjpzZWNyZXQ=
{% endhighlight %}

If you try to request any route without `Authorization` you will receive `401` response:

<div class="row">
    <p class="text-center image col-sm-10 col-sm-offset-1">
    <img src="/assets/images/posts/reactphp-restful-api-authentication/basic-401.png">
    </p>
</div>

Basic HTTP authentication is probably the quickest and easiest way to add to protect your REST API. It does not require cookies, session, login pages, or any other solutions, and because it uses the HTTP header itself, there’s no need to handshakes or other complex response systems. Looks simple, right? But there are some drawbacks of using HTTP Basic authentication:

- the username and password are sent with every request and thus can be potentially exposed
- expiration of credentials is not trivial


So, this authentication method shouldn't be used on an open network since base64-encoded string can be easily decoded. 

