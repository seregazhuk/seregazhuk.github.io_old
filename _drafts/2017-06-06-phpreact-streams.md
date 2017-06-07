---
title: "Event-Driven PHP with ReactPHP: Streams"
tags: [PHP, Event-Driven Programming, ReactPHP]
layout: post
---

# Streams

Every stream at a low level is simply an `EventEmitter`, which implements some special methods. Depending on these methods the stream becomes *Readable*, *Writable* or *Duplex* (both readable and writable). Readable streams allow to read data from a source, while writable can be used to write some data to a destination.

## Readable Stream


## Events

Every stream class extends `EventEmitter` class, which consists of one trait `EventEmitterTrait`:

{% highlight php %}
<?php

namespace Evenement;

class EventEmitter implements EventEmitterInterface
{
    use EventEmitterTrait;
}
{% endhighlight %}

`EventEmitterTrait` implements basic methods to fire events and subscribe to them:

- `on($event, callable $listener)` subscribes a listener to the specified event. When event occurs a listener will be triggered. Adds listener to the end of the listeners array, there are no checks if this listener already has been added.
- `once($event, callable $listener)` adds a one-time listener to the event. The listener will be invoked only once the next time the event is fired, after that it is removed. It is a wrapper over the `on` method. It wraps a specified listener into the closure, which when is invoked it at first removes the listener from the subscribers and then invokes this listener.
- `emit($event, array $arguments = [])` fires an event. All listeners that are subscribed to this event will be invoked. `$arguments` array will be passed as an argument to every listener.
- `listeners($event)` returns an array of listeners for the specified event.
- `removeListener($event, callable $listener)` removes a listener from the array of listeners for the specified event.
- `removeAllListeners($event = null)` removes listeners of the specified event. If `$event` is `null` removes all listeners.
