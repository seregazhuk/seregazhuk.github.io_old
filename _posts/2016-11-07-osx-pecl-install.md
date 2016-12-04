---
title: Install PECL Extensions on OS X
description: "How to use and install PECL Extensions on OS X."
layout: post
---

First of all install we need to install PEAR:

{% highlight bash %}
curl -O  http://pear.php.net/go-pear.phar
php -d detect_unicode=0 go-pear.phar
{% endhighlight %}

The installation will suggest to set up some directories:

{% highlight bash %}
Below is a suggested file layout for your new PEAR installation.  To
change individual locations, type the number in front of the
directory.  Type 'all' to change all of them or simply press Enter to
accept these locations.

 1. Installation base ($prefix)                   : /Users/serega/pear
 2. Temporary directory for processing            : /tmp/pear/install
 3. Temporary directory for downloads             : /tmp/pear/install
 4. Binaries directory                            : /Users/serega/pear/bin
 5. PHP code directory ($php_dir)                 : /Users/serega/pear/share/pear
 6. Documentation directory                       : /Users/serega/pear/docs
 7. Data directory                                : /Users/serega/pear/data
 8. User-modifiable configuration files directory : /Users/serega/pear/cfg
 9. Public Web Files directory                    : /Users/serega/pear/www
10. System manual pages directory                 : /Users/serega/pear/man
11. Tests directory                               : /Users/serega/pear/tests
12. Name of configuration file                    : /Users/serega/.pearrc

1-12, 'all' or Enter to continue:
{% endhighlight %}

I've used the default settings and pressed **Enter**. Then next step is to add PEAR to your PATH. I use [Oh My Zsh](http://ohmyz.sh) and my PATH settings are located in `~/.zshrc` file:

{% highlight bash %}
# Pear
# -----------------------------------------------------------------------------
export PATH="/Users/serega/pear/share/pear:$PATH"
export PATH="/Users/serega/pear/bin:$PATH"
{% endhighlight %}

To check your installation run:

{% highlight bash %}
pear version
{% endhighlight %}

If the installations was successful the output will be similar to the following:

{% highlight bash %}
PEAR Version: 1.10.1
PHP Version: 7.0.12
Zend Engine Version: 3.0.0
Running on: Darwin MacBook-Air-Sergej.local
{% endhighlight %}

Then there are two steps to install any extension:

1. Run `pecl install *extension_name*`
2. Add appropriate *\*.so* file to your *php.ini*