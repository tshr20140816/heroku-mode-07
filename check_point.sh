#!/bin/bash

curl -s -m 1 https://${HEROKU_APP_NAME}.herokuapp.com/check_point_${1} > /dev/null 2>&1 &
