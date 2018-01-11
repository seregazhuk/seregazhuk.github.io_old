---
title: "Test Coverage: Integration Between CodeClimate and Travis CI"
tags: [PHP, Open Source, Testing, CI]
layout: post
image: "/assets/images/posts/travisci-codeclimate/travis-loves-code-climate.png"
description: "Integration between CodeClimate and Travis CI to show your open source project test coverage"
---

When you maintain an open-source project it is considered a good practice to have a high test coverage, so the community can feel safe about using your code in their projects. There are some services that can analyze your code quality and provide some feedback about it. One of the most popular is [Code Climate](http://codeclimate.com). This service doesn't run your tests, but you can use one of CI tools to run them and then send their result to Code Climate. This article will show how to use [Travis CI](https://travis-ci.org) to run your tests and [CodeClimate](http://codeclimate.com) to get your test coverage.

<p class="text-center image">
    <img src="/assets/images/posts/travisci-codeclimate/travis-loves-code-climate.png" alt="logo" class="">
</p>

## 1. Get Your CodeClimate Reporter ID

On CodeClimate side you need only a reporter ID. Go to your repository setting, then select "Test Coverage".

<p class="text-center image">
    <img src="/assets/images/posts/travisci-codeclimate/reporter-id.png" alt="reporter-id" class="">
</p>


## 2. Add Your Code Climate Token To Travis CI

The next step is to add your token to your `travis.yml` file. But don't rush to do it right away! It is a secret token, don't commit it to your repository. At first, you should encrypt it with [Travis CI Client](https://github.com/travis-ci/travis.rb). It is a ruby gem that includes both a command line client and a Ruby library to interface with a Travis CI service.

### 2.1 Install Travis CI Client

It is a simple ruby gem, so to install it, run in your terminal:

<div class="row">
    <div class="col-sm-7">
        <p class="text-center image">
            <img src="/assets/images/posts/travisci-codeclimate/gem-install.png" alt="gem-install" class="">
        </p>
    </div>
</div>  

### 2.2 Login To Travis CI Client

Then, you need to login. Run `travis login` and provide you GitHub credentials:

<div class="row">
    <div class="col-sm-7">
        <p class="text-center image">
            <img src="/assets/images/posts/travisci-codeclimate/travis-login.png" alt="travis-login" class="">
        </p>
    </div>
</div>

### 2.3 Encrypt CodeClimate Token

Now, we can encrypt your CodeClimate token with `travis encrypt` command:

<p class="text-center image">
    <img src="/assets/images/posts/travisci-codeclimate/travis-encrypt.png" alt="travis-encrypt" class="">
</p>

Copy everything in double quotes after `secure:`, it is your encrypted token.

### 2.4 Add Encrypted Token To Travis CI Config 

The last step is to add your repository encrypted token to `travis.yml` file like this:

{% highlight yml %}
addons:
  code_climate:
    repo_token:
      secure: "pdpKV..."
{% endhighlight %}

## 3. Add CodeClimate Test Reporter Package
I assume that you are testing PHP package, so we need to add CodeClimate test reporter as a dev dependency to our project:

{% highlight bash %}
composer require codeclimate/php-test-reporter --dev
{% endhighlight %}

## 4. Update phpunit.xml 
We need manually collect tests report before sending it to CodeClimate. Add this section to your `phpunit.xml` file to collect coverage data to `build` folder in the root of your project:

{% highlight xml %}
<phpunit>
    ...
    <logging>
        <log type="coverage-clover" target="build/logs/clover.xml"/>
    </logging>
    ...
</phpunit>
{% endhighlight %}

Also, you can add this folder to your `.gitignore` file.

## 5. Update Travis CI Config To Send A Report
Add this section to your `travis.yml` to execute CodeClimate reporter, after your tests have been executed:

{% highlight yml %}
after_script:
  - vendor/bin/test-reporter
{% endhighlight %}

## That Is All! 
Next time when your push a build on Travis CI, it will run your tests and then send coverage report to CodeClimate.

<p class="text-center image">
    <img src="/assets/images/posts/travisci-codeclimate/coverage.png" alt="coverage" class="">
</p>

Now you can also add some CodeClimate badges to your repo's README file:

<p class="text-center image">
    <img src="/assets/images/posts/travisci-codeclimate/badges.png" alt="badges" class="">
</p>
