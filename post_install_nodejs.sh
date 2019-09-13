#!/bin/bash

npm audit

curl -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_nodejs > /dev/null 2>&1
