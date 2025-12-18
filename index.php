<?php
date_default_timezone_set('Europe/Istanbul');

require_once __DIR__ . '/core/bootstrap.php';

$app = new Pluto\Application();
$app->run();