<?php
session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
date_default_timezone_set('Europe/Istanbul');


require_once __DIR__ . '/src/autoload.php';

use Pluto\Autoloader;

Autoloader::register();

use Pluto\Core\System;
use Pluto\Core\Template\Template;

$GLOBALS["global"] = (object)[];
Template::$styles = [
    "/frontend/assets/css/style.css",
];

Template::$scripts = [
    "/frontend/assets/js/script.js",
];


System::setafterInit(function () {
    if (isset($_SESSION["language"])) {
        System::$language = new \Pluto\Core\Language($_SESSION["language"]);
    } else {
        System::$language = new \Pluto\Core\Language("tr");
    }
});


System::init();

System::$router->resolve([
    "before" => function ($router) {},
    "after" => function ($router) {}
]);

function e($key, ...$params)
{
    if (!isset(System::$language)) {
        return $key;
    }
    return System::$language->_($key, ...$params);
}
function findInArray($array, $key, $value)
{
    foreach ($array as $item) {
        if (isset($item[$key]) && $item[$key] == $value) {
            return $item;
        }
    }
    return false;
}
