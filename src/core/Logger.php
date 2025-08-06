<?php

namespace Pluto\Core;

class Logger
{

    public static function log($message)
    {

        $path = __DIR__ . '/../../storage/logs/';
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $file = 'log_' . date("Y-m-d") . '.log';
        if (\file_exists($file) && \filesize($path . $file) > 1000000) {
            $file = 'log_' . date("Y-m-d") . "_" . time() . '.log';
            file_put_contents($path . $file, "");
            \chmod($path . $file, 0777);
        }
        file_put_contents($path . $file, date("Y-m-d H:i:s") . " - " . $message . "\n\r", FILE_APPEND);
    }
}
