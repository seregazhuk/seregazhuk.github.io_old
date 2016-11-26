---
title: "Zsh Keyboard Shortcuts"
layout: post
tags: [Zsh, Terminal]
---
As a professional developer, you should effectively use your terminal. When you type a command in the terminal and make a typo at the beginning of it, what should you do? Retype the command again? Then use the arrow keys to move cursor character by character, just to correct one typo at the other end of the line. So, it sounds not great.

<p class="text-center image">
    <img src="/assets/images/posts/zsh-shortcuts-command/zsh-typo.gif" alt="cgn-edit" class="">
</p>

The best way to handle such situations is to use keyboard shortcuts. Here are the most useful:

{:.table}
|Shortcut|Action|
|---|---|
|`CTRL + A`|**Move** to the **beginning of the line**|
|`CTRL + E`|**Move** to the **end of the line**|
|`CTRL + left arrow`|**Move** one **word back**|
|`CTRL + right arrow`|**Move** one **word forward**|
|`CTRL + R`|**Search** in history|
|`CTRL + G`|**Escape** from search history|
|`CTRL + W`|**Delete** the word **before** the cursor|

{:.table}
|Command|Action|
|---|---|
|`!!`|**Execute last command** in history|

## History Search

<p class="text-center image">
    <img src="/assets/images/posts/zsh-shortcuts-command/zsh-search.gif" alt="cgn-edit" class="">
</p>

After pressing `CTRL + R` appears a *search menu*. We can type everything to search throug our commands history. After finding the required command we can press `ENTER` to execute it. Or press `CTRL + G` to escape from search.



