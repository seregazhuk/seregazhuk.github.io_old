---

title: "Laravel: Cache Response With Middleware"
layout: post
tags: [Laravel, PHP]

---

Imagine that we want to increase the speed of our site responses. Ofcourse we will use cache. We can cache requests to the database, we
can cache views, but we can also cache the whole response. But the response should have some dynamic parts. For example, if we have forms
we should provide csrf-tokens for them.

Lets create use Laravel Middlewares for it. For exmaple, *CachingMiddleware*:

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
     * @var int
     */
    protected $lifeTime = 120;

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
