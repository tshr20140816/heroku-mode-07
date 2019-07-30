<?php

exec('node ../scripts/20190730.js');

header('Content-Type: image/png');

echo file_get_contents('/tmp/testimage.png');
