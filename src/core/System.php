<?php

namespace Pluto\Core;

require_once __DIR__ . '/../libraries/mailer/class.phpmailer.php';
require_once __DIR__ . '/../libraries/mailer/class.smtp.php';

use Pluto\Core\Error;
use Pluto\Core\Response;
use Pluto\Model\User;
use Pluto\Core\Mysql as DB;

class System
{
    public static $connection;
    public static $data;
    public static $urlParams;
    public static $currentUser = null;
    public static $beforeInit = null;
    public static $router = null;
    public static $language = null;
    public static $permissions = null;

    /**
     * @return void
     */
    public static function init(): void
    {
        try {
            self::getEnv();
            self::$data = self::data();
            self::$urlParams = (object)$_GET;
            self::$permissions = (object)["*" => true];

            self::$router = new Router();
            self::$language = new Language();


            if (\is_callable(self::$beforeInit)) {
                call_user_func(self::$beforeInit, self::class);
            }
        } catch (\Throwable $th) {
            new Error("System initialize error.", $th->getCode(), $th);
            (new Response())->error($th->getMessage(), $th->getCode())->send();
        }
    }

    public static function db()
    {
        if (!self::$connection) {
            return self::$connection = new DB();
        } else {
            return self::$connection;
        }
    }

    /**
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string
    {
        $search = ['Ç', 'ç', 'Ğ', 'ğ', 'ı', 'İ', 'Ö', 'ö', 'Ş', 'ş', 'Ü', 'ü'];
        $replace = ['c', 'c', 'g', 'g', 'i', 'i', 'o', 'o', 's', 's', 'u', 'u'];
        $text = str_replace($search, $replace, $text);
        $text = strtolower($text);
        $text = preg_replace('/[^-a-z0-9\s]+/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');

        return $text;
    }

    /**
     * @param callable $callback
     * @return void
     */
    public static function setBeforeInit($callback): void
    {
        self::$beforeInit = $callback;
    }

    private static function getEnv()
    {
        $lines = file(__DIR__ . "/../../.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {

            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
            }
        }
    }

    /**
     * 
     * @return object 
     */
    private static function data(): object
    {
        if (isset(self::$data)) {
            return (object)self::$data;
        } else {
            $data = json_decode(file_get_contents('php://input'));
            if ($data) {
                if (isset($data->phone)) {
                    $data->phone = self::number_format($data->phone);
                }
                if (isset($data->token) && empty($data->token)) {
                    unset($data->token);
                }
                return $data;
            }
            if (isset($_POST["token"]) && empty($_POST["token"])) {
                unset($_POST["token"]);
            }
            if (isset($_COOKIE["token"])) {
                $_POST["token"] = $_COOKIE["token"];
            }
            return (object)$_POST;
        }
    }
    
    /**
     * @param string|int $number
     * @return string
     */
    static function number_format(string|int $number): string
    {
        $number = preg_replace("/[^\d]/", "", $number);
        return preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '$1$2$3', $number);
    }

    /**
     * @param string $data
     * @return bool
     */
    public static function json_validate($data): bool
    {
        if (!empty($data)) {
            return is_string($data) &&
                is_array(json_decode($data, true)) ? true : false;
        }
        return false;
    }

    /**
     * @param string $str www.domain.com or https://domain.com/url/path or domain.com
     * @return string
     */
    public static function getDomain($str): string
    {
        if ($str) {
            $explode = explode("/", $str);
            if (isset($explode[0])) {
                $str = $explode[0];
                $str = str_replace("www.", "", $str);
                $str = str_replace("http://", "", $str);
                $str = str_replace("https://", "", $str);
            }
        }
        return $str ? $str : "";
    }


    public static function realIP(): string
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $ip = $client;
        } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        } else {
            $ip = $remote;
        }

        return $ip;
    }
}
