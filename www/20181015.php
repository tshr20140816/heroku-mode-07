<?php

$year = date('%Y');

for ($i = 0; $i < 30; $i++) {
  $timestamp = strtotime('+' . ($i + 80) . ' days');
  $d = date('j', $timestamp);
  error_log($d);
}

?>
