---

title: "Laravel: Problems With Migrations And SQLite"
description: "Fix SQLite errors with Laravel migrations."
tags: [Laravel, PHP, SQLite]
layout: post

---

When testing with SQLite, I've met several problems with migrations.

### Cannot add a NOT NULL column with default value NULL

When you have a migration that changes columns in a table:

{% highlight php %}
<?php

public function up()
{
    Schema::table('users', function (Blueprint $table){
        $table->string('nickname');
    });
        
}

public function down()
{
    Schema::table('organisations', function (Blueprint $table){
        $table->dropColumn('nickname');
    });
}

{% endhighlight %}

When running this migration on MySQL everything is OK. But when running the same migration on SQlite, it throws the following error:

{% highlight bash %}
Exception: SQLSTATE[HY000]: General error: 1 Cannot add a NOT NULL column with default value NULL
{% endhighlight %}

This was very frustrating for me. But after googling this error, I've found that it is SQLite *feature*. When adding a table from scratch,
you can specify NOT NULL field. However, you can't do this when adding a column. SQLite's specification says you have to have a default for this.

So, to fix it our migration should be the following:

{% highlight php %}
<?php

Schema::table('organisations', function (Blueprint $table){
    $table->string('nikname')->default('default_value');
});
{% endhighlight %}

### Errors when dropping column
Here we have changed our migration:

{% highlight php %}
<?php

public function up()
{
    Schema::table('users', function (Blueprint $table){
        $table->string('nickname');
        $table->string('skype');
    });
        
}

public function down()
{
    Schema::table('organisations', function (Blueprint $table){
        $table->dropColumn('nickname');
        $table->dropColumn('skype');
    });
}
{% endhighlight %}

When rolling back this migration it will fail in the following error:

{% highlight bash %}
SQLSTATE[HY000]: General error: 1 no such column: format  
{% endhighlight %}

To fix it we can specify an array of columns when dropping:

{% highlight php %}
<?php

public function down()
{
    Schema::table('organisations', function (Blueprint $table){
        $table->dropColumn(['nickname', 'skype']);
    });
}
{% endhighlight %}
