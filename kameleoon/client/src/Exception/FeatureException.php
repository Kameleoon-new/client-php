<?php

namespace Kameleoon\Exception;

use Exception;
use Kameleoon\Exception\KameleoonException;

class FeatureException extends KameleoonException
{
    public function __construct($message, $code = 0, ?Exception $previous = null)
    {
        parent::__construct("Feature Exception: " . $message, $code, $previous);
    }
}
