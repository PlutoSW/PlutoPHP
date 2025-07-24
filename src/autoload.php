<?php
//autoloader class
namespace Pluto;

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    public static function autoload($class)
    {
        $class = str_replace('\\', '/', $class);
        $class = str_replace("Pluto/", "", $class);
        $class = \explode("/", $class);
        $class[0] = \strtolower($class[0]);
        $class = \implode("/", $class);
        if (!\strstr($class, 'core/')) {
            $class = '../backend/' . $class;
        }
        $file = __DIR__ . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        return false;
    }
}
