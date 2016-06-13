---

title: "VIM: multiline edit"
layout: post
tags: [vim]

---

Vim is popular for it's rich "'customization'" and endless number of commands. A nice feature that is available in SumlibeText or 
PhpStorm is **multiple cursors**. But there is no native support for this in vim, only
a [plugin](https://github.com/terryma/vim-multiple-cursors). But as it is known there is nothing imposiible in vim. 

## gn command
This command is used to operate with the mathes of the current search pattern. **cgn** will perform a change on the next founded match.
So we can combine search and **cgn** commands and then apply the **. (dot)** command to change the next match or skip it with **n**. As for
me it is even more flexible then multiple cursors.

<div class="text-center">
    <img src="/assets/images/posts/vim-multiline/cgn-edit.gif" alt="cgn-edit">
</div>
