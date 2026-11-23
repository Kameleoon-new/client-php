<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Data\Browser;
use Kameleoon\Helpers\SdkVersion;
use Kameleoon\Helpers\SemVersion;
use Kameleoon\Logging\KameleoonLogger;

class BrowserCondition extends TargetingCondition
{
    const TYPE = "BROWSER";

    const CHROME = "CHROME";
    const INTERNET_EXPLORER = "IE";
    const FIREFOX = "FIREFOX";
    const SAFARI = "SAFARI";
    const OPERA = "OPERA";

    private int $browserType;
    private ?string $version;
    private ?string $operator;

    public function __construct($conditionData)
    {
        parent::__construct($conditionData);
        $this->browserType = $this->browserTypeFromBrowser($conditionData->browser ?? "");
        $this->version = $conditionData->version ?? null;
        $this->operator = $conditionData->versionMatchType ?? null;
    }

    public function check($data): bool
    {
        return $data instanceof Browser && $this->checkTargeting($data);
    }

    private function checkTargeting(Browser $browser)
    {
        if ($this->browserType != $browser->getBrowserType()) {
            return false;
        }

        if ($this->version === null) {
            return true;
        }

        $semVersion = SemVersion::fromString($this->version);
        if ($semVersion === null) {
            return false;
        }
        $versionNumber = $semVersion->toFloat();

        if (!TargetingOperator::isComparison($this->operator)) {
            KameleoonLogger::error("Unexpected comparing operation for 'Browser' condition: '%s'", $this->operator);
            return false;
        }
        return TargetingOperator::compare($this->operator, $browser->getVersion(), $versionNumber);
    }

    private function browserTypeFromBrowser(string $browserType): int
    {
        $browserType = $browserType == "IE" ? "INTERNET_EXPLORER" : $browserType;
        return Browser::$browsers[$browserType] ?? Browser::OTHER;
    }
}
