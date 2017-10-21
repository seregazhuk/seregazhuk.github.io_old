---
title: "PHP7: Exceptions And Errors Handling"
layout: post
description: "PHP 7: Handling Errors and Exceptions, Throwable interface"
tags: [PHP]
---

In the previous versions of PHP, there was no way to handle fatal errors in your code. Setting the global error handler with `set_error_handler()` function doesn't help, the script execution will be halted. This happens because of the engine. Fatal and recoverable fatal errors have been *raised* (like warnings or deprecations). But exceptions are thrown. This is the main difference from the script execution point of view.

In PHP5 there we 16 different types of errors:

{% highlight php %}
// Fatal errors
E_ERROR
E_CORE_ERROR
E_COMPILE_ERROR
E_USER_ERROR

// Recoverable fatal errors
E_RECOVERABLE_ERROR
// Parse error
E_PARSE

// Warnings
E_WARNING
E_CORE_WARNING
E_COMPILE_WARNING
E_USER_WARNING

// Others
E_DEPRECATED
E_USER_DEPRECATED
E_NOTICE
E_USER_NOTICE
E_STRICT
{% endhighlight %}

The first four errors are fatal, they halt the script execution and don't invoke the error handler. The `E_RECOVERABLE_ERROR` behaves like a fatal error, but it invokes the error handler. There are some issues with this model of fatal errors:

- they cannot be normally handled (error handler is not called)
- the `finally` block will not be invoked
- destructors are not called

The solution with these issues comes with using exceptions. Now, in PHP7 when a fatal or recoverable fatal error (`E_ERROR` and `E_RECOVERABLE_ERROR`) occurs a special exception will be thrown, rather than halting a script:

{% highlight php %}
<?php
// PHP 5+
$obj = 'foo';
$obj->method();

// Fatal error: Call to a member function method() on a non-object
{% endhighlight %}

{% highlight php %}
<?php
// PHP7
try {
    $obj = 'foo';
    $obj->method();
} catch(Error $e) {
    var_dump($e);
}

/*
class Error#1 (8) {
  protected $message =>
  string(44) "Call to a member function method() on string"
  private $string =>
  string(0) ""
  protected $code =>
  int(0)
  protected $file =>
  string(14) "php shell code"
  protected $line =>
  int(3)
  private $trace =>
  array(0) {
  }
  private $previous =>
  NULL
}
*/
{% endhighlight %}

There are five pre-defined sub-classes of `Error` base class:

- `TypeError` - an argument doesn't match the required type hint
- `ParseError` - `eval` fails to parse the given code
- `AssertionError` - an assertion fails (`assert(...)`)
- `ArithmeticError` - error during a mathematical operation
- `DivizionByZeroError` - a sub-class of `ArithmeticError` when dividing by 0

Here is a full hierarchy of exceptions in PHP7:

{% highlight php %}

interface Throwable
    |- Exception implements Throwable
        |- Other Exception classes
    |- Error implements Throwable
        |- TypeError extends Error
        |- ParseError extends Error
        |- AssertionError extends Error
        |- ArithmeticError extends Error
            |- DivizionByZeroError extends ArithmeticError

{% endhighlight %}

## Throwable Interface
All exceptions and errors in PHP7 implement `Throwable` interface:

{% highlight php %}
<?php

interface Throwable
{
  public function getMessage(): string;
  public function getCode(): int;
  public function getFile(): string;
  public function getLine(): string;
  public function getTrace(): array();
  public function getTraceAsString(): string;
  public function getPrevious(): Throwable;
  public function __toString():
}
{% endhighlight %}

This interface specifies methods that look identical to those of `Exception`. The only difference is that `Throwable::getPrevious()` method can return any instance of `Throwable` and not only `Exception`. The constructors of `Exception` and `Error` accept an instance of `Throwable` as the previous exception.

Because both `Error` and `Exception` objects share the common interface, we can catch them:

{% highlight php %}
<?php

try {
  // some code
} catch (Exception $e) {
  // handle Exception
} catch (Error $e) {
  // handle Error
}
{% endhighlight %}

In some situations, we can catch any exceptions and errors. For example logging or framework error handling:

{% highlight php %}
<?php

try {
  // Some code
} catch (Throwable $e) {
  // ...
}
{% endhighlight %}

User defined classes cannot implement `Throwable` interface, they should extend from either `Error` or `Exception` classes. This was made for consistency: only instances of `Exception` or `Error` may be thrown.

In our packages, we can define package-specific interfaces by extending `Throwable` interface. A class can implement extended `Throwable` interface only if it extend either `Exception` or `Error`:

{% highlight php %}
<?php

interface PackageCustomThrowable extends Throwable{}

class PackageCustomException extends Exception implements PackageCustomThrowable{}
{% endhighlight %}

## Error

In PHP7 fatal errors and recoverable fatal errors throw instances of `Error` class, which implements `Throwable` interface and can be caught using a `try/catch` block:

{% highlight php %}
<?php

try {
  10%0;
} catch(Error $e) {
  // handle error
}
{% endhighlight %}

There are several specific subclasses of the base `Error` class: `TypeError`, `ParseError`, `ArtihmeticError`, and `AssertionError`.

## TypeError
This error is thrown in two different scenarios:
- a function argument or return value doesn't match a declared type hint
- an invalid number of arguments is passed to a built-in PHP function (*strict mode* only)

{% highlight php %}
<?php

function sum(int $a, int $b) {
  return $a + $b;
}

try {
  $result = add('a', 'b');
} catch (TypeError $e) {
  echo $e->getMessage(), "\n";
}
{% endhighlight %}

## ParseError
`ParseError` is thrown when there is a syntax error in `include/require` file or it occurs while parsing `eval()` function content:

{% highlight php %}
<?php

try {
  require 'file-with-syntax-error.php'
} catch (ParseError $e) {
  echo $e->getMessage(), "\n";
}
{% endhighlight %}

## ArithmeticError
`ArithmeticError` is thrown when there is an error while performing mathematical operations: 
- shifting by a negative value
- call to `intdiv()` that would result in a value outside the possible bounds of an integer

{% highlight php %}
<?php

try {
  $result = 1 << -1;
} catch (ArithmeticError $e) {
  echo $e->getMessage(), "\n";
}
{% endhighlight %}

## DivisionByZeroError
`DivisionByZeroError` is thrown when an attempt is made to divide a number by zero: 
- from intdiv() when the denominator is zero 
- when zero is used as the denominator with the modulo (%) operator.

Note that on division by zero 1/0 and module by zero 1%0 an `E_WARNING` is triggered first (probably for backward compatibility with PHP5), then the `DivisionByZeroError` exception is thrown next.

{% highlight php %}
<?php

try {
  $result = 1 % 0;
} catch (DivisionByZeroError $e) {
  echo $e->getMessage(), "\n";
}
{% endhighlight %}

## AssertionError

`AssertionError` is thrown when an assertion made via `assert()` fails:

{% highlight php %}
<?php

ini_set('zend.assertions', 1); // execute assertions
ini_set('assert.exception', 1); // throw exception when assertion fails

$value = 1;

assert($value === 0);

// Fatal error: Uncaught AssertionError: assert($value === 0)
{% endhighlight %}

## Cathing Errors
You should avoid catching `Error` objects unless logging them for the future solution. Because `Error` always points to code problems, not some temporary runtime issues. It is better to fix such problems instead of handling them at runtime. In general, `Error` objects should be caught for logging and for performing any necessary cleanup.

## Multi-Catch Exception Handling
In PHP7.1 when several different types of exceptions are handled the same way, we can use multi-catch instead of duplication of `catch` statements:

{% highlight php %}
<?php

try {
  // ... code
} catch(ExceptionType1 $e) {
  // ... Handle exception 
} catch(ExceptionType2 $e) {
  // ... Same code to handle exception
}
{% endhighlight %}

In PHP7.1 we can use a single `catch` statement to avoid code duplication:

{% highlight php %}
<?php
try {
  // ... code
} catch(ExceptionType1 | ExceptionType2 $e) {
  // ... Handle exception
} catch(\Exception $e) {
  // ... 
}
{% endhighlight %}
