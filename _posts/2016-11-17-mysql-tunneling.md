---
title: "MySql SSH Tunneling"
layout: "post"
description: "Steps to configure mysql tunneling in PhpStorm."
tags: [PhpStorm, MySQL, SSH]
---

Very often in our work, we have a remote database with closed remote connections, but somehow we need to get data from it. So we have connection string with login, password, we also have ssh account on the remote server, but the database is closed for remote connections.
The solution is SSH tunnel.

## PhpStorm
It is possible to setup PhpStorm to get access to the database via ssh tunnel. When adding a new database source we have **SSH/SSL** tab. Here we fill our ssh credentials:

<div class="row">
<div class="col-sm-7">
<p class="text-center image">
    <img src="/assets/images/posts/mysql-ssh/storm-1.jpg" alt="cgn-edit" class="">
</p>
</div>
</div>

Then on the **General** tab we fill credentials for the remote database. Don't forget to use *localhost* in the *host* field:

<p class="text-center image">
    <img src="/assets/images/posts/mysql-ssh/storm-2.jpg" alt="cgn-edit" class="">
</p>

Done! Now we have a connection to our remote database via ssh tunnel.

## Connection in code
But what if we need to query to our remote database in our application? How to deal with it?
First of all, we need to create an ssh tunnel to remote host:

{% highlight bash %}
ssh -v -L 3336:localhost:3306 login@your-remote-host.com
{% endhighlight %}

Here option `-v` can be removed, I use it for verbose. Then after `-L` option you a free port on your machine, then the port on the remote host, and at the end your ssh credentials.

After that, in our application, we should use `localhost` as the host, and port `3336` to connect to the remote database.
