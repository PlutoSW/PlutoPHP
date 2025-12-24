<?php
date_default_timezone_set('Europe/Istanbul');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/core/bootstrap.php';

$app = new Pluto\Application();
$app->run();
