<?php

declare(strict_types=1);

namespace Kameleoon\Targeting\Condition;

class TargetingOperator
{
    public const UNDEFINED = "UNDEFINED";
    public const CONTAINS = "CONTAINS";
    public const EXACT = "EXACT";
    public const REGULAR_EXPRESSION = "REGULAR_EXPRESSION";
    public const LOWER = "LOWER";
    public const EQUAL = "EQUAL";
    public const GREATER = "GREATER";
    public const LOWER_OR_EQUAL = "LOWER_OR_EQUAL";
    public const GREATER_OR_EQUAL = "GREATER_OR_EQUAL";
    public const IS_TRUE = "TRUE";
    public const IS_FALSE = "FALSE";
    public const AMONG_VALUES = "AMONG_VALUES";
    public const ANY = "ANY";
    public const UNKNOWN = "UNKNOWN";

    public static function isComparison(?string $operator): bool
    {
        switch ($operator) {
            case self::LOWER:
            case self::GREATER:
            case self::EQUAL:
            case self::LOWER_OR_EQUAL:
            case self::GREATER_OR_EQUAL:
                return true;
            default:
                return false;
        }
    }

    /**
     * Checks `value <operator> conditionValue`.
     *
     * @return bool `false` if the operator is not a comparison one or any of the values is `NAN`
     */
    public static function compare(?string $operator, $value, $conditionValue): bool
    {
        switch ($operator) {
            case self::LOWER:
                return $value < $conditionValue;
            case self::GREATER:
                return $value > $conditionValue;
            case self::EQUAL:
                return $value == $conditionValue;
            case self::LOWER_OR_EQUAL:
                return $value <= $conditionValue;
            case self::GREATER_OR_EQUAL:
                return $value >= $conditionValue;
            default:
                return false;
        }
    }
}
