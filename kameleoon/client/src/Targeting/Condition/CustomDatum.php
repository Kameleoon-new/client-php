<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

use Kameleoon\Helpers\StringHelper;
use Kameleoon\Logging\KameleoonLogger;

class CustomDatum extends TargetingCondition
{
    const TYPE = "CUSTOM_DATUM";

    private int $index;

    private string $operator;

    private ?string $value;

    private ?array $amongValues = null; // Parsed from value on first use

    public function getIndex()
    {
        return $this->index;
    }

    public function getOperator()
    {
        return $this->operator;
    }


    public function getValue()
    {
        return $this->value;
    }

    public function __construct($conditionData)
    {
        parent::__construct($conditionData);
        $this->index = intval($conditionData->customDataIndex);
        $this->operator = $conditionData->valueMatchType;
        $this->value = $conditionData->value;
    }

    public function check($data): bool
    {
        if (!is_iterable($data) || !isset($data[$this->index])) {
            return $this->operator === TargetingOperator::UNDEFINED;
        }

        $customData = $data[$this->index];
        return $this->checkTargeting($customData->getValues());
    }

    private function checkTargeting(array $customDataValues): bool
    {
        if (TargetingOperator::isComparison($this->operator)) {
            return $this->compareNumberValues($this->value, $customDataValues);
        }
        $targeting = false;
        switch ($this->operator) {
            case TargetingOperator::CONTAINS:
                $targeting = $this->checkForMatch(
                    $customDataValues,
                    function ($value) {
                        return strpos($value, $this->value) !== false;
                    }
                );
                break;
            case TargetingOperator::EXACT:
                $targeting = $this->checkForMatch(
                    $customDataValues,
                    function ($value) {
                        return $value == $this->value;
                    }
                );
                break;
            case TargetingOperator::REGULAR_EXPRESSION:
                $targeting = $this->checkForMatch(
                    $customDataValues,
                    function ($value) {
                        return preg_match("/" . $this->value . "/", $value);
                    }
                );
                break;
            case TargetingOperator::IS_TRUE:
                $targeting = $this->checkForMatch(
                    $customDataValues,
                    function ($value) {
                        return $value == "true";
                    }
                );
                break;
            case TargetingOperator::IS_FALSE:
                $targeting = $this->checkForMatch(
                    $customDataValues,
                    function ($value) {
                        return $value == "false";
                    }
                );
                break;
            case TargetingOperator::AMONG_VALUES:
                if ($this->amongValues === null) {
                    $this->amongValues = self::parseAmongValues($this->value);
                }
                $targeting = $this->checkForMatch(
                    $customDataValues,
                    function ($value) {
                        return in_array($value, $this->amongValues, true);
                    }
                );
                break;
            case TargetingOperator::UNDEFINED:
                // The custom data is defined, so the condition is not met
                break;
            default:
                KameleoonLogger::error(
                    "Unexpected comparing operation for %s condition: %s",
                    self::TYPE,
                    $this->operator
                );
                break;
        }
        return $targeting;
    }

    private function checkForMatch(array $customDataValues, callable $func): bool
    {
        foreach ($customDataValues as $value) {
            if ($func($value)) {
                return true;
            }
        }
        return false;
    }

    private function compareNumberValues(?string $conditionValue, array $customDataValues): bool
    {
        $number = self::parseNumber($conditionValue);
        // A non-numeric value is NaN, which never matches, so it does not hide the other values
        return $this->checkForMatch(
            $customDataValues,
            function ($value) use ($number) {
                return TargetingOperator::compare($this->operator, self::parseNumber($value), $number);
            }
        );
    }

    private static function parseNumber($value): float
    {
        // floatval is not enough: it returns 0 for a non-numeric value
        if (is_string($value)) {
            // is_numeric accepts trailing whitespace only since PHP 8
            $value = trim($value);
        }
        return is_numeric($value) ? floatval($value) : NAN;
    }

    private static function parseAmongValues(?string $value): array
    {
        $values = json_decode($value ?? "");
        return is_array($values) ? array_map([StringHelper::class, 'strval'], $values) : [];
    }
}
