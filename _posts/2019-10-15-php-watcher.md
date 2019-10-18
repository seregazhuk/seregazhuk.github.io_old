---

title: "Introducing PHP-Watcher"
layout: post
description: "How to reload your PHP application when changing its source code"
tags: [PHP, ReactPHP, Development]
image: "/assets/images/posts/php-watcher/thumb.png" 

---

## The problem

I like PHP for its simplicity: you code something, refresh the page in the browser and you can see your changes. There is no need to wait, to compile something, just refresh the page and you are ready to go. But, PHP is not only about request-response. Sometimes we create some CLI tools and some of these tools are long-living processes. For example, we create an asynchronous HTTP server for uploading files on the server. This server should be always running and listening for incoming connections. The development process of a long-running command in PHP can become really painful. In the terminal, we cannot "refresh the page" to see the changes. Each time we make changes in the source code, we have to manually interrupt our command and restart it. Being a developer I always strive to optimize my workflow. In PHP I haven't found any appropriate solution, so [I have chosen nodemon]({% post_url 2019-09-16-live-reload-php-applications %}){:target="_blank"}. It is a tool from NodeJS ecosystem and was built to help in developing NodeJs applications. But with some tweaks, it can be easily used with PHP script. Sometimes it is OK, and sometimes we don't want to install NodeJS and NPM to just restart my app. Actually, I don't want to have a bunch of unknown npm packages which are totally redundant for a PHP asynchronous application. So, I have decided to create my own solution which can be used in PHP ecosystem. I have announced it on Twitter and many people liked the idea of having such a tool, which is written in a pure PHP and available via Composer.

<blockquote class="twitter-tweet" data-lang="en"><p lang="en" dir="ltr">Finally, it is ready! PHP-Watcher - a package to automatically restart PHP application once the source code changes. Can be very useful for developing long-running PHP applications 🎉👍<a href="https://t.co/wIeuJejDvq">https://t.co/wIeuJejDvq</a></p>&mdash; Sergey Zhuk (@zhukserega) <a href="https://twitter.com/zhukserega/status/1179722274414436352?ref_src=twsrc%5Etfw">October 3, 2019</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script> 

## PHP-watcher

What is [PHP-watcher](https://github.com/seregazhuk/php-watcher){:target="_blank"}? Consider it as nodemon but for PHP and written in PHP. It helps develop long-running PHP applications by automatically restarting them when file changes are detected.

Here's how it looks like:

<img src="/assets/images/posts/php-watcher/demo.svg?sanitize=true" alt="watcher screenshot" style="max-width:100%;">

The package can be installed via Composer:

```bash
composer global require seregazhuk/php-watcher
```

And you are ready to go. Let's say that we are working on some long-running application based on Symfony framework. The entry point to our app is `public/index.php` and we want to monitor changes in `src` and `config` directories. Thus, once we change the source code or config params we want our app to be automatically restarted. This task can be solved with the watcher:

```bash
php-watcher public/index.php --watch src --watch config 
```

The command above executes PHP script `public/index.php` and starts watching directories `src` and `config` for changes. Once, any PHP file in these directories is being changed the watcher will restart the script. By default, it detects changes only in `*.php` files. But Symfony stores its config files in `yaml` format. So, we can explicitly tell the watcher to watch both `php` and `yaml` extensions providing `--ext` option:

```bash
php-watcher public/index.php --watch src --watch config --ext php,yaml
```

Then, let's say that we don't want to reload the app for any change in `src` directory. For example, we want to ignore `src/Migrations` subdirectory. In this case use `--ignore` option:

```bash
 php-watcher public/index.php --watch src --watch config --ext php,yaml --ignore Migrations
```

Now, the watcher starts watching `src` and `config` directories, but ignores `Migrations` subdirectory. Note that by default, it ignores all dot and VCS files.

PHP-watcher also supports customization of its behavior with config files. So, instead of passing a bunch of options via CLI command you can create a config file `.php-watcher.yml`. For example the previous command can be replaced with the following config file:

```yaml
script: 'public/index.php'
watch:
  - src
  - config
extensions:
  - php
  - yaml
ignore:
  - Migrations
```

Having this config file you can just run `php-watcher` and all the settings will be taken from this file:

```bash
php-watcher   
```

What happens if you have both config file and CLI arguments? The specificity is as follows so that a command-line argument will always override the corresponding config file setting.

By default, the watcher uses `php` executable to run the script. When we call this:

```bash
php-watcher public/index.php
```

Under the hood, it creates a new child process that runs this command - `php public/index.php`. In most cases it is OK, but if in your environment PHP executable is different, you can explicitly tell the watcher what command it should run. For example, we have several PHP versions in the system, and we want our app to run on PHP 7.4. Use `--exec` option and provide your executable:

```bash
php-watcher public/index.php --exec php7.4
```

or via config file:

```yaml
watch:
  - src
  - config
executable: php7.4
```
## Conclusion

Now, you don't have to install nodemon or any other npm package for developing your long-running PHP application. You can use PHP-Watcher which is pure PHP and provides the same functionality as nodemon does. 

If you want to learn more about the watcher, make sure to check out the [project homepage](https://github.com/seregazhuk/php-watcher){:target="_blank"}. Its documentation describes common usage patterns. The project is still under development, but the API is rather stable. So, feel free to provide any feedback by creating an issue on GitHub.

<p class="text-center image row">
    <img src="/assets/images/posts/php-watcher/nojs.gif" class="col-sm-6 col-sm-offset-3">
</p>



