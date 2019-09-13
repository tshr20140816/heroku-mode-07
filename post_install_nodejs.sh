#!/bin/bash

npm audit
npm outdated

curl -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_nodejs > /dev/null 2>&1
