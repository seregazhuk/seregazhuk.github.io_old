---
title: "Memory Leaking With A Lot Of Eloquent Queries"
description: "Increase Eloquent performance with a bunch of queries."
layout: post
tags: ["Laravel", "Eloquent"]
---

Imagine, that we have a situation where we need to make a lot of queries with Eloquent in a loop. Something like this:

{% highlight php %}
<?php

    $limit = 10000;
    $offset = 0;

    while(true) {
        $records = Statistics::where('processed', 1)
                ->take($limit)
                ->offset($offset)
                ->get();

        if(empty($records)) break;

        $this->processRecords($records);

        $offset += $limit;
    }

{% endhighlight %}

Here in an endless loop, we grab records from out statistics and process them. But what happens if this loop will work too long. Soon you will find out that you are out of memory. But why? PHP memory leaks? Ok, lets force to run a garbage collector after every iteration:

{% highlight php %}
<?php

    $limit = 10000;
    $offset = 0;

    while(true) {
        $records = Statistics::where('processed', 1)
                ->take($limit)
                ->offset($offset)
                ->get();

        if(empty($records)) break;

        $this->processRecords($records);

        gc_collect_cycles();
        $offset += $limit;
    }

{% endhighlight %}

This doesn't help, the memory is out again. Maybe we can force to unset out variables:

{% highlight php %}
<?php

    $limit = 10000;
    $offset = 0;

    while(true) {
        $records = Statistics::where('processed', 1)
                ->take($limit)
                ->offset($offset)
                ->get();

        if(empty($records)) break;

        $this->processRecords($records);
        unset($records);

        gc_collect_cycles();
        $offset += $limit;
    }

{% endhighlight %}

This doesn't help too. The memory is still leaking. After some research, I have found out that it was Eloqunet. Eloquent logs all the queries by default. To disable this behavior you can do this:

{% highlight php %}
<?php

\DB::connection()->disableQueryLog();
{% endhighlight %}

And it you are using Eloquent outside of Laravel:

{% highlight php %}
<?php

\Illuminate\Database\Capsule\Manager::connection()->disableQueryLog();
{% endhighlight %}