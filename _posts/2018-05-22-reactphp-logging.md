---
title: "Asynchronous Logging With ReactPHP"
tags: [PHP, Event-Driven Programming, ReactPHP, Logging]
layout: post
description: "Asynchronous logging in PHP with ReactPHP"
---

## Why to log?

Ok, we have a running application that doesn't have GUI to display its state. And when something bad or interesting happens we want to know about it. The simplest and the most obvious solution is to output some messages somewhere - stdout/stderr, files, syslog or whatever.

Here is the list of the main tasks that can be solved with logging:
- Performance and behavior analysis.
- Analysis and diagnosis of problems.
- Any other scenarios for use.

## How to log?
