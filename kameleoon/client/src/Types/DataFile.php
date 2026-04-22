<?php

declare(strict_types=1);

namespace Kameleoon\Types;

use Kameleoon\Configuration\DataFile as InternalDataFile;
use Kameleoon\Helpers\StringHelper;

class DataFile
{
    /**
     * @var array<string, FeatureFlag>
     */
    public array $featureFlags;

    /**
     * @var int
     */
    public int $dateModified;

    /**
     * @internal
     * @param array<string, FeatureFlag> $featureFlags
     * @param int $dateModified
     */
    public function __construct(array $featureFlags, int $dateModified)
    {
        $this->featureFlags = $featureFlags;
        $this->dateModified = $dateModified;
    }

    public static function buildFromInternal(InternalDataFile $sourceDataFile): self
    {
        $featureFlags = [];
        foreach ($sourceDataFile->getFeatureFlags() as $featureKey => $featureFlag) {
            $featureFlags[$featureKey] = FeatureFlag::buildFromInternal($featureFlag);
        }

        return new self($featureFlags, $sourceDataFile->getDateModified());
    }

    public function __toString(): string
    {
        $featureFlags = StringHelper::sarray($this->featureFlags);
        return "DataFile{featureFlags:$featureFlags,dateModified:$this->dateModified}";
    }
}
