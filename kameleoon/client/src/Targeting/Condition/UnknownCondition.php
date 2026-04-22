<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Logging\KameleoonLogger;

class UnknownCondition extends TargetingCondition
{
    const TYPE = "UNKNOWN";

    public function check($data): bool
    {
        KameleoonLogger::warning("Condition of unknown type '%s' evaluated as true", $this->getType());
        return true;
    }
}
