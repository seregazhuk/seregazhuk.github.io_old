---

title: "GIT: Some Advanced Usage"
layout: post
tags: [git]

---

## Interactive rebase

You may probably want to change commits on the branch, you are currently woring on. For example, you want
to change commit message, or split some commits:

{% highlight bash %}
~$git rebase -i HEAD~3
{% endhighlight %}

Here we want to change last four commiths (`HEAD~3`). After this command opens an editor with the rebase script. But before 
we continue, behind the scenes git will move our four commits to a temporary directory. Then it runs all commands from the
rebase script. So let's no have a look at the rebase script:

{% highlight bash %}
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

{% highlight bash %}
~$git filter-branch --tree-filter 'rm -f tests/_data/dump.sql' -- --all
{% endhighlight %}

In the command above `-- --all` means filter *all commits* in *all branches*.

If our repo is too large, we can use `--index-filter` and git will run our shell command on each commit only on staged files.

Notice, that if the specified command will fail the filter will stop.

Before running `filter-branch` for the second time, we must use `-f` flag, to force the command. Because git has created 
a backup of the repo and `force` will override it. After clearing history some commits may become empty. We can clear our
repo from them with 

{% highlight bash %}
~$git filter-branch -f --prune-empty -- --all
{% endhighlight %}


## Restore Data

### Commits

In our repo we have some sort of commits history:

{% highlight bash %}
~$git log --oneline

59e5b5f feature_#2
56wcf1q bug fix in feature_#1
c734020 feature_#1
{% endhighlight %}

And we want to move back to bug fix: `git reset --hard 56wcf1q`. But some moments later, we have understood
that it was a mistake. How do we restore a *feature_#2* commit?. Of course, now there is no *feature_#2* commit in our log:

{% highlight bash %}
~$git log --oneline
56wcf1q bug fix in feature_#1
c734020 feature_#1
{% endhighlight %}


But git **never** removes commits and it has a special *reflog*, which is available only in your local repo. If we type this command:

{% highlight bash %}
~$git reflog --oneline

56wcf1q HEAD@{0}: reset: moving to 56wcf1q 
59e5b5f HEAD@{1}: commit: feature_#2
56wcf1q HEAD@{1}: commit: bug fix in feature_#1
c734020 HEAD@{2}: commit: feature_#1

{% endhighlight %}

it will show a removed commit. This command shows a list of *HEAD* commits: where the *HEAD* has been pointing at each change.
Our removed commit is now like an orphan, it isn't attached to any branch. To move it back we can use `git reset --hard 59e5b5f`.
Or we can use a shortcut `git reset --hard HEAD@{1}` instead of a hash. Now our commit has come back:

{% highlight bash %}
~$git log --oneline

59e5b5f feature_#2
56wcf1q bug fix in feature_#1
c734020 feature_#1
{% endhighlight %}


### Branches

Some day we go and delete a branch:

{% highlight bash %}
~$git branch -D feature_#1 
{% endhighlight %}

And then we remember, that we haven't merged it into our *master* branch. So, it's time to restore it. As we remember git **never**
deletes commits. It has removed a branch but commits still exist. Now we must find the latest commit from the removed branch and
create a new branch, that will point to this commit. As in the previous example, we can use `git reflog` command to find the
needed commit. And then we just create a new branch, that points to this commit:

{% highlight bash %}
~$git branch feature_#1 136ed
~$git checkout feature_#1
{% endhighlight %}

## Cherry picking

When do you need cherry picking? It may be useful when we want some piece of the functionality to be moved to our branch 
from another one. And this code exists in one commit. So, in other words, we want to move a commit from one branch to another.

Here are our steps:

1. Checkout the branch where we need to put a commit: `git checkout master`
2. Use this command with the hash of the required commit: `git cherry-pick q19dqe3`
3. Optionally you can change a commit message: `git cherry-pick --edit q19dqe3`. This will open an editor for changing a
commit message.

Notice, that not the hash of the commit in the `master` branch has been changed. That happens because these commits have 
different parents.

It is possible to cherry pick several commits into one: `git cherry-pick --no-commit q19dq3 fs41t92`.
This command takes specified commits and applies them to the current HEAD. But it doesn't make any commits on the current
branch. Then we need to commit applied changes into their own commit.
