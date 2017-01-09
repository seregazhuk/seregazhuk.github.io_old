---
title: "Favour Composition Over Inheritance"
layout: post
tags: [PHP, OOP, SOLID]
---

There are two fundamental ways to establish relationships between class in object-orientated design: *inheritance* and *composition*.

First of all, let's determine that there is no *better* or *worse* principle in object-orientated desing. But there is an *appropriate* design for the concrete task. You should use wisely both inheritance or composition, depending on the situation.

## Inheritance

The natural outcome of the inheritance is that it provides tight coupling between a parent class and any child classes. It is a hard-coded dependency via `extends` keyword. When we treat abstraction and inheritance in the correct way this will never be a problem.

The abstract parent class should provide the new data type definition. This data type should be present in all of the child instances. But very often inheritance hierarchy is based only on the purposes of the code re-use. One class is inherited from another just to gain access to a desired method. Or we create a parent class only to share this method. Another words it is a *code re-use through inheritance*.

The main problem here is in the parent class, in its interface. Inheritance, regardless of whether it's used in order to achieve code re-use or not, introduces fragility into our design. One little change to a parent class *automatically* effects all the child classes in the entire hierarchy. This change can ripple out and require many changes in many other places of the application.

Actually in a parent class the most fragile thing - is its interface. If the superclass is well-designed and defines a new data type with a clean separation of interface and implementation in the object-oriented style, any changes of the superclass shouldn't ripple at all. But if the inheritance is used to achieve a code re-use, changes in the parent class can 