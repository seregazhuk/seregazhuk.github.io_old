---
title: "Code review"
tags: [PHP, Code review, Open Source]
layout: post
---

How to organize your pull-requests and speed up code-review.

## Categories of changes

Let's imagine that you have a task, a new feature in the project. The code you are working on may contain different types or categories of changes. Of course you have some new code, that represents a feature itself. But, while working on it you may notice that some code should be refactored in order the feature to fit in. Or with this new feature the code now contains a duplication and you want to extract it out. Or you have suddenly found a bug and want to fix. So, how should the final request look like? 

First of all lets review what kinds of changes the code may have:

- Features (functional changes).
- Structure refactoring (changes of classes, interfaces, moving methods between classes).
- Simple refactoring (which may be done by your IDE, e.g. extracting variables/methods/constants, simplifying conditionals).
- Renaming and moving classes (reorganizing namespaces).
- Removing unused (dead) code.
- Code style fixes (e.g. using autowiring, removing redundant doc-blocks).
- Formatting fixes.

## Review strategies

It is very important to understand that each of these categories values differently. The reviewer should pay attention to different things when reviewing each of them. We may say that each category of changes has its own review strategy:

- Feature changes: fulfillment of business requirements and design.
- Structure refactoring: backward compatibility and design improvements.
- Simple refactoring: readability improvements. Because these changes are mostly may be done by IDE.
- Renaming/removing classes: whether the namespaces structure has become better.
- Removing unused code: backward compatibility

Because different categories have different review strategy it means that the time being spent on them also differs:

Feature changes. The longest one, because here we have changes in a domain logic. The reviewer decides whether the business problem is solved or not. The reviewer also checks whether the suggested solution is the best one or it can be improved.

Structure refactoring. Significantly shorter then feature changes. But here may be some suggestions and disagreements about the way the code should be organized.

All other categories in most cases are 99% instant merge:
- Simple refactoring. The code has become more readable? - merge
- Renaming/moving classes. The class has been moved to a better namespace? - merge
- Removing unused (dead) code - merge

## Why should we separate changes by categories?

The reviewer has to keep in mind several review strategies at once. We have already discussed that different categories are valued differently. For example in feature changes we review business requirements, but in structure refactoring we review backward compatibility. And if we mix several categories it will be hard for reviewer to keep in mind more than one review strategy. And most likely the reviewer will spend more time than necessary on the request and thus may miss something. Moreover any fix in a request that has mixed changes from different categories, will force the reviewer to review both categories again. For example we mix structure refactoring and business feature. Even if refactoring is done well, but there is an issue with a feature, then after fixes the reviewer has to review the whole request from the very beginning. That means that the review has to look through both refactoring and the business feature again. As a result we have extra time spent. Instead of having a separate request with refactoring that could already have been merged the reviewer has to review this code one more time.

## The worst category mixins

Class renaming/removing and its refactoring. Here we have to deal with Git which doesn't correctly understand such changes. Here I'm talking about huge changes, where a lot of lines are being changed. When you refactor a class and then move it somewhere, Git will not detect a moving change. Instead Git will interpret these changes as deletion of one class and a creation of another one. It leads to a bunch of questions on code review. The author is being asked why he or she had written this *ugly* code, when in reality this code was just moved from one place to another with a bit of changes made inside.

Any feature + any refactoring. We have already discussed this mixin above. It forces the reviewer to keep in mind two review strategies. Even if the refactoring is done well, we can't merge it until the feature is being approved.

Any machine-made changes + any human-made changes. Here by "machine-made changes" I mean any formatting made by IDE or code generation. For example we apply new code style and receive 3000 lines in a diff. And if we mix these changes with any feature or any other human-made changes, we will force the reviewer to mentally categorize these changes: whether it is a machine-made change and I should ignore it, or it is a human-made change and I should review it. As a result extra time spent by a reviewer. 

## Example

Here I have a pull request with a feature of implementing a method that gracefully closes a client connection: 

<p class="text-center image">
    <img src="/assets/images/posts/code-review/all-commits.png">
</p>

As you can see by its commits it contains different categories of changes:
- feature (new code)
- refactoring (creation/moving classes)
- code style fixes (removing redundant doc blocks)

The whole request is more than one hundred lines of code, while the feature itself is just 10 lines:

<p class="text-center image">
    <img src="/assets/images/posts/code-review/end-method.png">
</p>

As a result the review has to look through all the code and:
- check that the refactoring is OK
- check that the feature was implemented
- detect whether it was an automatic IDE change or a human-made change

So, it's hard to review such a request. To fix it we can break these commits into separate branches and create a pull request for each branch.

1. Refactoring: extracting a requests pool:

<p class="text-center image">
    <img src="/assets/images/posts/code-review/extract-pool.gif">
</p>

Just two files here. The reviewer has to check just a new design. If everything is OK - merge.

2. The next step is also a refactoring, we just move to classes to a new namespace. 

<p class="text-center image">
    <img src="/assets/images/posts/code-review/move-namespace.gif">
</p>

This request is pretty simple to review and can be instantly merged.

3. Removing redundant doc blocks

<p class="text-center image">
    <img src="/assets/images/posts/code-review/doc-blocks.gif">
</p>

Instant merge.

4. The feature itself

<p class="text-center image">
    <img src="/assets/images/posts/code-review/feature.gif">
</p>

And now the "feature" request contains only "feature" code. So, the reviewer can concentrate only on it. The request is small and easy to review. 


## Conclusion

Don't create huge pull-requests with mixed categories of changes. The bigger the request is, the harder it is for reviewer to understand your suggested changes. It most likely that a huge request with hundreds lines of code will be rejected. Instead, break it down into small logic pieces. If your refactoring is OK, but the feature has bugs, than the refactoring can already be merged. So, you and reviewer can concentrate on a feature bug instead of reviewing the whole code from the very beginning.

And always follow these steps before submitting a pull-request:

- Optimize code for reading. Code is read much more often than it is written. 
- Describe suggested changes in order to provide a context for diffs in the request.
- You should review your code by yourself before submitting the request. Review your own request as it is not yours. Sometimes you may find something you have missed. This will reduce the circles of rejecting/fixing.
