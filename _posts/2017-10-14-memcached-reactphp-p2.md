---
title: "Building ReactPHP Memached Client: Serialization, Errors And Connection Handling"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Creating ReactPHP asynchronous Memcached PHP client part 2: serialization and handling the connection"
---

In the [previous article]({% post_url 2017-10-09-memcached-reactphp-p1 %}) we have created a simple streaming Memcached client for ReactPHP ecosystem. It can connect to Memcached server, execute commands and asynchronously return results. In this article we are going to implement some improvements:

- connection handling
- error handling
