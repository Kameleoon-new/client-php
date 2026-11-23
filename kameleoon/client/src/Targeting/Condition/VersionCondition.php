<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Helpers\SemVersion;
use Kameleoon\Logging\KameleoonLogger;

class VersionCondition extends TargetingCondition
{
    const TYPE = "APPLICATION_VERSION";
    protected ?string $conditionVersion;
    private ?string $versionMatchType;
    private ?SemVersion $cachedVersionCondition = null;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData);

        $this->conditionVersion = $conditionData->version ?? null;
        $this->versionMatchType = $conditionData->versionMatchType ?? null;
    }

    public function check($data): bool
    {
        return is_string($data) && $this->compareWithVersion($data);
    }

    protected function compareWithVersion(string $version): bool
    {
        if ($this->cachedVersionCondition === null) {
            $this->cachedVersionCondition = SemVersion::fromString($this->conditionVersion);
        }

        $versionCompare = SemVersion::fromString($version);

        if ($this->cachedVersionCondition === null || $versionCompare === null) {
            KameleoonLogger::error(
                "VersionCondition has unexpected values to compare, condition version: '%s', provided version: '%s'",
                $this->conditionVersion,
                $version
            );
            return false;
        }

        if (!TargetingOperator::isComparison($this->versionMatchType)) {
            KameleoonLogger::error(
                "Unexpected comparing operation for %s condition: %s",
                $this->getType(),
                (string) $this->versionMatchType
            );
            return false;
        }
        // The sign of comparing the versions is compared with zero
        return TargetingOperator::compare(
            $this->versionMatchType,
            $versionCompare->compareTo($this->cachedVersionCondition),
            0
        );
    }
}
