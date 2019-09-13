#!/bin/bash

set -x

export TZ=JST-9
export WEB_CONCURRENCY=4
export USER_AGENT=$(curl -sS https://raw.githubusercontent.com/tshr20140816/heroku-mode-07/master/useragent.txt)
export PERL5LIB=/app/.apt/usr/share/perl5

if [ ! -v BASIC_USER ]; then
  echo "Error : BASIC_USER not defined."
  exit
fi

if [ ! -v BASIC_PASSWORD ]; then
  echo "Error : BASIC_PASSWORD not defined."
  exit
fi

npm update >/dev/null 2>&1 &
pear list-upgrades >/tmp/pear_upgrades 2>&1 &

find -L . -type l | grep -v usr/share/doc

grep -c -e processor /proc/cpuinfo
cat /proc/cpuinfo | head -n $(($(cat /proc/cpuinfo | wc -l) / $(grep -c -e processor /proc/cpuinfo)))

httpd -V
# httpd -M | sort
php --version
# php -i
# whereis php
# php -m
cat /proc/version
curl --version

git --version

lbzip2 --version
megals --version
parallel --version

ulimit -u
getconf ARG_MAX

hostname -A
hostname -I

htpasswd -c -b .htpasswd ${BASIC_USER} ${BASIC_PASSWORD}

dig -t txt _netblocks.google.com | grep ^[^\;] > /tmp/_netblocks.google.com.txt

pushd www
mv ical.php ${ICS_ADDRESS}.php
mv rss.php ${RSS_ADDRESS}.php
popd

fc-cache -fv > /dev/null 2>&1 &

# # For pixz
# unlink /app/.apt/usr/lib/x86_64-linux-gnu/liblzo2.so
# mv liblzo2.so.2.0.0 /app/.apt/usr/lib/x86_64-linux-gnu/
# ln -s /app/.apt/usr/lib/x86_64-linux-gnu/liblzo2.so.2.0.0 /app/.apt/usr/lib/x86_64-linux-gnu/liblzo2.so
# ln -s /app/.apt/usr/lib/x86_64-linux-gnu/liblzo2.so.2.0.0 /app/.apt/usr/lib/x86_64-linux-gnu/liblzo2.so.2

ldd bin/curl

set +x
pushd classes
for file in $(ls . | grep .php$); do
  php -l ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
pushd scripts
for file in $(ls . | grep .php$); do
  php -l ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
pushd www
for file in $(ls . | grep .php$); do
  php -l ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
pushd scripts
for file in $(ls . | grep .js$); do
  eslint ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
set -x

ncu 2>&1 | tee /tmp/ncu_result.txt

printenv | wc -c

ls -lang /tmp

wait

cat /tmp/pear_upgrades

# curl -s -m 1 --basic -u ${BASIC_USER}:${BASIC_PASSWORD} https://${HEROKU_APP_NAME}.herokuapp.com/opcache_compile_file.php
bin/curl -s -m 1 --basic -u ${BASIC_USER}:${BASIC_PASSWORD} https://${HEROKU_APP_NAME}.herokuapp.com/opcache_compile_file.php

vendor/bin/heroku-php-apache2 -C apache.conf www
