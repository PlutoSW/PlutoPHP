<?php

namespace Pluto;

/**
 * PlutoPHP Memcached Wrapper
 */
class Cache
{
    private static ?\Memcached $instance = null;

    private static function getMemcached(): \Memcached
    {
        if (self::$instance === null) {
            self::$instance = new \Memcached();
            $host = getenv('MEMCACHED_HOST') ?: '127.0.0.1';
            $port = getenv('MEMCACHED_PORT') ?: 11211;
            self::$instance->addServer($host, (int)$port);
        }
        return self::$instance;
    }

    public static function allKeys(): array
    {
        return self::getMemcached()->getAllKeys() ?: [];
    }

    public static function get(string $key)
    {
        if (\str_contains($key, "*")) {
            $keys = self::allKeys();
            $matchingKeys = [];
            $regexPattern = '/^' . $key . '.*/';

            if ($keys !== false) {
                foreach ($keys as $item) {
                    if (preg_match($regexPattern, $item)) {
                        $matchingKeys[] = $item;
                    }
                }
            }
            return self::getMemcached()->getMulti($matchingKeys);
        }
        return self::getMemcached()->get($key);
    }

    public static function set(string $key, $value, int $expiration = 3600): bool
    {
        return self::getMemcached()->set($key, $value, $expiration);
    }


    public static function remember(string $key, int $expiration, \Closure $callback)
    {
        $value = self::get($key);
        if (self::getMemcached()->getResultCode() !== \Memcached::RES_NOTFOUND) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $expiration);

        return $value;
    }

    public static function forget(string $key): bool
    {
        if (\str_contains($key, "*")) {
            $keys = self::allKeys();
            $regexPattern = '/^' . $key . '.*/';

            if ($keys !== false) {
                foreach ($keys as $item) {
                    if (preg_match($regexPattern, $item)) {
                        self::getMemcached()->delete($item);
                    }
                }
            }
        }
        return self::getMemcached()->delete($key);
    }
}
