<?php

define('BASE_PATH', dirname(__DIR__));

function load_env($path)
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
            $_SERVER[$name] = $value;
        }
    }
}

load_env(BASE_PATH . '/.env');

spl_autoload_register(function ($class) {
    $prefixes = [
        'Pluto\\' => BASE_PATH . '/core/',
        'App\\'   => BASE_PATH . '/app/',
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

/**
 * Render a view with the given data.
 *
 * @param string $view
 * @param array $data
 * @return string
 */
function view(string $view, array $data = []): string
{
    $template = new \Pluto\Template\Template();
    $GLOBALS['template'] = $template;
    return $template->render($view, $data);
}

/**
 * Get the translation for a given key.
 *
 * @param string $key
 * @param array $replace
 * @return string
 */
function __(string $key, array $replace = []): string {
    return \Pluto\Lang::getInstance()?->get($key, $replace) ?? $key;
}

/**
 * Get an instance of the response factory.
 *
 * @return \Pluto\Response
 */
function response(): \Pluto\Response
{
    return new \Pluto\Response();
}
