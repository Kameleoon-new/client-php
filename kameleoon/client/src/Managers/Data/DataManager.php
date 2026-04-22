<?php

declare(strict_types=1);

namespace Kameleoon\Managers\Data;

use Kameleoon\Configuration\DataFile;
use Kameleoon\Types\DataFile as ExternalDataFile;

interface DataManager
{
    public function doesVisitorCodeManagementRequireConsent(): bool;

    public function getDataFile(): ?DataFile;
    public function getExternalDataFile(): ?ExternalDataFile;

    public function setDataFile(DataFile $df): void;
}
