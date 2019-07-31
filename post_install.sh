#!/bin/bash

set -x

date

grep -c -e processor /proc/cpuinfo
cat /proc/cpuinfo | head -n $(($(cat /proc/cpuinfo | wc -l) / $(grep -c -e processor /proc/cpuinfo)))

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

wget https://cli-assets.heroku.com/heroku-cli/channels/stable/heroku-cli-linux-x64.tar.gz -O heroku.tar.gz
mkdir heroku
mv heroku.tar.gz ./heroku/heroku.tar.gz
pushd heroku
tar xf heroku.tar.gz --strip-components=1
popd

# ***** phppgadmin *****

pushd www
# git clone --depth 1 https://github.com/phppgadmin/phppgadmin.git phppgadmin
time git clone --depth=1 -b REL_5-6-0  https://github.com/phppgadmin/phppgadmin.git phppgadmin
cp ../config.inc.php phppgadmin/conf/
ls -lang phppgadmin
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

wget -q https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.4.2/phpcs.phar
wget -q https://github.com/squizlabs/PHP_CodeSniffer/releases/download/3.4.2/phpcbf.phar
wget -q https://oscdl.ipa.go.jp/IPAexfont/ipaexg00401.zip

mkdir .fonts
mv ipaexg00401.zip .fonts/
pushd .fonts
unzip ipaexg00401.zip
rm ipaexg00401.zip
popd
ls -lang .fonts/

chmod 755 ./start_web.sh
chmod 755 ./bin/unrar

wait

date
