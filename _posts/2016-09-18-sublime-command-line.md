---
title: "Sublime Text 3 from command line in OS X"
layout: post
---

Sublime Text 3 comes with a cli tool called `subl` to work with files on the command line. But 
it is not available from terminal by default.

In the [documentation](http://www.sublimetext.com/docs/3/osx_command_line.html) it is said that this tool
is located in `/Applications/Sublime Text.app/Contents/SharedSupport/bin/` folder. And to make it available 
you should create a symlink in `/bin/subl` folder.

In OS X the load path be default is `/usr/local/bin`. So to run sublime from terminal we should place a symlink 
for it in this folder:

{% highlight bash %}
ln -s "/Applications/Sublime Text.app/Contents/SharedSupport/bin/subl" /usr/local/bin/subl
{% endhighlight %}

Now I can easily open Sublime Text from the command line:

{% highlight bash %}
// opens current directory
subl .

// opens Docs directory
subl ~/Docs
{% endhighlight %}