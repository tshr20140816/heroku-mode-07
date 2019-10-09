#!/bin/bash

set -x

npm audit
npm outdated

curl -s -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_nodejs >/dev/null 2>&1
