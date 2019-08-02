#!/bin/bash

set -x

export TZ=JST-9

if [ ! -v BASIC_USER ]; then
  echo "Error : BASIC_USER not defined."
  exit
fi

if [ ! -v BASIC_PASSWORD ]; then
  echo "Error : BASIC_PASSWORD not defined."
  exit
fi

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

getconf ARG_MAX

echo "$(httpd -v)" > /tmp/apache_current_version
echo "$(php -v | head -n 1)" > /tmp/php_current_version
echo "$(curl -V | head -n 1)" > /tmp/curl_current_version

# if [ $(date +%-M) -lt 10 ]; then
  # heroku-buildpack-php
#   composer update > /dev/null 2>&1 &
# fi

export USER_AGENT=$(curl https://raw.githubusercontent.com/tshr20140816/heroku-mode-07/master/useragent.txt)

htpasswd -c -b .htpasswd ${BASIC_USER} ${BASIC_PASSWORD}

dig -t txt _netblocks.google.com | grep ^[^\;] > /tmp/_netblocks.google.com.txt

pushd www
mv ical.php ${ICS_ADDRESS}.php
mv rss.php ${RSS_ADDRESS}.php
popd

fc-cache -fv > /dev/null 2>&1 &

set +x
pushd classes
for file in $( ls . | grep .php$ ); do
  php -l ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
pushd scripts
for file in $( ls . | grep .php$ ); do
  php -l ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
pushd www
for file in $( ls . | grep .php$ ); do
  php -l ${file} 2>&1 | tee -a /tmp/php_error.txt
done
popd
set -x

export WEB_CONCURRENCY=3

printenv | wc -c

ls -lang /tmp

wait

curl -s -m 1 --basic -u ${BASIC_USER}:${BASIC_PASSWORD} https://${HEROKU_APP_NAME}.herokuapp.com/opcache_compile_file.php

vendor/bin/heroku-php-apache2 -C apache.conf www
