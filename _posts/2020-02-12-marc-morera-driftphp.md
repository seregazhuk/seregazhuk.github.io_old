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
the HTTP kernel to allow only not only returning responses but allowing as well returning promises of responses. So what I started with that proof of concept that it was just cloning the kernel of Symfony and adding some small some small refactorings inside the kernel. Then I had to refactor a little bit how the event, the event dispatcher works because you are going to work as well with promises instead of returning values and that's it. So at the entry point you handle a request and you can expect a promise of response and on the other side the controller inside Symfony application... it... it's... it's not longer necessary and required to return a response anymore. But you can return
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

Well, you can have some component actually you can have all components on top of Symfony that don't require really you know... non-blocking... non-blocking actions like `file_get_contents()` or whatever. In fact if you have a `file_get_contents()` action inside a Symfony component for example for acquiring a Twig... a Twig template they have a `file_get_contents()`. You can manage how to make that `file_get_contents()` before the server is actually working and starting listening to the WebSocket. So you can do that in fact DriftPHP has a Twig adapter for working with Twig that basically what it does is make all that `file_get_contents()` before the request is handled. So if you want to make something blocking you have to make it at the beginning before the first request so as soon as the request is handled by the kernel then the component itself is
not going to have to make that... that... you know in out operations anymore. So
everything is going to be allocated in memory. 

-*How it works under the hood? Yeah... we... the the application starts yeah this bootstrapping process executes and then it just keeps alive, yeah? we have a running kernel and it runs asynchronously. So, we can think about it as PHP-PM which reduces this bootstrap in time and on top of it we also have performant, non-blocking asynchronous kernel which keeps running and handles all requests.*

Well, that's quite different between that. There's a big difference between PHP-PM and Drift. PHP-PM is just a collection of of Symfony workers that... between requests... the same kernel is reused once and again. 

- *So, Drift is not about reducing the bootstrapping time?*

You are actually reusing the same kernel across all the requests, but it's not
only about that. Because in PHP-PM the kernel that exists, it's Symfony a standard one. So what you will have in the PHP-PM is that is
actually blocking. You have to make one request after the other one. Okay. So if you have... you know... enough requests at the same time long request at the beginning short requests later. Short requests are going to be stopped until
the first longer requests are completely fulfilled. So this not going to happen anymore with Drift because it's not only about reusing the same kernel but of making asynchronous and unlocking requests. So you are taking advantage of
both things at the same time. 

- *If it is written on top of Symfony, right? And let's imagine that I already have a Symfony application yeah... it is running on the latest version of Symfony. It is running on the latest version of PHP it has Opcache but I have a leak of performance. I want to improve. Will it be hard to migrate from Symfony to Drift? Because under the hood it is actually the same year? There we have Symfony and there Symfony. *

Yeah it's not good to be difficult in fact there is one application that I
could be... it could be considered as first application on top of DriftPHP. It was actually built on top of ReactPHP before DriftPHP was actually started. That
is Api Search. It was built on top of Symfony. Basically was Symfony. And I started translating it from Symfony PHP, regular PHP to ReactPHP and is actually on top of DriftPHP already. So it was hard to make something
like that not actually basically because I had a strong layer of functional
testing there. So it was easy for me to to be sure that the refactoring was already done and properly done. But at the same time because you know
changing from Symfony to DriftPHP is like changing from Symfony to Laravel
even a bit more harder. If you think about that you will have to change your
classes. You will not have to change the logic of your classes but the way it
works. The classes are not going to expect to return the result but a promise of the result. So you are going to change maybe some part of the controller, some part of the commands but you know for example the configuration layer of your service here are going to be exactly the same you will not have to change the routing there. You will not have to change the way you sort your classes you organize your classes inside at the end it's going to be exactly the same unless you work for example with Doctrine. In that case that's not the best moment for
changing to DriftPHP. To be honest and to be you know... for me it's important
to say that DriftPHP is not the production-ready framework. DriftPHP is two or three months old. So if DriftPHP and for me at the same time the ReactPHP community needs some time to make that growth to start building some external and you know third-party libraries like for example DBAL or the ORM. But for me, DriftPHP is just the beginning of something like that. You know we already have a MySQL adapter, Postgres adapter, you have Redis adapter, RabbitMQ adapter you have a Twig adapter, you have a server, you have an HTTP kernel, you have I don't know an adapter for preloading all the things
before the framework request is served so we already have a lot of things but they are not enough to make something for production-ready. So we are on that direction and that means that the more time we have to work in that direction
the better performance we're going to have. The better libraries we are going to work with and of course the more people we have the better and the sooner
we are going to to be able to work with production-ready framework. For example,
for me it has no.. it has... it's not good to have a production-ready framework if you don't have a library that works with... you know with any kind of metrics third-party service for example New Relic. If you can't go with New Relic then a lot of people enough companies are going to... are not going to be ready for DriftPHP. So that's a direction we have to take if you want a project ready for production. 

- *So you can try but be ready...* 

Yes, absolutely. I think it's important to start digging in how ReactPHP works and for me it's the most important thing what are the advantages of working ReactPHP instead of PHP. And you will find that yeah absolutely maybe it's not that it's not now the moment to work with that on production. But what if in the future the community was large enough to make something really good in terms of performance it could be in a new stage for PHP community. For the whole PHP community. Not only for Symfony users. 

- *You also should think that when you migrate yes that you don't have an application in the request-response cycle yeah. You should monitor I don't know... supervisor use... or something else that your application is currently running that it doesn't fail and you know.*

Well not the server itself it should take care of... to be sure that all requests... it's time the request fails okay, it should fail inside the scope of the request itself. Not just shutting down the server so the server should be
always running even if requests are failing. That should be the direction of
the design of the server itself. So you should not have to need any supervisor.
Basically because if you put the supervisor... the docker... inside docker,
the entry point is not going to be the server itself what it makes... what it's
really important it's going to be the supervisor. So what can happen here is that you have the supervisor running and all workers just stopped. It's the same
problem that you can have with PHP-PM. So, for me the thing that the server itself and the code itself and the thread that is actually handling your
requests is the entry point of docker. So if you have... if you are working with Amazon Web Services, Amazon load balancer is going to take care that you always
have like what... end

0:25:01.909,0:25:06.750
versions of the server running at the
same time with a load balancing. In

0:25:06.750,0:25:10.649
that case the load balancing because all
requests are done blocking can work

0:25:10.649,0:25:16.409
directly with round-robin instead of
marking it's working if it's busy or or

0:25:16.409,0:25:22.740
it's not busy. So you should keep an eye not on the server itself but on the

0:25:22.740,0:25:29.990
container? Exactly!
But the container the thread

0:25:29.990,0:25:34.490
number one in container so the entry
point is the server itself so when the

0:25:34.490,0:25:40.250
server breaks the container is going to
break what it is important in terms of

0:25:40.250,0:25:48.890
docker yes. And you mentioned that when
you moved in your application

0:25:48.890,0:25:54.890
ApiSearch from Symfony to Drift you
had a lot of tests right? yeah. Functional

0:25:54.890,0:26:00.290
tests. And okay if we consider a request
response cycle it is obvious... but how

0:26:00.290,0:26:06.530
do you test your application which is
always running? Does it differ from

0:26:06.530,0:26:12.940
traditional testing? Or it is the same? It is the same if you test

0:26:12.940,0:26:19.010
if you test any component that instead
of returning you the value itself it

0:26:19.010,0:26:24.790
returns you a promise. There is a library
on top of ReactPHP that is actually

0:26:24.790,0:26:31.850
built by one of the of the people that
has already doing ReactPHP from the

0:26:31.850,0:26:38.660
scratch. That is Christian Luck. There is
a library that it's called "block". So what

0:26:38.660,0:26:44.240
you can do if you have a promise you can
just wait for the result of the promise

0:26:44.240,0:26:49.340
and that's it. Then you can
assert with the content of the promise

0:26:49.340,0:26:56.720
itself. So it's basically the same.
But you need to have that layer of

0:26:56.720,0:27:02.690
waiting for the promise response. So you
just move from values to promises and

0:27:02.690,0:27:07.880
you assert that that promise resolves
with something or the problem or the

0:27:07.880,0:27:12.980
promise rejects and you check the
exception. Exactly, if you wait

0:27:12.980,0:27:17.390
for a promise and the promise reject
then the exception is going to be thrown

0:27:17.390,0:27:25.160
so it's exactly the same that you
write in a PHPUnit. If you work with... on

0:27:25.160,0:27:34.300
top of PHP. Yeah. Okay, let's say that... you
know... I am brave enough to write my new

0:27:34.300,0:27:40.679
application in Drift. It's okay for me to throw away

0:27:40.679,0:27:46.710
Doctrine, to write these low-level queries
by myself. Where should I go?

0:27:46.710,0:27:51.200
How should I know how to use it? how to
write it?

0:27:51.200,0:27:55.620
Well, if you want to build your
application of top of DriftPHP right now

0:27:55.620,0:28:02.210
I wouldn't say you're brave. I wouldn't
say you don't have thought it so much.

0:28:02.210,0:28:08.580
For me writing DriftPHP
applications today for production... I

0:28:08.580,0:28:14.790
won't say no but if you want to dig it...
to dig in a little bit on that framework

0:28:14.790,0:28:21.450
I would say that driftphp.io it's a
documentation itself for the project and

0:28:21.450,0:28:27.900
you can go to github.com/driftphp
and you will find a demo there. That demo

0:28:27.900,0:28:33.840
was already built on Drif PHP skeleton
and you you can find a small application

0:28:33.840,0:28:38.580
that is actually doing absolutely
nothing but saving some values in Redis

0:28:38.580,0:28:44.520
and making some Twig... some Twig work
and you will find that instead of having

0:28:44.520,0:28:49.680
three four five ten milliseconds
returning requests you will find

0:28:49.680,0:28:55.380
requests that I are actually working in
less than 300 microseconds in a

0:28:55.380,0:29:01.040
non-blocking way. You will find that in
terms of performance DriftPHP it's

0:29:01.049,0:29:07.650
much more faster than any PHP framework
nowadays. But if you go with concurrency

0:29:07.650,0:29:12.840
you will find that the cover of growing
of that concurrency it stops maybe on

0:29:12.840,0:29:17.640
ten milliseconds even if you go with
1,000 concurrent requests at the

0:29:17.640,0:29:22.620
same time the 50% of your requests are
going to be returned it in 10

0:29:22.620,0:29:27.570
milliseconds. If you go with that on any
other framework like Symfony you will

0:29:27.570,0:29:32.160
have... if it works in your computer... you
will have like tons of hundreds of

0:29:32.160,0:29:39.750
milliseconds or maybe one second per
request. Or that's amazing! This demo

0:29:39.750,0:29:45.390
application, it is just a "hello world"
blank page? Or it is something... it is

0:29:45.390,0:29:50.920
CRUD? No, you can manage some
key value values there

0:29:50.920,0:29:58.090
by using a adapter for Redis. That is
actually tested as far as I remember. You

0:29:58.090,0:30:04.960
will find there some testing stuff. Well
I'm not sure about what I'm saying I'm

0:30:04.960,0:30:08.560
going to review it later. If it doesn't
exist I'm going to add some testing

0:30:08.560,0:30:14.140
there to make sure that people
understand how to test promises. But you

0:30:14.140,0:30:20.650
will find that you can really call some
endpoints to make sure that you can add

0:30:20.650,0:30:25.120
some value in. Having a key you can
request and you can list all values and

0:30:25.120,0:30:29.110
query and keys at the same time. And you
can delete it. Like a small rest

0:30:29.110,0:30:36.370
application there. And you can use some
Twig in order to show that even if you

0:30:36.370,0:30:41.110
would use Twig performance is not
 decreasing because

0:30:41.110,0:30:47.050
using of Twig. Everything is already
loaded into memory? Yeah, exactly! There is

0:30:47.050,0:30:51.580
one component on top of DriftPHP that is called preloading. That what

0:30:51.580,0:30:58.600
it takes is it asks to all components
that actually are loaded inside of PHP

0:30:58.600,0:31:06.250
and it requests them ok preload your
services preload your classes preload

0:31:06.250,0:31:13.120
everything you have to promote before
the first request is handed. So it's not

0:31:13.120,0:31:18.040
because of the first request that I would
like to make it as fast as possible but

0:31:18.040,0:31:23.710
what happens if you promote that service
on your load balancer and at the same

0:31:23.710,0:31:29.470
time you have 10,000 WebSockets
connection you don't want 10 thousand

0:31:29.470,0:31:34.980
connections at the same time building
the same service once and again.

0:31:34.980,0:31:40.799
So the service is going to be already
built-in. And having you know this sort

0:31:40.799,0:31:46.669
of architecture now on top of Symfony
on top of this asynchronous kernel

0:31:46.669,0:31:54.509
running this you know very performant...
what currently is the slowest part here?

0:31:54.509,0:31:59.039
in this request-response cycle... what is
the slow part? do you think it can be even

0:31:59.039,0:32:03.629
more improved?
There is no slow part here. The slow part

0:32:03.629,0:32:08.279
the slow part here into your application
it's going to be the bit peak of it

0:32:08.279,0:32:14.369
itself. The more PHP code you have of
course PHP is not... it's not asynchronous

0:32:14.369,0:32:21.690
as language. You will always do one thing
before the others don't. So what you have

0:32:21.690,0:32:25.980
to think here is that the hard
consumption here is going to be about

0:32:25.980,0:32:32.190
CPU. Of course in memory as well but you
can read the maximum memory and then if

0:32:32.190,0:32:37.859
you have a good policy of of you know
growing and killing servers you're going

0:32:37.859,0:32:43.440
to be able to do that to solve that on
top of you know on top of Amazon Web

0:32:43.440,0:32:49.710
Services but what you have to think here
is that tons of thousands of requests

0:32:49.710,0:32:55.739
are going to use the same CPU. One CPU, there is one threat on PHP. So you

0:32:55.739,0:33:01.649
don't have that Nginx or Apache or
you know or PHP-PM that it's actually

0:33:01.649,0:33:05.429
multi-threading so you are using at
the same time several CPUs at the same

0:33:05.429,0:33:12.600
time. You will not have something like
that. You will have one CPU just at the

0:33:12.600,0:33:18.779
same time across all the things that are
happening there: your web sockets,

0:33:18.779,0:33:27.629
whatever. So it's time you make a long
code of PHP that you cannot you are

0:33:27.629,0:33:34.049
doing it at one time during that time
the thread of PHP is going to be blocked

0:33:34.049,0:33:41.970
by everyone. But you know that time is
that is small that is that it's well you

0:33:41.970,0:33:46.590
cannot go for... you cannot do better than
that.

0:33:46.590,0:33:53.150
In fact so this is why in fact in DriftPHP
or ReactPHP when you talk about

0:33:53.150,0:33:59.820
commands and queries, CQRS and you
take that writes asynchronous you

0:33:59.820,0:34:03.659
should not consider anymore to make your
writes asynchronous because writes are

0:34:03.659,0:34:10.139
not-blocking as well so there's no need
to make queues with writes there. But I

0:34:10.139,0:34:16.349
would consider to make that writes not
because of writes... I consider to take

0:34:16.349,0:34:20.399
that commands to make it asynchronous
not because of writes but because the

0:34:20.399,0:34:26.909
amount of time of CPU usage. I will
consider that. The part that can be slow

0:34:26.909,0:34:32.790
and I think that in the future is going
to be a good thing to try to increase

0:34:32.790,0:34:38.129
the performance is event loop. Take into
consideration that event loop is a

0:34:38.129,0:34:43.740
class that is actually making ticks...
ticks... ticks... ticks... until the

0:34:43.740,0:34:49.200
end of time. So the more the faster that
ticks are done the better the

0:34:49.200,0:34:54.359
performance is going to be across all
the actions that is actually done inside

0:34:54.359,0:35:03.830
the server. We are talking here about you
know hard computations, right? When CPU is

0:35:03.830,0:35:12.390
utilized and how can I fix I can run you
know if I have four CPUs I can just run

0:35:12.390,0:35:18.480
four containers and they will... Exactly.
Literally. Yeah, you can have four

0:35:18.480,0:35:23.760
containers inside that server and then
having a load balancer that just

0:35:23.760,0:35:29.700
balancing between for the good thing if
you have for example four containers you

0:35:29.700,0:35:33.660
will be able to run the four containers
at the same time using 4 CPUs because

0:35:33.660,0:35:42.490
at the end you know it's like it's going
to be multi-threading right?

0:35:42.490,0:35:48.190
It will be a multi-threaded asynchronous
PHP. Yeah, but using 4 threads of

0:35:48.190,0:35:53.350
PHP, PHP is not multi-threaded but
it's the way that you can't really use

0:35:53.350,0:36:02.980
the 100% of resources of your server. But
now we only talked about request

0:36:02.980,0:36:10.570
response cycle yeah and can I?... and we talked about HTTP Kernel asynchronous.

0:36:10.570,0:36:14.410
Can I write you know... asynchronous
long-running

0:36:14.410,0:36:21.100
console commands in DriftPHP? Yeah, you
can do that. I mean for me console

0:36:21.100,0:36:26.110
things it's not very important to be asynchronous on terms of request

0:36:26.110,0:36:33.250
response on terms of application I will
consider making a console on top of

0:36:33.250,0:36:42.610
ReactPHP to be sure that all the
actions, all the in/out operations inside

0:36:42.610,0:36:47.859
my infrastructure layer are done at the
same time and in the concurrent way. You

0:36:47.859,0:36:55.510
know things are much more easy if you go
with a console instead of a service. So

0:36:55.510,0:37:01.720
yeah absolutely. I mean if you go at the
end console in Symfony it's actually

0:37:01.720,0:37:07.619
using the same kernel that is using the
server, the web server.

0:37:07.790,0:37:13.730
We have a talk about
how to work with console components on

0:37:13.730,0:37:17.720
top of ReactPHP in the conference. So I
think it's going to be interesting to

0:37:17.720,0:37:26.510
see how Michael actually works with
console. Okay that was interesting I

0:37:26.510,0:37:33.050
don't have any questions so hope that
the talk was interesting to others and I

0:37:33.050,0:37:40.670
think the main point is that PHP can be
asynchronous right? That in community

0:37:40.670,0:37:45.410
you have tools to write asynchronous
code. You have this low-level ReactPHP

0:37:45.410,0:37:51.280
components. Now you even have a framework where you don't have to think about

0:37:51.280,0:37:58.670
event loop, about this low-level stuff.
You just have these components you

0:37:58.670,0:38:04.660
Just think that you are now using
non-blocking code, you can't wait. Exactly.

0:38:04.660,0:38:10.670
Exactly. So if people that is
actually listening to that podcast needs

0:38:10.670,0:38:17.119
more information about what we are
talking about we are working so hard to

0:38:17.119,0:38:25.340
make a small list of created you know...
resources for ReactPHP not for DriftPHP

0:38:25.340,0:38:31.670
but for ReactPHP that for me it's the
reason that we are already today here. To

0:38:31.670,0:38:37.730
understand more how ReactPHP works. So
if you go to the DriftPHP repository on

0:38:37.730,0:38:42.020
github you will find the also ReactPHP
list there and at some time

0:38:42.020,0:38:47.510
you can find like different groups of
slack and gitter that some communities

0:38:47.510,0:38:51.800
small communities actually are working
on top of DriftPHP and ReactPHP in

0:38:51.800,0:38:56.170
different channels. So I'm sure that
people is going to be interested and

0:38:56.170,0:39:02.869
should take that consideration that in
terms of DriftPHP maybe for next year

0:39:02.869,0:39:07.040
we will have something really really
really really really good for production.

0:39:07.040,0:39:14.430
And we are taking that work because we
want to make it happen for sure.

0:39:14.430,0:39:22.170
You know a just one last question I have.
Why ReactPHP, right? I know that you

0:39:22.170,0:39:26.460
have chosen Symfony because you have to
work with Symfony for a lot of years.

0:39:26.460,0:39:34.470
But currently in async PHP community we
have several tools we have ReactPHP, we have

0:39:34.470,0:39:42.390
AMP and we have Swoole which is sort of
something different. Why ReactPHP and

0:39:42.390,0:39:51.839
not others? I would say because the same
I would give you... and... and it doesn't say

0:39:51.839,0:39:59.130
much more from me to be honest I would
say the same response that I told you

0:39:59.130,0:40:08.579
with the name of the project. Why not?
I mean I started digging with ReactPHP

0:40:08.579,0:40:15.329
because PHP-PM and I was very
impressed about all the components that

0:40:15.329,0:40:22.920
are actually built on top of PHP without
the need of any PHP extension and for me

0:40:22.920,0:40:27.900
it's very good because you can start
from the scratch doing anything and it's

0:40:27.900,0:40:33.809
you know... it's more for me it's so
comprehensive how you are doing what you

0:40:33.809,0:40:39.420
are doing on top of PHP knowing that
they are only libraries I'm sure that

0:40:39.420,0:40:46.859
Swoole and Amp are good libraries as well. And that you can take a good performance

0:40:46.859,0:40:53.779
numbers by using that libraries. But you
know maybe some other guy on in

0:40:53.779,0:40:57.750
another part of the world is going to
have the same idea but instead of ReactPHP

0:40:57.750,0:41:03.029
use Swoole and yeah if you ask him or her the same

0:41:03.029,0:41:08.190
question the response is going to be
exactly the same. What's totally fair

0:41:08.190,0:41:13.769
because I think that we don't have time
enough to start digging across you know

0:41:13.769,0:41:19.529
checking all the possibilities of
everything because you know... you know my

0:41:19.529,0:41:24.210
life and you know... it's important as well
and if I want to take care about

0:41:24.210,0:41:30.329
something like  driftPHP or
the conference I need to be more you

0:41:30.329,0:41:35.700
know... not taking that considerations but
taking one and working so hard on that

0:41:35.700,0:41:41.869
direction otherwise there's no time for
anything to be honest.

0:41:42.440,0:41:48.200
So thank you for your time for answering
questions. Thank you very much for you

0:41:48.200,0:41:54.560
and I hope we meet again soon. Yes, yes,
yes, we have a lot of to talk yeah. Sure.

0:41:54.560,0:41:59.410
Bye.

