<?php

declare(strict_types=1);

namespace Kameleoon\Managers\RemoteData;

use Kameleoon\Configuration\CustomDataInfo;
use Kameleoon\Data\Browser;
use Kameleoon\Data\CBScores;
use Kameleoon\Data\ScoredVarId;
use Kameleoon\Data\Conversion;
use Kameleoon\Data\CustomData;
use Kameleoon\Data\Device;
use Kameleoon\Data\Geolocation;
use Kameleoon\Data\KcsHeat;
use Kameleoon\Data\OperatingSystem;
use Kameleoon\Data\PageView;
use Kameleoon\Data\Personalization;
use Kameleoon\Data\Visit;
use Kameleoon\Data\VisitorVisits;
use Kameleoon\Data\Manager\AssignedVariation;
use Kameleoon\Data\Manager\PageViewVisit;
use Kameleoon\Types\RemoteVisitorDataFilter;

class RemoteVisitorData
{
    private RemoteVisitorDataFilter $filter;
    public array $customDataDict;
    public array $pageViewVisits;
    public array $conversions;
    public array $experiments;
    public array $personalizations;
    public ?Device $device;
    public ?Browser $browser;
    public ?OperatingSystem $operatingSystem;
    public ?Geolocation $geolocation;
    public int $visitNumber;
    public ?VisitorVisits $visitorVisits;
    public ?KcsHeat $kcsHeat;
    public ?CBScores $cbs;
    public ?string $visitorCode;

    public function __construct($json, RemoteVisitorDataFilter $filter)
    {
        $this->filter = $filter;
        $this->customDataDict = [];
        $this->pageViewVisits = [];
        $this->conversions = [];
        $this->experiments = [];
        $this->personalizations = [];
        $this->device = null;
        $this->browser = null;
        $this->operatingSystem = null;
        $this->geolocation = null;
        $this->visitorCode = null;
        $this->visitNumber = 0;
        $this->parseVisit(($json !== null) ? $json->currentVisit ?? null : null, 0);
        $this->visitorVisits = $this->parsePreviousVisits(($json !== null) ? $json->previousVisits ?? null : null);
        $this->kcsHeat = self::parseKcsHeat(($json !== null) ? $json->kcs ?? null : null);
        $this->cbs = self::parseCBScores(($json !== null) ? $json->cbs ?? null : null);
    }

    private function parsePreviousVisits($previousVisits): ?VisitorVisits
    {
        if (!is_array($previousVisits)) {
            return null;
        }
        $prevVisits = [];
        foreach ($previousVisits as $i => $visit) {
            if ($visit != null) {
                $timeStarted = $visit->timeStarted ?? 0;
                $timeLastEvent = $visit->timeLastEvent ?? $timeStarted;
                $prevVisits[] = new Visit($timeStarted, $timeLastEvent);
                $this->parseVisit($visit, $i + 1);
            }
        }
        return empty($prevVisits) ? null : new VisitorVisits($prevVisits, $this->visitNumber);
    }

    private function parseVisit($visit, int $visitOffset): void
    {
        if ($visit == null) {
            return;
        }
        if ($this->visitorCode === null) {
            $this->visitorCode = $visit->visitorCode ?? null;
        }
        $this->parseCustomData($visit->customDataEvents ?? null);
        $this->parsePages($visit->pageEvents ?? null);
        $this->parseExperiments($visit->experimentEvents ?? null);
        $this->parseConversions($visit->conversionEvents ?? null);
        $this->parseGeolocation($visit->geolocationEvents ?? null);
        $this->parseStaticData($visit->staticDataEvent ?? null, $visitOffset);
        $this->parsePersonalizations($visit->personalizationEvents ?? null);
    }

    private function parseCustomData($customDataEvents): void
    {
        if (!is_array($customDataEvents)) {
            return;
        }
        for ($i = count($customDataEvents) - 1; $i >= 0; $i--) {
            $customDataEvent = $customDataEvents[$i] ?? null;
            if ($customDataEvent == null) {
                continue;
            }
            $data = $customDataEvent->data ?? null;
            if ($data !== null) {
                $id = $data->index ?? null;
                if (($id !== null) && !array_key_exists($id, $this->customDataDict)) {
                    $valuesCountMap = $data->valuesCountMap ?? null;
                    $values = is_object($valuesCountMap) ? array_keys((array)$valuesCountMap) : [];
                    $cd = new CustomData($id, ...$values);
                    $this->customDataDict[$id] = $cd;
                }
            }
        }
    }

    private function parsePages($pageEvents): void
    {
        if (!is_array($pageEvents)) {
            return;
        }
        for ($i = count($pageEvents) - 1; $i >= 0; $i--) {
            $pageEvent = $pageEvents[$i] ?? null;
            if ($pageEvent == null) {
                continue;
            }
            $data = $pageEvent->data;
            if ($data == null) {
                continue;
            }
            $href = $data->href ?? null;
            if ($href == null) {
                continue;
            }
            $pageViewVisit = $this->pageViewVisits[$href] ?? null;
            if ($pageViewVisit === null) {
                $title = $data->title ?? null;
                $ts = $pageEvent->time ?? 0;
                $pageView = new PageView($href, $title);
                $pageViewVisit = new PageViewVisit($pageView, 1, $ts);
                $this->pageViewVisits[$href] = $pageViewVisit;
            } else {
                $pageViewVisit->increasePageVisits();
            }
        }
    }

    private function parseExperiments($experimentEvents): void
    {
        if (!is_array($experimentEvents)) {
            return;
        }
        for ($i = count($experimentEvents) - 1; $i >= 0; $i--) {
            $experimentEvent = $experimentEvents[$i] ?? null;
            if ($experimentEvent == null) {
                continue;
            }
            $data = $experimentEvent->data ?? null;
            if ($data == null) {
                continue;
            }
            $expId = $data->id ?? 0;
            if (!array_key_exists($expId, $this->experiments)) {
                $varId = $data->variationId ?? 0;
                $ts = $experimentEvent->time ?? 0;
                $variation = new AssignedVariation($expId, $varId, AssignedVariation::RULE_TYPE_UNKNOWN, $ts);
                $this->experiments[$expId] = $variation;
            }
        }
    }

    private function parseConversions($conversionEvents): void
    {
        if (!is_array($conversionEvents)) {
            return;
        }
        for ($i = count($conversionEvents) - 1; $i >= 0; $i--) {
            $conversionEvent = $conversionEvents[$i] ?? null;
            if ($conversionEvent == null) {
                continue;
            }
            $data = $conversionEvent->data ?? null;
            if ($data == null) {
                continue;
            }
            $goalId = $data->goalId ?? 0;
            $revenue = $data->revenue ?? 0.0;
            $negative = $data->negative ?? false;
            $ts = $conversionEvent->time ?? 0;
            $conversion = Conversion::createWithAssignmentDateMillis($goalId, $revenue, $negative, null, $ts);
            array_push($this->conversions, $conversion);
        }
    }

    private function parseGeolocation($geolocationEvents): void
    {
        if (!is_array($geolocationEvents) || ($this->geolocation !== null)) {
            return;
        }
        $geolocationEvent = $geolocationEvents[count($geolocationEvents) - 1] ?? null;
        if ($geolocationEvent == null) {
            return;
        }
        $data = $geolocationEvent->data ?? null;
        if ($data == null) {
            return;
        }
        $country = $data->country ?? null;
        $region = $data->region ?? null;
        $city = $data->city ?? null;
        $this->geolocation = new Geolocation($country, $region, $city);
    }

    private function parseStaticData($staticDataEvent, int $visitOffset): void
    {
        if ($staticDataEvent == null) {
            return;
        }
        $data = $staticDataEvent->data ?? null;
        if ($data == null) {
            return;
        }
        if ($this->visitNumber == 0) {
            $remoteVisitNumber = $data->visitNumber ?? null;
            if ($remoteVisitNumber !== null) {
                $this->visitNumber = $remoteVisitNumber + $visitOffset;
            }
        }
        if ($this->filter->device && ($this->device === null)) {
            $deviceType = $data->deviceType ?? null;
            if ($deviceType != null) {
                $this->device = new Device($deviceType);
            }
        }
        if ($this->filter->browser && ($this->browser === null)) {
            $browserName = $data->browser ?? null;
            $browserType = Browser::$browsers[$browserName] ?? null;
            if ($browserType != null) {
                $browserVersion = $data->browserVersion ?? NAN;
                $this->browser = new Browser($browserType, $browserVersion);
            }
        }
        if ($this->filter->operatingSystem && ($this->operatingSystem === null)) {
            $osType = $data->os ?? null;
            $osIndex = OperatingSystem::$typeIndices[$osType] ?? null;
            if ($osIndex != null) {
                $this->operatingSystem = new OperatingSystem($osIndex);
            }
        }
    }

    private function parsePersonalizations($personalizationEvents): void
    {
        if (!is_array($personalizationEvents)) {
            return;
        }
        for ($i = count($personalizationEvents) - 1; $i >= 0; $i--) {
            $personalizationEvent = $personalizationEvents[$i] ?? null;
            if ($personalizationEvent == null) {
                continue;
            }
            $data = $personalizationEvent->data ?? null;
            if ($data == null) {
                continue;
            }
            $id = $data->id ?? 0;
            if (!array_key_exists($id, $this->personalizations)) {
                $varId = $data->variationId ?? 0;
                $ts = $personalizationEvent->time ?? 0;
                $this->personalizations[$id] = new Personalization($id, $varId, $ts);
            }
        }
    }

    private static function parseKcsHeat($kcs): ?KcsHeat
    {
        if (!is_object($kcs)) {
            return null;
        }
        $valueMap = [];
        foreach ($kcs as $strKeyMomentId => $goalScores) {
            if (!(is_object($goalScores) && is_string($strKeyMomentId) && ctype_digit($strKeyMomentId))) {
                continue;
            }
            $goalScoreMap = [];
            foreach ($goalScores as $strGoalId => $score) {
                if (!((is_float($score) || is_int($score)) && is_string($strGoalId) && ctype_digit($strGoalId))) {
                    continue;
                }
                $goalId = intval($strGoalId);
                $goalScoreMap[$goalId] = $score;
            }
            $keyMomentId = intval($strKeyMomentId);
            $valueMap[$keyMomentId] = $goalScoreMap;
        }
        return empty($valueMap) ? null : new KcsHeat($valueMap);
    }

    private static function parseCBScores($cbs): ?CBScores
    {
        if (!is_object($cbs)) {
            return null;
        }
        $cbsMap = [];
        foreach ($cbs as $strExpId => $scoredVarEntries) {
            if (!(is_object($scoredVarEntries) && is_string($strExpId) && ctype_digit($strExpId))) {
                continue;
            }
            $entries = [];
            foreach ($scoredVarEntries as $strVarId => $score) {
                if (!((is_float($score) || is_int($score)) && is_string($strVarId) && ctype_digit($strVarId))) {
                    continue;
                }
                $varId = intval($strVarId);
                $entries[] = new ScoredVarId($varId, $score);
            }
            $experimentId = intval($strExpId);
            $cbsMap[$experimentId] = $entries;
        }
        return empty($cbsMap) ? null : new CBScores($cbsMap);
    }

    public function markVisitorDataAsSent(?CustomDataInfo $customDataInfo): void
    {
        foreach ($this->customDataDict as $id => $customData) {
            if (($customDataInfo == null) || !$customDataInfo->isVisitorScope($id)) {
                $customData->markAsSent();
            }
        }
        foreach ($this->pageViewVisits as $url => $pageViewVisit) {
            $pageViewVisit->getPageView()->markAsSent();
        }
        foreach ($this->conversions as $conversion) {
            $conversion->markAsSent();
        }
        foreach ($this->experiments as $expId => $variation) {
            $variation->markAsSent();
        }
        if ($this->device !== null) {
            $this->device->markAsSent();
        }
        if ($this->browser !== null) {
            $this->browser->markAsSent();
        }
        if ($this->operatingSystem !== null) {
            $this->operatingSystem->markAsSent();
        }
        if ($this->geolocation !== null) {
            $this->geolocation->markAsSent();
        }
    }

    public function collectVisitorDataToReturn(): array
    {
        $dataList = [];
        array_push($dataList, ...array_values($this->customDataDict));
        foreach ($this->pageViewVisits as $url => $pageViewVisit) {
            $dataList[] = $pageViewVisit->getPageView();
        }
        array_push($dataList, ...$this->conversions);
        if ($this->device !== null) {
            $dataList[] = $this->device;
        }
        if ($this->browser !== null) {
            $dataList[] = $this->browser;
        }
        if ($this->operatingSystem !== null) {
            $dataList[] = $this->operatingSystem;
        }
        if ($this->geolocation !== null) {
            $dataList[] = $this->geolocation;
        }
        return $dataList;
    }

    public function collectVisitorDataToAdd(): array
    {
        $dataList = [];
        array_push($dataList, ...array_values($this->customDataDict));
        array_push($dataList, ...array_values($this->pageViewVisits));
        array_push($dataList, ...$this->conversions);
        array_push($dataList, ...array_values($this->experiments));
        array_push($dataList, ...array_values($this->personalizations));
        if ($this->visitorVisits !== null) {
            $dataList[] = $this->visitorVisits;
        }
        if ($this->kcsHeat !== null) {
            $dataList[] = $this->kcsHeat;
        }
        if ($this->cbs !== null) {
            $dataList[] = $this->cbs;
        }
        if ($this->device !== null) {
            $dataList[] = $this->device;
        }
        if ($this->browser !== null) {
            $dataList[] = $this->browser;
        }
        if ($this->operatingSystem !== null) {
            $dataList[] = $this->operatingSystem;
        }
        if ($this->geolocation !== null) {
            $dataList[] = $this->geolocation;
        }
        return $dataList;
    }
}
