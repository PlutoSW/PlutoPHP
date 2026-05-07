<?php

namespace Pluto;

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
class Request
{
    public $user = null;
    public $permission = "";
    public $permission_project = null;
    private static ?self $instance = null;

    public function __construct()
    {
        self::$instance = $this;
    }

    public function setPermission_project($permission_project)
    {
        $this->permission_project = $permission_project;
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new Request();
        }
        return self::$instance;
    }

    public function toJson(): string
    {
        return json_encode([
            'path' => $this->getPath(),
            'body' => $this->getBody(),
            'headers' => $this->getHeaders(),
            'method' => $this->getMethod(),
            'permission_project' => $this->permission_project,
        ]);
    }

    public function getPath()
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }

        return $path;
    }

    public function getHeaders(): array
    {
        return getallheaders();
    }

    public function getHeader($key)
    {
        $headers = getallheaders();
        if (isset($headers[$key])) {
            return $headers[$key];
        }
        return null;
    }

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public function getBody(): array
    {
        $body = [];
        if (count($_GET)) {
            foreach ($_GET as $key => $value) {
                $body[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }
        if (count($_POST)) {
            foreach ($_POST as $key => $value) {
                $body[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }
        $json = json_decode(file_get_contents('php://input'), true);
        if ($json) {
            foreach ($json as $key => $value) {
                $body[$key] = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        return $body;
    }

    public function cookie(string $name, $value = null, array $options = [])
    {
        if (!$value) {
            $cookie = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;
            if ($cookie) {
                $cookies = explode(';', $cookie);
                foreach ($cookies as $cookie) {
                    $parts = explode('=', trim($cookie));
                    if ($parts[0] === $name) {
                        return $parts[1];
                    }
                }
            }
            return null;
        } else {
            $expire = (isset($options['expires']) && $options['expires']) ? date('Y-m-d H:i:s', $options['expires']) : null;
            $path = (isset($options['path']) && $options['path']) ? $options['path'] : '/';
            $domain = (isset($options['domain']) && $options['domain']) ? $options['domain'] : null;
            $secure = (isset($options['secure']) && $options['secure']) ? $options['secure'] : null;
            $httponly = (isset($options['httponly']) && $options['httponly']) ? $options['httponly'] : null;
            $sameSite = (isset($options['sameSite']) && $options['sameSite']) ? $options['sameSite'] : null;
            setcookie($name, $value, $expire, $path, $domain, $secure, $httponly, $sameSite);
        }
    }

    public function get(string $key, $defaultValue = null): mixed
    {
        if (isset($_GET[$key])) {
            return filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
        }
        return $defaultValue;
    }

    public function post(string $key, $defaultValue = null): mixed
    {
        if (isset($_POST[$key])) {
            return filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
        }
        return $defaultValue;
    }

    public function posts(): array
    {
        return $_POST;
    }

    public function gets(): array
    {
        return $_GET;
    }

    public function payload($key = null, $defaultValue = null): mixed
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($key) {
            if (isset($data[$key])) {
                return filter_var($data[$key], FILTER_SANITIZE_SPECIAL_CHARS);
            }
            return $defaultValue;
        }
        if ($data) {
            return $data;
        }
        return [];
    }



    public function isJson(): bool
    {
        if (strpos($this->getPath(), '/api/') !== false) return true;
        return $this->getHeader('Accept') === 'application/json' || str_contains($this->getHeader('Content-Type') ?? '', 'application/json');
    }

    public function referrer()
    {
        return $_SERVER['HTTP_REFERER'];
    }
}
