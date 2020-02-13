---

title: "Interview with Marc Morera: About DriftPHP"
layout: post
description: "Interview with Marc Morera, an author of DriftPHP. We talk about asynchronous PHP, DriftPHP and ReactPHP."
tags: [PHP, AsyncPHP, Development, ReactPHP, DriftPHP]
image: "/assets/images/posts/php-watcher/thumb.png" 

---

Recently I interviewed [Marc Morera](mmoreram). He is an author of [DriftPHP](https://driftphp.io/) - a new asynchronous non-blocking framework on top of Symfony Components and ReactPHP. We discussed a new framework and how it became possible to make Symfony asynchronous and run it on top of ReactPHP. 

- *Hi everybody and today we're gonna have
an interview with Marc Morera. He is an author of a new framework for asynchronous PHP and he also organizes the first conference about asynchronous PHP which is gonna happen on 26th 27th March in Barcelona. Hi Marc. How are doing?* 

Hello. Nice to be here with you today. Thank you.

- *So what is driftPHP? Why do we need just one more framework in, you know, PHP ecosystem because you know there is even a joke when every senior developer should write own framework just to understand how things work. So, what's the point of drift PHP?*

Well, basically the point here is that there's a small mistake taking consideration that ReactPHP it's the same language that PHP itself. Of course, both are based in PHP. PHP in
space on PHP basically and ReactPHP is just library on top of PHP. But
you know when you don't when you model your domain choosing react PHP or
directly PHP itself without any kind of you know external library support, your
classes your methods are going to be completely different. So from this point
taking in consideration that driftPHP is just another framework it's like a
wrong you know decision the wrong point of view of above about what drift PHP is.
DriftPHP is the first framework on top of ReactPHP. So if you want to model your entire domain if you want to build an application on top of ReactPHP you won't find any other framework on top of that technology. So, yeah it's another
framework on top of PHP but if you want to use ReactPHP there's no like other
frameworks out there. 

- *And under the hood, it is based on ReactPHP and on Symfony components?*  

Exactly. components, I take as well framework bundle that is the bundle perspective of what Symfony is and I take ReactPHP components as well and managing both components mixing them and taking the skeleton architecture of Symfony while it's from my point of view a good thing when you want to build a new framework then you have three. DriftPHP, so it's
basically, if you work with Symfony and you will find that DriftPHP is mostly
the same. You can define services, routing. You can define auto wiring, you can
define whatever you want the same way that you can do in Symfony, but taking
the advantages of working on top of ReactPHP. 

- *How did you come up with this idea? I have read a series of your
articles, where you try to make Symfony kernel asynchronous. You
try to put promises there. Then you build an async kernel and now you have a
framework, right?*

Well basically the first sight of of that component of a that idea it came like I've been using Symfony for the last eight years or nine or seven I don't even remember how how it started. But I am very used to working on top of Symfony components and the Symfony skeleton. I mean the best the best applications in my life have been built on top of Symfony so I started some months ago working on top of ReactPHP components as well by doing some stuff with PHP-PM and by using PHP-PM I understood what ReactPHP was. So from that point of view, I decided that I wanted to make my domain as well on top of ReactPHP. So, you have a server that is on top of ReactPHP, you have a domain that is on top of ReactPHP and if you understand a little bit how ReactPHP works how the event loop works, how promises can be used then it's going to be very easy for you to just think that
if you want the whole application work on top of react PHP you need all that
small layers to be working with promises that means that Symfony kernel stops in
the wrong way. The promises that are generated from the domain layer for
example, making a Redis call... that promises are are stopped in the kernel
so you can't take that promise and put it on the server on the event loop in the
server and start making non-blocking calls. So, at this point I decided that
the first step was thinking about how to change the control of Symfony. Not
changing the kernel itself but allowing the handled method inside the kernel and
the HTTP kernel to allow only not only returning responses but allowing as well returning promises of responses. So what I started with that proof of concept that it was just cloning the kernel of Symfony and adding some small some small refactorings inside the
kernel. Then I had to refactor a little bit how the event, the event dispatcher works because you are going to work as well with promises instead of returning values and that's it. So at the entry point you handle a request and you can expect a promise of response and on the other side the controller inside Symfony application... it... it's... it's not longer necessary and required to return a response anymore. But you can return
that promise generated in your domain layer or infrastructure. So, at this point, you have three layers all three layers are built on top of ReactPHP promises. So, it means that you can have a whole ReactPHP application. So, it started as a proof of concept. I had... you know... I worked so hard to make... to make sure that the proof of concept could be a small. You know... not a component of inside Symfony environment but a point of starting point of working on that direction. Symfony community was not for my... you
know... for my perspective and about what I saw was not much interested in that field. So, I decided that for all that PHP people working on top of Symfony was
very interesting to have that alternative to jump to React... and ReactPHP framework called DriftPHP in a very easy way. 

- *So, it is not only about the code here inside your application you have also now to think that your application now becomes the server. There is no Nginx no Apache, your
application is the server and you should keep it in mind.*

Exactly. It can be Nginx can be an Apache. I'm not sure how... how it should work right now. But what you have here is that one of the components of DriftPHP it's a server itself. So, you don't need any more that process manager that manages your several workers
because inside this worker it request is blocking one. You don't need it
anymore because your code is not going to be blocking any more. So, you can
have like thousands of requests at the same time working with one's little
server and if the code is properly designed all the requests at the same
time are going to work properly with the concurrency. So, with one server you can like have tons of... of thousands of requests at the same time and no one is
going to block anyone.

- *And what was the idea? What does drift mean? If it is not a secret.*

No, no it's not a secret. It means absolutely nothing. You know, if you go to all the projects I've built in my life, I need to admit that for naming I'm not that bad. But if you go to find a name, the reason, the rationality behind its name of my life you will find nothing. I mean what means all the naming in software architecture world but nothing. So DriftPHP... yeah I thought something very fast something, you know like making some drifting maybe. It has the origins on... you know or need for speed maybe... I
don't know. But that's not important you know. Yeah, it could be named like other
ways. 

- *So, you sit down... how it should be titled? Drift! Oh yeah, let's write down!* 

Exactly! Literally! I mean yeah. 

- *So, we can consider it as a non-blocking Symfony, event-driven Symfony on top of ReactPHP. It is also a built-in server. It can be used without Apache on Nginx
And what are the common use cases? Why I should use Drift and I can't use for example traditional synchronous Symfony?*

Of course the idea of using DriftPHP because you can't really
use another framework it's a bad idea. I think that most of applications nowadays
are having the servers we have, and having the language we have with PHP
7. I think the most applications can be done with any kind of framework if
you know how to model good architecture. But what this framework tries to do here
is to put an alternative on terms of performance. So, for example if you want
to build an e-commerce on top of PHP you can take Sylius for example. You can
take natural PHP any kind of framework. you can you can take Symfony itself to
building that small components that cart or whatever. But you can also take a
framework like DriftPHP and of course all your models is going to be absolutely
the same instead of returning values you will you will take an account that you
will start returning promises what really makes the software non-blocking. It's sort of waiting for the results. You're going to return a promise and you will put that promise in the event loop. This how works other languages for example golang. In another way of goroutines or whatever or you know for example nodejs. So, yeah, for example in e-commerce or a small application with small micro services you know and apress for example it's so easy to work that way you can work with PHPUnit as well. You can test what you will have here is that there's some pieces you know small
components small libraries on inside the PHP environment that is going to be a
bit difficult for you if you want to work with the way that on top of ReactPHP.
When I say difficult I'm saying quite impossible. 

- *So, okay. If I want to write something performant in PHP I should consider
some let's call them pitfalls yes? Some things that will be... that will come with a lot of pain or it will be  even impossible to do async. And what are they?*

Yeah, for example you cannot make a `file_get_contents()` what seems something you know... Obvious? Yeah! I mean of course... it's a... it's a... in terms a function from PHP what. Okay, I should be able to do that in my PHP code. But what you should take in consideration here is that when you make a `file_get_contents()` from the moment you call that function until the moment you take the result the PHP threat is completely blocking. Because you have to consider file system as infrastructure layer as well. So, if you have a unique server with thousands of connections there even we can start talking for example with WebSockets you can have dozens of thousands of WebSockets connected in that thread. What happens is you if you make a `file_get_contents()` during that small time? The whole code there whole web sockets the whole connections HTTP connections are going to be blocked so developed is going to be blocked so that means that for example functions like
`file_get_contents()` of if you go to Doctrine for example all the calls in MySQL or you know SQLite both all that queries are blocking so that means that you cannot use Doctrine on top of ReactPHP you know that's not not good news but from the other perspective we can build something like doctrine on top of ReactPHP and we have will going to have at
that point DBAL or ORM not blocking what it's good news from that perspective of
ReactPHP.

-*If I think about DriftPHP as a Symfony on top of ReactPHP... but actually I can't just get any component I like, pull it into my project and use it. I should consider what's inside here yeah?*

Well, you can have some component actually you can have all components on top of Symfony
that don't require really you know... non-blocking... non-blocking actions like
`file_get_contents()` or whatever. In fact if you have a `file_get_contents()` action
inside a Symfony component for example for acquiring a Twig... a Twig template
they have a `file_get_contents()`. You can manage how to make that `file_get_contents()` before the server is actually working and starting listening to the WebSocket. So you can do that in fact DriftPHP has a Twig adapter for working with Twig that basically what it does is make all that `file_get_contents()` before the request is handled. So if you want to make something blocking you have to make it at the beginning before the first request so as soon as the request is handled by the kernel then the component itself is
not going to have to make that... that... you know in out operations anymore. So
everything is going to be allocated in memory. 

-*How it works under the hood? Yeah... we... the the application starts yeah this bootstrapping process executes and then it just keeps alive, yeah? we have a running kernel and it runs asynchronously. So, we can think about it as PHP-PM which reduces
this bootstrap in time and on top of it we also have performance, non-blocking asynchronous kernel which keeps running and handles all requests. Well, that's
quite different between that. There's a big difference between PHP-PM and
Drift. PHP-PM is just a collection of
of Symfony workers that... between
requests... the same kernel is reused once

0:17:14.180,0:17:18.530
and again. Okay? So, Drift is not about reducing the

0:17:18.530,0:17:24.410
bootstrapping time? You are
actually reusing the same kernel across

0:17:24.410,0:17:30.470
all the requests, but it's not
only about that. Because in PHP-PM

0:17:30.470,0:17:35.240
the kernel that exists, it's Symfony a standard one. So what you

0:17:35.240,0:17:40.940
will have in the PHP-PM is that is
actually blocking. You have to make one

0:17:40.940,0:17:46.520
request after the other one. Okay.
So if you have... you know... enough requests

0:17:46.520,0:17:51.770
at the same time long request at the
beginning short requests later. Short

0:17:51.770,0:17:56.060
requests are going to be stopped until
the first longer requests are completely

0:17:56.060,0:18:00.890
fulfilled. So this not
going to happen anymore with Drift

0:18:00.890,0:18:06.730
because it's not only about reusing the
same kernel but of making

0:18:06.730,0:18:14.300
asynchronous and unlocking requests.
So you are taking advantage of

0:18:14.300,0:18:21.770
both things at the same time. And okay... if
it is written on top of Symfony, right?

0:18:21.770,0:18:28.970
And let's imagine that I already have a
Symfony application yeah... it is running

0:18:28.970,0:18:34.610
on the latest version of Symfony. It is
running on the latest version of PHP it
