<?php

$data = ['type' => 'line',
         'data' => ['datasets' => ['data' => [1, 2, 3, 3, 2, 1]],
                   ],
         'options' => [],
        ];

exec('node ../scripts/20190730.js 800 400 ' . base64_encode(json_encode($data)));

header('Content-Type: image/png');

echo file_get_contents('/tmp/testimage.png');
