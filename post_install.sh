#!/bin/bash

set -x

date

curl -s -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_000 > /dev/null 2>&1 &

grep -c -e processor /proc/cpuinfo
cat /proc/cpuinfo | head -n $(($(cat /proc/cpuinfo | wc -l) / $(grep -c -e processor /proc/cpuinfo)))

# ***** XML_RPC2 *****

pear config-show
pear list-channels

time pear channel-update pear.php.net > /tmp/pear_php_net.log
cat /tmp/pear_php_net.log
is_succeeded=$(grep -c -e succeeded /tmp/pear_php_net.log)
if [ ${is_succeeded} != '0' ]; then
  pear install XML_RPC2 &
fi
# wget https://github.com/pyrus/Pyrus/blob/master/pyrus.phar
# php pyrus.phar install pear/XML_RPC2

# ***** heroku cli *****

time curl -sS -o heroku.tar.gz https://cli-assets.heroku.com/heroku-cli/channels/stable/heroku-cli-linux-x64.tar.gz
mkdir heroku
mv heroku.tar.gz ./heroku/heroku.tar.gz
pushd heroku
time tar xf heroku.tar.gz --strip-components=1
rm heroku.tar.gz
popd

# ***** phppgadmin *****

pushd www
time git clone --depth=1 -b REL_5-6-0  https://github.com/phppgadmin/phppgadmin.git phppgadmin
cp ../config.inc.php phppgadmin/conf/
# ls -lang phppgadmin
popd

mkdir lib

if [ ${is_succeeded} = '0' ]; then
  # ***** XML_RPC2 *****

  pushd lib
  git clone --depth=1 -b 1.1.4 https://github.com/pear/XML_RPC2.git .
  pushd /tmp
  mkdir pear_exception
  pushd pear_exception
  git clone --depth=1 https://github.com/pear/pear_exception.git .
  popd
  popd
  cp -af /tmp/pear_exception/* ./
  pushd /tmp
  mkdir http_request2
  pushd http_request2
  git clone --depth=1 https://github.com/pear/http_request2.git .
  popd
  popd
  cp -af /tmp/http_request2/* ./
  pushd /tmp
  mkdir net_url2
  pushd net_url2
  git clone --depth=1 https://github.com/pear/net_url2.git .
  popd
  popd
  cp -af /tmp/net_url2/* ./

  rm -f *
  ls -lang
  popd
fi

# ***** php syntax check *****

set +x
pushd classes
for file in $(ls . | grep .php$); do
  php -l ${file} 2>&1 | tee /tmp/php_error.txt
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
set -x

count1=$(grep -c 'No syntax errors detected in' /tmp/php_error.txt)
count2=$(cat /tmp/php_error.txt | wc -l)

if [ $count1 -lt $count2 ]; then
  curl -s -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/php_error_exists > /dev/null 2>&1
fi

# ***** font etc *****

# time curl -sS -O https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.4.2/phpcs.phar \
#               -O https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.4.2/phpcbf.phar \
#               -O https://oscdl.ipa.go.jp/IPAexfont/ipaexg00401.zip

cat << '__HEREDOC__' >jobs.txt
curl -sS -O https://oscdl.ipa.go.jp/IPAexfont/ipaexg00401.zip
curl -sS -L -o migu-1m.zip "https://ja.osdn.net/frs/redir.php?m=iij&f=mix-mplus-ipa/63545/migu-1m-20150712.zip"
curl -sS -O https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.4.2/phpcs.phar
curl -sS -O https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.4.2/phpcbf.phar
__HEREDOC__

time cat jobs.txt | parallel -j4 --joblog /tmp/joblog.txt 2>&1
cat /tmp/joblog.txt

mkdir .fonts
mv ipaexg00401.zip .fonts/
pushd .fonts
time unzip ipaexg00401.zip
rm ipaexg00401.zip
popd
ls -lang .fonts/

chmod 755 ./start_web.sh
chmod 755 ./bin/unrar

curl -s -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_100 > /dev/null 2>&1

date

wait

curl -s -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_200 > /dev/null 2>&1

date
