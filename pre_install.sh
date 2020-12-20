#!/bin/bash

set -x

start_time=$(date +%s)

date -d '9 hours'

finish_time=$(date +%s)

elapsed_time=$((finish_time - start_time))

echo Elapsed ${0} ${elapsed_time}s
