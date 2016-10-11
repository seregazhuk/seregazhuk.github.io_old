---
title: "Symfony + Angular + Gulp"
layout: post
tags: [Symfony, Angular, Gulp]
---

So, we want to create an application and use Symfony as backend and Angular 1.x as frontend. Here come some questions:

- Where to put angular files (folder/structure)?
- Ho to automate frontend workflow?

In this article we use a standard Symfony project folder structure:
<p class="text-center image">
    <img src="/assets/images/posts/symfony-angular/project-structure.jpg" alt="cgn-edit" class="">
</p>


To glue Symfony with Angular we will use [Gulp](http://gulpjs.com). Next step we need to install it:

{% highlight bash %}
npm install --save gulp
{% endhighlight %}

and then some gulp additional modules:

{% highlight bash %}
npm install --save gulp-cli gulp-contcat gulp-sass gulp-watch
{% endhighlight %}

After everything will be installed we add a new `gulpfile.js` in the root of our project:

{% highlight js %}
var gulp   = require('gulp');
var sass   = require('gulp-sass');
var concat = require('gulp-concat');
var watch  = require('gulp-watch');

gulp.task('sass', function(){
    gulp.src('src/AppBundle/Resources/public/sass/style.scss')
        .pipe(sass())
        .pipe(gulp.dest('web/css'));
});

gulp.task('scripts', function(){
    var scripts = [
        // Dist
        'node_modules/angular/angular.min.js',
        // App
        'src/AppBundle/Resources/public/js/app.js'
    ];

    gulp.src(scripts)
        .pipe(concat('app.js'))
        .pipe(gulp.dest('web/js'));
});

gulp.task('watch', function() {
    gulp.watch('src/AppBundle/Resources/public/js/**/*', ['scripts']);
    gulp.watch('src/AppBundle/Resources/public/sass/**/*', ['sass']);
});

gulp.task('default', [
    'sass',
    'scripts'
]);
{% endhighlight %}

Our Angular app script will be placed in the **AppBundle** in *src/AppBundle/Resources/public/js/app.js* file:

{% highlight js %}
// src/AppBundle/Resources/public/js/app.js
angular.module('app', []);
{% endhighlight %}

And that is all! Now our application skeleton is ready. Everything related to frontend should be placed in *src/AppBundle/Resources/public/js/* folder. After running `gulp` all project *js* and *css* files will be put in the *web* folder, in the `web/js/app.js` and `web/css/style.css` files accordingly.

The last step is to include them in the main application layout *app/Resources/views/base.html.twig*:
{% highlight twig%}
{% raw %}
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>{% block title %}Welcome!{% endblock %}</title>
        {% block stylesheets %}
            <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        {% endblock %}
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
    </head>
    <body ng-app="dictionary">
        {% block body %}
            <div ng-view class="container">

            </div>
        {% endblock %}
        {% block javascripts %}
            <script src="{{ asset('js/app.js') }}"></script>
        {% endblock %}
    </body>
</html>
{% endraw %}
{% endhighlight %}