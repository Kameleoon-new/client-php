<?php

declare(strict_types=1);

namespace Kameleoon\Helpers;

class SemVersion
{
    private const NON_EXISTENT_IDENTIFIER = -1;

    public int $major;
    public int $minor;
    public int $patch;

    public function __construct(
        int $major = self::NON_EXISTENT_IDENTIFIER,
        int $minor = self::NON_EXISTENT_IDENTIFIER,
        int $patch = self::NON_EXISTENT_IDENTIFIER
    ) {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
    }

    /**
     * Compares this version to another SemVersion.
     * Returns < 0 if lower, 0 if equal, > 0 if higher.
     */
    public function compareTo(SemVersion $v): int
    {
        if ($this->major !== $v->major) {
            return $this->major <=> $v->major;
        }

        if ($this->minor !== $v->minor) {
            return $this->minor <=> $v->minor;
        }

        return $this->patch <=> $v->patch;
    }

    public function toFloat(): float
    {
        // Force dot as decimal separator
        return (float) sprintf('%d.%d', $this->major, $this->minor);
    }

    /**
     * Parses `major[.minor[.patch]]`, ignoring a pre-release (-) or build (+) suffix.
     * Returns null if the string is invalid.
     */
    public static function fromString(string $versionString): ?SemVersion
    {
        $versions = [0, 0, 0];
        $index = 0;
        $hasDigits = false;
        $valid = true;
        // Single pass without splitting, stops at pre-release (-) or build (+) suffix
        for ($i = 0, $length = strlen($versionString); $i < $length; $i++) {
            $c = $versionString[$i];
            $digit = ord($c) - 48;
            if ($digit >= 0 && $digit <= 9 && $versions[$index] <= intdiv(PHP_INT_MAX - $digit, 10)) {
                $versions[$index] = $versions[$index] * 10 + $digit;
                $hasDigits = true;
            } elseif ($c === '.' && $hasDigits && $index < 2) {
                $index++;
                $hasDigits = false;
            } else {
                $valid = $c === '.' || $c === '-' || $c === '+';
                break;
            }
        }
        // Each parsed component must have at least one digit
        if (!$valid || !$hasDigits) {
            // Replace with your logger if needed
            error_log(sprintf(
                "Invalid version component, index: %d, version: '%s'",
                $index,
                $versionString
            ));
            return null;
        }

        return new SemVersion($versions[0], $versions[1], $versions[2]);
    }
}
