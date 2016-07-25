--- 

title: "Laravel: use database and Eloquent for logging"
layout: post
tags: [Laravel, PHP]

---

By default Laravel stores all error messages in logs files in `storage/logs` directory. But sometimes it is not
very convenient to analyze these log files or to aggregate them. In this artice I'm going to to use mysql and 
Eloquent to store errors in a database.

First of all we need a table, so lets create a model and an empty migration:

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
        'area',
        'level',
        'context',
        'extra'
    ];

    protected $casts = [
        'context' => 'array',
        'extra' => 'array'
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
            $table->string('area', 500);
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

Next, we need to configure Monolog. Laravel users this library for logging. To use custom logging we need to override the 
`Illuminate\Foundation\Bootstrap\ConfigureLogging` class. Bur before we need to create a custom Mongolog handler to 
store logs in database `EloquentHandler`, and a custom `RequestProcessor`preprocessor to add more information about the requet to our
logs.

App/Vendors/Monolog/Processor/RequestPreprocessor:
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

App/Vendors/Monolog/Handler/EloquentHandler:

{% highlight php %}
<?php

namespace App\Vendors\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;

class EloquentHandler extends AbstractProcessingHandler {
    protected function write(array $record) {
        \App\Log::create([
            'env'     => $record['channel'],
            'area'    => $record['message'],
            'level'   => $record['level_name'],
            'context' => $record['context'],
            'extra'   => $record['extra']
        ]);
    }
}
{% endhighlight %}

Now lets create `ConfigureLogging` class and put it into the `app/Vendors/Illuminate/Foundation/Bootstrap` folder. In
the `bootstrap()` method we will set our `EloquentHandler` and `RequestProcessor`:

{% highlight php %}
<?php

namespace App\Vendors\Illuminate\Foundation\Bootstrap;

use Crm\Classes\Vendors\Monolog\Handler\EloquentHandler;
use Crm\Classes\Vendors\Monolog\Processor\RequestProcessor;

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

Lastly we need to update our `Kernel` class and add new `ConfigureLogging` class to the `$bootstrappers` array:

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
