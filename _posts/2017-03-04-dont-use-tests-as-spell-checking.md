---
title: "Don't Use Your Testing Tool As A Spell Checker"
tags: [PHP, Testing, PHPUnit]
layout: post
description: 'Common mistake mocking in tests, when you use your tests as a spell checker for your code'
---

Today, when I started refactoring tests for my [PHP Pinterest Bot](https://github.com/seregazhuk/php-pinterest-bot) library, I've noticed one issue.
According to coverage, reports I have 80% code coverage. I though that I'm writing a lot good tests. But then I noticed that most of them actually doesn't test anything. The tests are *overmocked*. They only test the code grammar, that I have no typos in my code. 

For example this silly test, checking that some class delegates a method call to its dependency. In the snippet bellow, take a look at the `it_delegates_client_info_to_response` test:

{% highlight php %}
<?php

/**
 * Class ProvidersContainerTest.
 */
class ProvidersContainerTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_delegates_client_info_to_response()
    {
        $response = Mockery::mock(Response::class);
        $request = Mockery::mock(Request::class);

        $container = new ProvidersContainer($request, $response);

        $clientInfo = ['info'];
        $response
            ->shouldReceive('getClientInfo')
            ->andReturn($clientInfo);

        $this->assertEquals($clientInfo, $container->getClientInfo());
    }
}
{% endhighlight %}

Everything looks good, we have mocked a dependency and then assert that a required method has been called. 

But wait a minute, what does this test actually check? 

In reality, it executes no useful code. We create a mock then we pass it as a dependency and then we assert that this mock has been executed. When in the future we will refactor and rename `getClientInfo` method to `getClientData` our test will fail. It is stupid to fix tests every time when we refactor because in these tests I have simply recreated the entire application implementation. Now, every time we refactor, we should go and refactor our tests. 
Of course it will fail because now `ProvidersContainer` is broken, but actually, it is broken because we have only a typo in a method name. In this case, we use PHPUnit only as a *spell checker*, to be sure that there are *no misspells in the property or method names* and everything is *typed correctly*. 

## Conclusion
Mocking everything and reimplementing everything in your tests actually doesn't test anything. Don't waste your time to write such kind of *tests* and don't have an illusion of the high code coverage. 

At least I've deleted more than 50% of my tests. To keep high code coverage reports I made changes in my `phpunit.xml` file in `whitelist`:

{% highlight xml %}
<whitelist>
    <directory suffix=".php">./src/</directory>
    <exclude>
        <file>/src/Api/Providers/Topics.php</file>
        <file>/src/Api/Providers/Boards.php</file>
        <file>/src/Api/Providers/Comments.php</file>
        <file>/src/Api/Providers/Inbox.php</file>
        <file>/src/Api/Providers/Pins.php</file>
        <file>/src/Api/Providers/Pinners.php</file>
        <file>/src/Api/Providers/Interests.php</file>
        <file>/src/Api/Providers/User.php</file>
    </exclude>
</whitelist>
{% endhighlight %}

In this section, we can use `exclude` tag to remove some files or directories from the coverage report.

*This article was inspired by [Laravel Podcast Episode 38](http://www.laravelpodcast.com/episodes/20182-episode-38-repositorybeanfactory)*.