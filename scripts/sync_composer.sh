#!/bin/bash

set -x

cd ~

php -d apc.enable_cli=1 -d include_path=.:/app/.heroku/php/lib/php:/app/lib ./make_netrc.php

git config --global user.email "user"
git config --global user.name "user"

cd /tmp

git clone --depth=1 https://github.com/tshr20140816/heroku-mode-07.git
git clone --depth=1 https://github.com/tshr20140816/heroku-mode-09.git

update_flag=0

size=$(diff heroku-mode-07/composer.json heroku-mode-09/composer.json | wc -c)
if [ $size -gt 0 ]; then
    cp -f heroku-mode-07/composer.json heroku-mode-09/composer.json
    update_flag=1
fi
size=$(diff heroku-mode-07/composer.lock heroku-mode-09/composer.lock | wc -c)
if [ $size -gt 0 ]; then
    cp -f heroku-mode-07/composer.lock heroku-mode-09/composer.lock
    update_flag=1
fi

cd heroku-mode-09

if [ $update_flag -eq 1 ]; then
    git commit -a -m autoupdate
    git push origin master
fi
