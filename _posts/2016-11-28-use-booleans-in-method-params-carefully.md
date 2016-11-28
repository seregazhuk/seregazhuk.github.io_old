---
title: "Use Booleans In Method Params Carefully"
layout: post
tags: [PHP, Refactoring]
---

Today when I was refactoring my [PHP Pinterest Bot](https://github.com/seregazhuk/php-pinterest-bot), I've noticed that it has a *logout* method with a boolean param. So when you want to logout, you use something like this:

{% highlight php %}
<?php

$bot->auth->logout();
{% endhighlight %}

But you can also type this:

{% highlight php %}
<?php

$bot->auth->logout(true);
{% endhighlight %}

And I actually didn't remember what means this *true*. Looking through the code, I've remembered that is a flag to clear cookies:

{% highlight php %}
<?php

/**
 * If $removeCookies is set, cookie file will be removed
 * from the file system.
 *
 * @param bool $removeCookies
 */
public function logout($removeCookies = false)
{
    $this->request->logout($removeCookies);
}
{% endhighlight %}

And here comes the question. Should I leave it as it is or should I refactor it somehow. Because again in six months I should look through the code or the documentation to find out, what does *true* means in `$bot->auth->logout(true)`. The boolean passed as a flag to the method always mean, that this method does too much. It has several tasks, each is performed according to the passed flag.
So, of cource we can pass this parameter explicitly:

{% highlight php %}
<?php

$bot->auth->logout($clearCookies = true);
{% endhighlight %}

And now we can read the code, and understand the purpose of the param. But the core problem still exists. The method does too much. I think that it is better to create a separate method to handle cookies. Something like this:

{% highlight php %}
<?php

$bot->auth->logout();
$bot->auth->clearCookies();
{% endhighlight %}

Now I have two clear methods, and in six months from now, there will be no questions about their meanings and purpose.