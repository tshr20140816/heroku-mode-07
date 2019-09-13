#!/bin/bash

set -x

cd ~
touch .netrc

echo "machine github.com" >> .netrc
echo "login username" >> .netrc
echo "password xxxxxxx" >> .netrc

git config --global user.email "user"
git config --global user.name "user"

cd /tmp

git clone --depth=1 https://github.com/tshr20140816/heroku-mode-07.git
git clone --depth=1 https://github.com/tshr20140816/heroku-mode-09.git

diff heroku-mode-07/composer.json heroku-mode-09/composer.json
diff heroku-mode-07/composer.lock heroku-mode-09/composer.lock
# diff heroku-mode-07/package.json heroku-mode-09/package.json
