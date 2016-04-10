---
layout: post
title:  "Errors after npm update"
categories: npm
---

I have recently installed node.js from <a href="https://nodejs.org" target="_blank">official website</a> via package installer on my Mac. I've run into an issue though, after running ``npm update -g`` it brokes npm. The funny thing is that node.js works. But npm show errors:
``npm: command not found``.

After research on github and stackoverflow issues I've found that on Mac it's better idea to install nodejs via homebrew.
The following steps solve this problem:

1. brew uninstall node
2. brew update
3. brew upgrade
4. brew cleanup
5. brew install node
6. sudo chown -R $(whoami) /usr/local (for issues with permissions)
7. brew link --overwrite node
8. brew postinstall node

And then everything work fine.
