<?php

namespace Pluto\Core;

use Pluto\Core\Response;
use Pluto\Core\Logger;
use Pluto\Core\System;

class Error extends \Exception
{
    public function __construct($message, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        if (getenv('DEBUG') == "true") {
            Logger::log($this);
        }
    }
}
