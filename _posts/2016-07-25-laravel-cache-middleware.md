---

title: "Laravel: Cache Response With Middleware"
layout: post
tags: [Laravel, PHP]

---

Imagine that we want to increase the speed of our site responses. Of course, we will use cache. We can cache requests to the database, we
can cache views, but we can also cache the whole response. But the response should have some dynamic parts. For example, if we have forms
we should provide csrf-tokens for them.

Let's create use Laravel Middlewares for it. For example, *CachingMiddleware*:

{% highlight bash %}
php artisan make:middleware CachingMiddleware
{% endhighlight %}

This command creates our new middleware in the `app/Http/Middleware` folder. First of all we need to define controller actions that 
we want to cache. I've made a collection `cachedActions`, where the controller class is the key, and actions will be values:

{% highlight php %}
<?php

class CachingMiddleware {

    /**
     * @var Collection
     */
    protected $cachedActions;



    public function __construct() {
        $this->initCachedActions();
    }


    protected function initCachedActions() {
        $this->cachedActions = collect();

        // cache all actions of the catalog and filter controllers.
        $this->cachedActions->put(CatalogController::class, collect()); 
        $this->cachedActions->put(FilterController::class, collect());

        return $this;
    }
}

{% endhighlight %}

Next, we need to define the main method of every middleware, `handle()`:

{% highlight php %}
<?php

class CachingMiddleware {

    /**
     * @var Collection
     */
    protected $cachedActions;


    /**
     * @var int
     */
    protected $lifeTime = 120;

    /**
     * @var Request
     */
    protected $request;

    public function handle(Request $request, Closure $next) {
        $this->request = $request;

        return $this->getResponse($next);
    }

    protected function getResponse(Closure $next) {
        // check if we don't need to cache
        if (!$this->isCached()) return $next($this->request);

        $cacheKey = $this->request->getPathInfo();

        if(!\Cache::has($cacheKey)) {
            $response = $next($this->request);

            $response->original = '';

            \Cache::put($cacheKey, $response, $this->lifeTime);
        }
    }

    protected function isCached() {
        if(app()->environment('local')) return false;

        return $this->checkRoute();
    }

    protected function checkRoute() {
        list($controller, $action) = explode('@', $this->request->route()->getActionName());

        $cachedController = $this->cachedActions->get($controller, false);

        if($cachedController === false) return false;

        if($cachedController->isEmpty()) return true;

        return !! $cachedController->get($action, false);
    }
}
{% endhighlight %}

Let's take a closer look to the `getResponse()` method. First of all we check if we need caching. For example, on local machine or if
the current route is no specified in the `cachedActions` array we simply return the response as it is. In the `cachedActions` we can
tell to cache the whole controller or some specific actions.

Next, if the current route needs to be cached we create a `cacheKey`, which is simply the current path of the route. So every route has
it own unqiue cache key.

And that is our caching middleware. It is very simple. Interesting things come when we want to have some dynamic parts in the reponse.

## Add Dynamic Content

Let's define the types of dynamic content that may occur in the response. I've defined only two: a string and a rendered view. Then we 
need to create some placeholders, that will be replaced with the real content:

{% highlight php %}
<?php

/**
 * @var array
 */
protected $replaceData = [
    '%%crf_token%%' => [
        'type' => 'string',
        'data' => '',
    ],
    '%%user_cart%%' => [
        'type' => 'view',
        'partials.header.cart'
    ]
];
{% endhighlight %}

In the code above I've defined a protected property `$replaceData`. It has to sub-arrays: one for the csrf-token for our forms on the site,
and the second for the user cart info, that will be displayed in the site header.

Next, we need methods to replace different dynamic content from the cache with the real data: `replaceViewContent`
and `replaceStringContent`. The first one will render the view and then replace the specified placeholder with it, and the second one
will simply do *str_replace*:

{% highlight php %}
<?php

/**
 * @var string $content
 * @var string $placeholder
 * @var array $replace
 * @return string
 */
protected function replaceViewContent($content, $placeholder, $replace) {
    return str_replace($placeholder, view($replace['data'])->render(), $content);
}

/**
 * @var string $content
 * @var string $placeholder
 * @var array $replace
 * @return string
 */
protected function replaceStringContent($content, $placeHolder, $replace) {
    return str_replace($placeholder, $replace['data'], $content);
}

{% endhighlight %}

Notice that there is a common pattern in these methods names: *replaceTYPEContent*. It is done to call them dynamically, depending on the
replace content type. Now we need some base method, that will iterate through the `replaceData` and replace the dynamic content:

{% highlight php %}

<?php

/**
 * @param string $content
 * @return string
 */
protected function replaceDynamicContent($content) {
    foreach($this->replaceData as $placeholder => $replace) {
        $method = 'replace' . ucfirst($replace['type']) . 'Content';

        $content = method_exists($this, $method) ?
            $this->{$method}($content, $placeholder, $replace) :
            $content;
    }

    return $content;
}
{% endhighlight %}

Lastly, we need to update our `handle()` method:

{% highlight php %}
<?php

public function handle(Request $request, Closure $next) {
    $this->request = $request;

    $response = $this->getResponse($next);

    $response = $response->setContent($this->replaceDynamicContent($response->getContent));

    return $response;
   
}
{% endhighlight %}

Now our caching middleware is ready. But how we are going to replace, for example, csrf-tokens? Where to put tokens in the view? 

## Example with token

We can use meta tag in the main layout and put the placeholder there:

{% highlight html %}
<meta name="_token" content="%%csrf_token%%">
{% endhighlight %}

Next, we need to tell our middleware the real token for the replacement. For this purpose we can define a new method `initReplaceData` and 
call it in the constructor:

{% highlight php %}
<?php

class CachingMiddleware {

    /**
     * @var Collection
     */
    protected $cachedActions;


    /**
     * @var int
     */
    protected $lifeTime = 120;

    /**
     * @var Request
     */
    protected $request;

    /**
    * @var array
    */
    protected $replaceData = [
        '%%crf_token%%' => [
            'type' => 'string',
            'data' => '',
        ],
        '%%user_cart%%' => [
            'type' => 'view',
            'partials.header.cart'
        ]
    ];

    public function __construct() {
        $this
            ->initCachedActions()
            ->initReplaceData();
    }

    protected function initReplaceData() {
        $this->data['%%csrf_token%%']['data'] = csrf_token();

        return $this;
    }
}
{% endhighlight %}
