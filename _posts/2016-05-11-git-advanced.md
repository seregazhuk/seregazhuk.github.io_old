---

title: "GIT: Advanced tips"
layout: post
tags: [git]

---

## Interactive rebase

You may probably want to change commits on the branch, you are currently woring on. For example, you want
to change commit message, or split some commits:

{% highlight python %}
$git rebase -i HEAD~3
{% endhighlight %}

Here we want to change last four commiths (`HEAD~3`). After this command opens an editor with the rebase script. But before 
we continue, behind the scenes git will move our four commits to a temporary directory. Then it runs all commands from the
rebase script. So let's no have a look at the rebase script:

{% highlight python %}
pick 12ea4612 Bug fix 
pick gf141es1 New feature
{% endhighlight %}

Notince, that commits in the rebase script are shown from the first commit to the latest one. For example, in `git log` command
the commits are shown from the oldest to the newest. After saving the rebase script, commits from the temporary foled
will be applied in the order we have specified.

### Split commits

If we want to split a commit into two commits, we can use interactive rebase. 
1. `edit` command. At first, put the `edit` command for the 
required commit in the rebase script. Then, when we save and exit the rebase script, git will run all the commits, and stops
at the `edit` command waiting for the prompt. 
2. Undo last changes in the working directory: `git reset HEAD^`. So our files become unstaged:
3. Add the files to the stage: `git add`
4. Commit the files: `git commit -m`
5. Repeat with the other files.
6. Continue to rebase with `git rebase --continue`.

## Clear history

Let's imagine that you have committed a large dump for your tests in a repository. Then you have done some 
changes in it and also have committed them. So our repository has become too large. How to change it? First of 
all, before any movements make a backup: `git clone our-repo`.

To change a history call: `git filter-branch --tree-filter <shell command>`. It will check out each commit from the
working directory, run the specified `<shell command>` and then recommit the files:

{% highlight python %}
$git filter-branch --tree-filter 'rm -f tests/_data/dump.sql' -- --all
{% endhighlight %}

In the command above `-- --all` means filter *all commits* in *all branches*.

If our repo is too large, we can use `--index-filter` and git will run our shell command on each commit only on staged files.

Notice, that if the specified command will fail, the filter will stop.

Before running `filter-branch` for the second time, we must use `-f` flag, to force the command. Because git has created 
a backup of the repo, and `force` will override it. After clearing history some commits may become empty. We can clear our
repo from them with 

{% highlight python %}
$git filter-branch -f --prune-empty -- --all
{% endhighlight %}
