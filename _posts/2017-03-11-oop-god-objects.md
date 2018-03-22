---
title: "OOP Design Antipattern: God Object"
tags: [OOP, PHP]
layout: post
description: "Object-oriented design antipattern God Object in PHP"
---

*God Object* means an object that does too much or knows too much. To be more detailed this situation occurs when an object has knowledge of many different large parts of the system, it has an influence on them, the same way these large parts have to look to the *God Object* for some data or processing.

It often happens with `User` class, that often becomes a monster class. 

{% highlight php %}
<?php

class User
{
    public function login($email, $password) {
        $user = $this
            ->where('email', $email)
            ->get()
            ->first();

        return password_verify($password, $user->password);
    }

    public function register($email, $password) {
        // some validation
        self::create([
            'email' => $email,
            'password' => $password
        ]);
    }
}
{% endhighlight %}

While application grows this class also keeps growing and growing. Our `User` class has to deal with balance, profile, payments and so on. And after some months of maintaining our application, we end up a monstrous class with thousands and thousands of lines of code. Do you need registration? We add it to `User` class. Need to restore password? `User` class has a method for it. Do you want to process a payment? I think you know where to find this method.

{% highlight php %}
<?php

class User
{
      public function login($email, $password) {
        $user = $this
            ->where('email', $email)
            ->get()
            ->first();

        return password_verify($password, $user->password);
    }

    public function register($email, $password) {
        // some validation
        self::create([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }  

    public function restorePassword($email) {
        // validation
        $token = $this->createPasswordToken($email);
        self::update([
            'reset_token' => $token,
        ]);

        $this->email->send(
            $email, 'Password restore', 'views/auth/restorePassword', ['token' => $token]
        )
    }

    public function getAddressString() {
        $address  = [
            $this->profile->city,
            $this->profile->street,
            $this->profile->house,
            $this->profile->apartment,
        ];

        return implode(" ", $address);
    }

    public function getFullName() {
        return $this->profile->second_name . ' ' . $this->profile->first_name;
    }

    public function addBalance($sum) {
        return $this->getBalance()->addSum($sum);
    }

    public function getBalance() {
        return $this->getBalance()->sum;
    }

    public function getBillingStatistics() {
        return BillingHistory::where('user_id', $this->id)
            ->groupBy('created_at')
            ->get();
    }

    public function getPaymentsStatistics() {
        return Payments::where('user_id', $this->id)
            ->groupBy('created_at')
            ->get();
    }
}
{% endhighlight %}

But why is it wrong? It looks very nice that you have access to all these pretty methods from one place. But the flip side of this coin is that the user object causes things to happen in very different parts of the system. So, when we make some changes in this class there is a very big chance for bugs to appear and it will be very hard to find out the cause of them.  

How to fix this issue? By refactoring, extracting parts of its methods into other classes. Fields and parameters that are used together might move into a new class.

Let's have a closer look to our `User` class. We have some groups of methods that use only a small bunch of fields. 

*Balance* methods:

{% highlight php %}
<?php
public function addBalance($sum) {
    return $this->getBalance()->addSum($sum);
}

public function getBalance() {
    return $this->getBalance()->sum;
}
{% endhighlight %}

*Profile* methods:

{% highlight php %}
<?php

public function getAddressString() {
    $address  = [
        $this->profile->city,
        $this->profile->street,
        $this->profile->house,
        $this->profile->apartment,
    ];

    return implode(" ", $address);
}

public function getFullName() {
    return $this->profile->second_name . ' ' . $this->profile->first_name;
}
{% endhighlight %}

And *statistics* methods:

{% highlight php %}
<?php

public function getBillingStatistics() {
    return BillingHistory::where('user_id', $this->id)
        ->groupBy('created_at')
        ->get();
}

public function getPaymentsStatistics() {
    return Payments::where('user_id', $this->id)
        ->groupBy('created_at')
        ->get();
}
{% endhighlight %}

All of these methods groups can be extracted to their own classes: *Balance*, *Profile* and *UserStatistics*:

{% highlight php %}
<?php 

class Balance 
{
    public function addSum($sum) {
        $this->sum += $sum;
        return $this->save();
    }

    public function getBalance() {
        return $this->sum;
    }
}

class Profile 
{
    public function getAddressString() {
        $address  = [
            $this->city,
            $this->street,
            $this->house,
            $this->apartment,
        ];

        return implode(" ", $address);
    }

    public function getFullName() {
        return $this->second_name . ' ' . $this->first_name;
    }
}

class UserStatistics 
{
   public function getBillingStatistics() {
        return BillingHistory::where('user_id', $this->userId)
            ->groupBy('created_at')
            ->get();
    }

    public function getPaymentsStatistics() {
        return Payments::where('user_id', $this->userId)
            ->groupBy('created_at')
            ->get();
    } 
}
{% endhighlight %}

Now we have a set of small objects, each of them has its own responsibility.

In terms of object-orientated design, *God Objects* violate Single Responsibility Principle because they have too many responsibilities and reasons to be changed. They also violate the [Law of Demeter]({% post_url 2016-12-04-tell-dont-ask %}), as they often have long methods chains to get access for required methods or properties.
