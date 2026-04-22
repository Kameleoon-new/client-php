<?php

namespace Kameleoon\Exception;

use Exception;

class KameleoonException extends Exception
{
    private const KAMELEOON_SDK = "Kameleoon SDK: ";

    public function __construct($message, $code = 0, ?Exception $previous = null)
    {
        parent::__construct(self::KAMELEOON_SDK . $message, $code, $previous);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
