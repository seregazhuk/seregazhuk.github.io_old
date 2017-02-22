---
title: "Laravel: Organize Your Cron Schedule In Large Application"
tags: [Laravel]
layout: post
description: "How to organize and manage a lot of cron tasks in Laravel"
---

Laravel has a very nice way to manage your cron jobs. `app/Console/Kernel.php` file has a `schedule()` method. It accepts an instance of `Schedule` component and every cron task can be defined here:

{% highlight php %}
<?php

class Kernel extends ConsoleKernel {
    // ...
    protected function schedule(Schedule $schedule) {
        // ...
    }
    // ...
}
{% endhighlight %}

Then you put one line in your cron list and you are ready to go:

{% highlight bash %}
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
{% endhighlight %}


But while your application grows it has more and more cron jobs to manage:

{% highlight php %}
<?php

class Kernel extends ConsoleKernel {
    // ...
    protected function schedule(Schedule $schedule) {
        $schedule->command('order:not-responded-check')->everyFiveMinutes();
        $schedule->command('order:confirm-partners')->hourly();
        $schedule->command('products:set-rating')->dailyAt('02:30');
        $schedule->command('service:generators:sitemap')->dailyAt('00:35');
        $schedule->command('order:generate:statistic')->everyThirtyMinutes();
        $schedule->command('send:daily-emails')->dailyAt('00:00');
        $schedule->command('service:log:clear')->dailyAt('04:15');    
        $schedule->command('order:auto-close')->dailyAt('02:05');
        $schedule->command('client:mail:send-bonus-notifications')->weekdays()->at('10:00');
        $schedule->command('promo-code-sms:send')->dailyAt('12:00');
        // ... and other over 50 commands
    }
    // ...
}
{% endhighlight %}

Placing all of the commands in one method becomes a nightmare. Your `schedule()` method grows and grows, and you have to scroll it to find any command. It is impossible to figure out what your cron schedule looks like. The point is that when we have several cron tasks it is ok, to put all of them in `schedule()` method. But with large applications, it looks too detailed.

My approach for this kind of problem is to group all of these tasks according to their schedule. For example, some jobs run daily at a certain time, some jobs run several times a day, and others run on certain days. So it looks like we need three methods: 

- `scheduleInDayCommands()` commands that run several times a day
- `scheduleDailyCommands()` commands that run daily
- `scheduleOnDayCommands()` commands that run on certain days

Then we place all our commands to appropriate methods, according to their schedule. In the main `schedule()` method we place a methods chain of our new three methods:

{% highlight php %}
<?php

class Kernel extends ConsoleKernel {
    
    protected function schedule(Schedule $schedule) {
        $this
            ->scheduleInDayCommands($schedule)
            ->scheduleDailyCommands($schedule)
            ->scheduleOnDayCommands($schedule);
    }

    protected function scheduleInDayCommands(Schedule $schedule) {
        $schedule->command('order:not-responded-check')->everyFiveMinutes();
        $schedule->command('order:confirm-partners')->hourly();
        $schedule->command('order:generate:statistic')->everyThirtyMinutes();
    }

    protected function scheduleDailyCommands(Schedule $schedule) {
        $schedule->command('products:set-rating')->dailyAt('02:30');
        $schedule->command('service:generators:sitemap')->dailyAt('00:35');
        $schedule->command('send:daily-emails')->dailyAt('00:00');
        $schedule->command('service:log:clear')->dailyAt('04:15');    
        $schedule->command('order:auto-close')->dailyAt('02:05');
        $schedule->command('promo-code-sms:send')->dailyAt('12:00');
    }

    protected function scheduleOnDayCommands(Schedule $schedule) {
        $schedule->command('client:mail:send-bonus-notifications')->weekdays()->at('10:00');
    }

}
{% endhighlight %}

Now our schedule looks much better and more organized. It is easier to find out what commands run more often than others, which of them run daily and which several times a day.
