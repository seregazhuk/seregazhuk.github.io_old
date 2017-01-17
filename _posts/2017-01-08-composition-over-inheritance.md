---
title: "Favour Composition Over Inheritance"
layout: post
tags: [PHP, OOP, SOLID]
---

There are two fundamental ways to establish relationships between class in object-orientated design: *inheritance* and *composition*.

First of all, let's determine that there is no *better* or *worse* principle in object-orientated design. But there is an *appropriate* design for the concrete task. You should use wisely both inheritance or composition, depending on the situation.

## Inheritance

The natural outcome of the inheritance is that it provides tight coupling between a parent class and any child classes. It is a hard-coded dependency via `extends` keyword. When we treat abstraction and inheritance in the correct way this will never be a problem.

The abstract parent class should provide the new data type definition. This data type should be present in all of the child instances. But very often inheritance hierarchy is based only on the purposes of the code reuse. One class is inherited from another just to gain access to the desired method. Or we create a parent class only to share this method. Other words it is a *code reuse through inheritance*.

The main problem here is in the parent class, in its interface. Inheritance, regardless of whether it's used in order to achieve code reuse or not, introduces fragility into our design. One little change to a parent class *automatically* effects all the child classes in the entire hierarchy. This change can ripple out and require many changes in many other places of the application.

Actually in a parent class the most fragile thing - is its interface. If the superclass is well-designed and defines a new data type with a clean separation of interface and implementation in the object-oriented style, any changes of the superclass shouldn't ripple at all. But if the inheritance is used to achieve a code reuse, changes in the parent class can break any code that uses this class or any of its subclasses. 

This means that all classes in the hierarchy should be *substitutable* (Liskov Substitution Principle). All the classes behave as expected. They must implement the same methods as their parent class does, taking the same kinds of arguments and return the same kinds of values. Child classes *should not* do anything that forces other collaborators to check their type in order to know how to collaborate with them. 

Child classes may *violate* their parent contract by *accepting input arguments* that have **broader restrictions** and *returning results* that have *narrower restrictions*. In this case, they can be perfectly substitutable for their parent class.

### Benefits:

- **Easy to create child classes**. Correctly designed hierarchy is easy to extend. There is an abstraction in it, and every new child class comes with a few concrete differences. In this way adding a new child class requires no changes to the existing code. 

- **Easy to change behavior**. Changes made to the methods defined at the top of the hierarchy influence all the child classes down the tree. Big changes in behavior can be achieved via small changes in the existing code.

## Composition

When your main goal is a *code reuse*, the **composition** is a better choice than inheritance. The composition provides the ability to employ switchable behaviors at *run-time*. It combines different simple, transparent and independent objects into one complex whole thing. 

In composition, the larger object is connected to its smaller parts via **has-a** relationships. The main idea here is not only that a larger object has parts, but it communicates with them via an interface. Any smaller object plays the appropriate **role** and the larger object can collaborate with any object that plays this **role**. The composed object depends on the interface of the **role**.

In terms of inheritance, with the composition the parent class becomes *backend* and the child class becomes *frontend*. With inheritance, a child class automatically inherits the implementations of all the non-private methods of its parent. Unlike the inheritance, in the composition the *frontend* class must explicitly invoke a *backend* class method with its own implementation (delegation).

With composition approach to code reuse, we have a stronger encapsulation than with inheritance. A change to a parent (backend) class doesn't break any code, that relies only on the child (frontend) class. This means that ripple effect caused by the changes in the parent (back-end) class stops at the child (frontend) class. We have structural independence, but at the cost of explict method delegation.

### Benefits:

- **Parent class interface change**. With composition, it is easier to change the parent (backend) class interface, than with inheritance. A change to backend class interface may require changes in the front-end class but doesn't require changes in its interface. Code that depends only on the front-end class continues to work. In inheritance changes in the parent class interface ripple down to all the child classes and the code that uses them.

- **Child class interface change**. With composition, it is easier to change a front-end class interface. With inheritance, we can't make changes in the child classes without checking that their new interface is compatible with the parent one. We can't in the child class override a method and return a value of different type. On the other hand, composition allows us to make changes in the front-end class without affecting back-end classes.

- **Changing behavior at a run-time**. Composition allows us to delay the creation of the back-end objects until they become required. Also, we can change them dynamically throughout the lifetime of the front-end objects in run-time. With inheritance the parent always class exists as the parent of the child class.


## Conclusion

When choosing between composition and inheritance you should always determine the *relationship between classes*. If it is **is-a** relationship, then in most cases it should be inheritance: a child class **is-a** parent class. 

Some situations require different objects to play a common **role**. In addition to the core responsibilities, they might play roles like *loggable* or *printable* or any others. There are two ways to recognize the existence of a **role**:

- when an object plays a role, it is not the object's main responsibility;
- many other unrelated objects can play this role;

Some roles have only common interfaces, others share common behaviours.

The decision between inheritance and composition lies in **is-a** versus **has-a** distinction. The more parts an object has, the more likely it should be designed with composition.

