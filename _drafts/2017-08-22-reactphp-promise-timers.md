---
title: "Cancelling ReactPHP Promises With Timers"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
description: "Cancelling asynchronous promises with timers with ReactPHP"
---

At first let's refresh in memory what is *Promise*. A promise represents a result of an asynchronous operation. You can add fulfillment and error handlers to a promise object and they will be envoked once this operation has completed or failed. Check [this article]({% post_url 2017-06-16-phpreact-promises %}) to learn more about promises.

Promise is a very powerful tool which allows us to pass around the code eventual results of some deffered operation. But there is one big probleme with promises: they dont give us much control.

> *Sometimes it may take too long for them to be resolved or rejected and we can't wait for it.*


