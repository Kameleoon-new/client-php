<?php

namespace Kameleoon\Exception;

use Exception;
use Kameleoon\Exception\KameleoonException;

class ConfigException extends KameleoonException
{
    public function __construct($message, $code = 0, ?Exception $previous = null)
    {
        parent::__construct("Config Invalid: " . $message, $code, $previous);
    }
}
