---

layout: post
title: ES6 with Babel and Gulp
tags: [Gulp, ES6, Babel]
comments: true

---

First, make sure we have the latest versions of both cli and local version of gulp:
{% highlight python %}
$ gulp -v
CLI version 3.9.0
Local version 3.9.0
{% endhighlight %}

## Creating an ES6 gulpfile
To use ES6 we need to have installed Babel as a dependency to our project, with es2015 plugin preset:
{% highlight python %}
$ npm install babel-core babel-preset-es2015 --save-dev
{% endhighlight %}

We can specify preset with the command option or simply create .babelrc file:
{% highlight json %}
{
    "presets": ["es2015"]
}
{% endhighlight %}

Then we need to tell gulp to use Babel.It can be done by renaming ``gulpfile.js`` to ``gulpfile.babel.js``:

{% highlight python %}
$ mv "gulpfile.js" "gulpfile.babel.js"
{% endhighlight %}

And that's it. Now we can use ES6 syntax via Babel, for example some sort of gulpfile:

{% highlight javascript %}
"use strict";
import gulp from gulp;

gulp.task("default", () => {
    console.log("From gulp!");
});
{% endhighlight %}
