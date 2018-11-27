---
title: "Code review"
tags: [PHP, Code review, Open Source]
layout: post
---

How to organize your pull-requests and speed up code-review

Legazy code, owner of the code has changed a lot of times. Most times you have to change functionality. 
Refactoring on-the-go.

High-level view:
1. Optimize code for reading. Code is read much more often than written. 
- describe suggested changes in order to provide a context for diffs in the request.
- you should review your code by yourself before submiting the request. Review your own request as it is not yours. Sometimes you may find something you have missed. This will reduce the circles of fixing 

Diff categories:
- features. Functional changes
- structure refactoring (changes of classes, interfaces, moving methods between classes)
- simple refactoring (may be done by IDE, eg extracting variables/methods/constants, simplifing conditionals.
- renaming and moving classes (reorganizing namespaces) ?
- removing unused (dead) code
- code style fixes (eg using autowiring, removing redandunt doc-blocks)
- formatting fixes

What is important in each category. Every category values differently:
- feature changes: fulfillment of business requirements and design.
- structure refactoring: backward compatibility and design improvements
- simple refactoring: readability improvements. Because these changes are mostly may be done by IDE
- renaming/removing classes: namespaces structure
- removing unused code: backward compatibility

Categories and time spent on review:
- Feature changes - the longest, because here we have changes in domain logic, we decide whether the business problem is solved or not, we try to find the best solution to the business problem.
- structure refactoring - significantly less then feature changes. But here may be some suggestions and disagreements about the way the code should be organized.
All other categories in most cases are 99% instant merge:
- simple refactoring: the code has become more readable - merge
- renaming/moving classes, the class has been moved to a better namespace - merge
- removing unused (dead) code - merge

Why we should separate changes by categories?
- The review has to keep in mind several review strategies at once. We have already discussed that different categories are valued differently. For example in a feature changes we review business requirements, but in structure refactoring we review backward compatibility. And if we mix several categories it will be hard for reviewer to keep in mind more than one review strategy. It is hard, and chances high that the reviewer will spend more time and also may miss something. Morover any fix in a request that has mixed changes, will force the reviewer to review both categories again. For example we mix structure refactoring and business feature. Even if refactoring is done well, but there is an issue with a feature, then after fixes the reviewer has to review the whole request. That means that the review has to look through both refactoring and business feature again. As a result we have extra time spent. Because from the very beginning the refactoring part was done and it should be merged. But instead we have to review it one more time.

The worst category mixins:
- class renaming/removing and its refactoring (Git doesn't correctly understand the changes). Here I'm talking about huge changes, where a lot of lines are being changed. When you refactor a class a lot and then move it somewhere, Git will not detect moving change. Instead Git intepret these changes as deletion of one class and a creation of another one. It leads to a bunch of questions on code review. The author is being asked why he or she had written this *ugly* code, when in reality this code was just moved from one place to another with a bit of changes made inside.
- any feature + any refactoring
- any machine-made changes + any human-made changes. I mean any formatting made by IDE or code generation. For example we apply new code style and receive 3000 lines in diff. And if we mix these changes with any feature or any other human-made changes, we will force the reviewer to mentaly categorize these changes: whether it is a machine-made change and I should ignore it, or it is a human-made change and I should review it. Extra time spent.
- 
