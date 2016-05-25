---

title: "Laravel: Jobs vs Events"
layout: post
tags: [Laravel]
comments: true

---

When I arrived at this topics I was a little bit confused about the difference between jobs 
and events, because they look very similar. Both of them help us to make our controllers very
thin and easily understandable. So when should I choose an event and when it is 
appropriate for a job? 

## Events

Let's look at Laravel's documentation: *Laravel's events provide a simple observer implementation, 
allowing you to subscribe and listen for events in your application.* What does it mean? 

Events usually describe things, that have occurred in the past. For example: 

- *UserRegistered*
- *ArticlePublished*

Events are picked up by listeners. When the event is fired all the listeners that are registered for that
particular event are run and receive an event object with its data. The event system may be compared with
the way how exceptions work. For example, you throw an exception, and you can define several catch blocks to react on it.
In the case of events and listeners, an event is fired (thrown) and one or more listeners (catch blocks)
react on it.

Events usually **start and finish during the request lifecycle and cause something that needs to
be handled**.

An event could trigger a listener and register a job. For example, a *UserRegistered* event is fired,
a listener picks up an event to send an email, and a job is queued.

## Jobs

A job is something that you want to happen (need to be done). For example:

- *UpdateOrder* 
- *PublishArticle* 

**Jobs are often tasks that work behind the scene (in the background)**. The may be executed during the request as 
a synchronous task or you can put them into a queue to respond the request as soon as possible. Then the queue 
handles the execution of all pending jobs (send an email, create a backup). They are executed according to the 
order they arrived or other scheduling requirements.

## Conclusion

You may consider jobs as something that **needs** to be executed in order for your application to work properly. Events 
are mostly **side effects/results** of those actions - while important and useful, they play a secondary role.

