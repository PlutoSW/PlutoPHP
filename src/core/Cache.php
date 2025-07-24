<?php

namespace Pluto\Core;

class Cache
{
    private static $cache = [];
    public static function set($key, $value)
    {
        if (\is_array($key)) {
            $key = \base64_encode(\json_encode($key));
        }
        self::$cache[$key] = $value;
    }
    public static function get($key)
    {
        if (\is_array($key)) {
            $key = \base64_encode(\json_encode($key));
        }
        return self::$cache[$key];
    }
    public static function has($key)
    {
        if (\is_array($key)) {
            $key = \base64_encode(\json_encode($key));
        }
        return isset(self::$cache[$key]);
    }
    public static function remove($key)
    {
        if (\is_array($key)) {
            $key = \base64_encode(\json_encode($key));
        }
        unset(self::$cache[$key]);
    }
    public static function clear()
    {
        self::$cache = [];
    }
}
