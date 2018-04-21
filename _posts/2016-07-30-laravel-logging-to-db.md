--- 

title: "Laravel 5.0: use database and Eloquent for logging"
layout: post
description: "Creating logging system based on Eloquent in Laravel."
tags: [Laravel, PHP]

---

<p class="text-center image">
    <img src="/assets/images/posts/laravel-logging-to-db/logging-to-db.jpg" alt="logging-to-db" class="">
</p>


{% include deprecated.html %}

## MySQL

By default Laravel stores all error messages in logs files in `storage/logs` directory. But sometimes it is not
very convenient to analyze these log files or to aggregate them. In this article, I'm going to use mysql and 
Eloquent to store errors in a database.

First of all, we need a table, so lets create a model and an empty migration:

{% highlight bash %}
php artisan make:model Log -m
{% endhighlight %}

This command will create a model `App\Log` with an appropriate migration for it.

app/Log.php:

{% highlight php %}
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Log extends Model {
    use SoftDeletes;

    protected $fillable = [
        'env',
        'message',
        'level',
        'context',
        'extra'
    ];

    protected $casts = [
        'context' => 'array',
        'extra'   => 'array'
    ];
}

{% endhighlight %}

database/migrations/your_current_time_create_logs_table.php:

{% highlight php %}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLogsTable extends Migration {
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('env');
            $table->string('message', 500);
            $table->enum('level', [
                'DEBUG',
                'INFO',
                'NOTICE',
                'WARNING',
                'ERROR',
                'CRITICAL',
                'ALERT',
                'EMERGENCY'
            ])->default('INFO');
            $table->text('context');
            $table->text('extra');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
{% endhighlight %}

Next, we need to configure Monolog. Laravel uses this library for logging. To use custom logging we need to override the 
`Illuminate\Foundation\Bootstrap\ConfigureLogging` class. Bur before we need to create a custom Mongolog `EloquentHandler`
to store logs in a database and a custom `RequestProcessor` preprocessor to add some more information from the request
to our logs.

App/Vendors/Monolog/Handler/EloquentHandler:

{% highlight php %}
<?php

namespace App\Vendors\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;

class EloquentHandler extends AbstractProcessingHandler {
    protected function write(array $record) {
        \App\Log::create([
            'env'     => $record['channel'],
            'message' => $record['message'],
            'level'   => $record['level_name'],
            'context' => $record['context'],
            'extra'   => $record['extra']
        ]);
    }
}
{% endhighlight %}

App/Vendors/Monolog/Processor/RequestProcessor:
{% highlight php%}
<?php

namespace App\Vendors\Monolog\Processor;

class RequestProcessor {
    public function __invoke(array $record) {
        $request = request();

        $record['extra']['serve'] = $request->server('SERVER_ADDR');
        $record['extra']['host'] = $request->getHost();
        $record['extra']['uri'] = $request->getPathInfo();
        $record['extra']['request'] = $request->all();

        return $record;
    }
}
{% endhighlight %}


Now let's create the `ConfigureLogging` class and put it into the `app/Vendors/Illuminate/Foundation/Bootstrap` folder. In
the `bootstrap()` method we will set our `EloquentHandler` and `RequestProcessor`:

{% highlight php %}
<?php

namespace App\Vendors\Illuminate\Foundation\Bootstrap;

use App\Vendors\Monolog\Handler\EloquentHandler;
use App\Vendors\Monolog\Processor\RequestProcessor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\ConfigureLogging as BaseConfigureLogging;

class ConfigureLogging extends BaseConfigureLogging {
    public function bootstrap(Application $app) {
        $log = $this->registerLogger($app)->getMonolog();

        $log->pushHandler(new EloquentHandler());
        $log->pushProcessor(new RequestProcessor());
    }
}
{% endhighlight %}

Lastly, we need to update our `Kernel` class. We override the `$boostrappers` property of the parent `HttpKernel` class and
add a new `ConfigureLogging` class to the array:

{% highlight php %}
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel {
    protected $bootstrappers = [
        \Illuminate\Foundation\Bootstrap\DetectEnvironment::class,
        \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        \App\Vendors\Illuminate\Foundation\Bootstrap\ConfigureLogging::class,
        \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class,
    ];

    // ...
}
{% endhighlight %}

Next, we can change our exception `Handler`, to specify what kind of exceptions we don't want to log to database:

{% highlight php %}
<?php 
namespace App\Exceptions;

use Exception;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler {

    /**
    * A list of the exception types that should not be reported.
    *
    * @var array
    */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    // ...
}
{% endhighlight %}

## MongoDB

We can use another storage for logs. [MongoDB](https://www.mongodb.com) is a document-orientated database, and I think it the best solution for storing logs, because we 
are not limited to the schema. 
First of all, we need to install mongodb php driver:

{% highlight bash %}
brew install php-mongodb
{% endhighlight %}

Next, we need to install [laravel-mongodb library](https://github.com/jenssegers/laravel-mongodb) to use MongoDB based Eloquent model:

{% highlight bash %}
composer require jenssegers/mongodb
{% endhighlight %}

I'm going to use both databases in my project: MySQL as a basic storage and MongoDB for logs and statistics. 
To use MongoDB we need to update the `config/database.php` file and add `mongodb` driver there:

{% highlight php %}
<?php

    'mongodb' => [
        'driver'   => 'mongodb',
        'host'     => env('DB_HOST', 'localhost'),
        'port'     => env('DB_PORT', 27017),
        'database' => env('DB_DATABASE', 'logs'),
    ]
{% endhighlight %}

Then, we need to add a new service provider to the `config/app.php` file:
{% highlight php %}
<?php

   /*
    * Application Service Providers...
    */
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    Jenssegers\Mongodb\MongodbServiceProvider::class,
{% endhighlight %}

The last part is simply to extend our `Log` model from `Jenssegers\Mongodb\Eloquent\Model`. And because we use two database connections, we need to
specify the connection. I've also specified the collection name:

{% highlight php %}
<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Log extends Model {
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'logs';

    protected $fillable = [
        'env',
        'message',
        'level',
        'context',
        'extra'
    ];

    protected $casts = [
        'context' => 'array',
        'extra'   => 'array'
    ];
}


{% endhighlight %}

That's all, now all logs will be stored in the *logs* collection in our *app* database.
