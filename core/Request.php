<?php

namespace Pluto;

class Request
{
    public function getPath()
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }

        return $path;
    }

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public function getBody(): array
    {
        $body = [];
        if ($this->getMethod() === 'GET') {
            foreach ($_GET as $key => $value) {
                $body[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }
        if ($this->getMethod() === 'POST') {
            foreach ($_POST as $key => $value) {
                $body[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }
        return $body;
    }

    public function getCookie(string $name): ?string
    {
        $cookie = $_SERVER['HTTP_COOKIE'];
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

    public function payload(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            return $data;
        }
        return [];
    }
}
