---

title: "VIM: multiline edit"
layout: post
tags: [vim]

---

Vim is popular for it's rich "customization" and an endless number of commands. A nice feature that is available in SumlibeText or 
PhpStorm is **multiple cursors**. But there is no native support for this in Vim, only
a [plugin](https://github.com/terryma/vim-multiple-cursors). But as it is known nothing is impossible in Vim. 

## gn command

This command is used to operate with the matches of the current search pattern. Like **n** command tells Vim to jump to the next match,
**gn** does the same, but also it starts *visual mode* and *selects the next match*. It looks like most graphical editors work. You select 
a current match and then edit it. 

For example, **cgn** will perform a change on the next founded match. And **dgn** will delete the next match. One more nice thing about **gn**
command is that the **.(dot)** command will both move to the next match and repeat the operation we did with the previous match.

So we can combine search and **cgn** commands and then apply the **. (dot)** command to change the next match or skip it with **n**. As for
me it is even more flexible then multiple cursors.

<p class="text-center image">
<img src="/assets/images/posts/vim-multiline/cgn-edit.gif" alt="cgn-edit" class="">
</p>

Of course the result above may be achieved with universal search and replace: `:%s/login/email`, but **gn** is more flexible. It works 
with all operators: **ygn** to yank the match, **gUgn** to uppercase the match, and so on. You can skip and edit matches on the fly.
