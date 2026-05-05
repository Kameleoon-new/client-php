<?php

declare(strict_types=1);

namespace Kameleoon\Targeting;

use Kameleoon\Data\Manager\VisitorManager;
use Kameleoon\Helpers\SdkVersion;
use Kameleoon\Logging\KameleoonLogger;
use Kameleoon\Managers\Data\DataManager;
use Kameleoon\Targeting\Condition\BrowserCondition;
use Kameleoon\Targeting\Condition\ConversionCondition;
use Kameleoon\Targeting\Condition\CookieCondition;
use Kameleoon\Targeting\Condition\CustomDatum;
use Kameleoon\Targeting\Condition\DeviceCondition;
use Kameleoon\Targeting\Condition\ExclusiveExperimentCondition;
use Kameleoon\Targeting\Condition\GeolocationCondition;
use Kameleoon\Targeting\Condition\KcsHeatRangeCondition;
use Kameleoon\Targeting\Condition\OperatingSystemCondition;
use Kameleoon\Targeting\Condition\PageTitleCondition;
use Kameleoon\Targeting\Condition\PageUrlCondition;
use Kameleoon\Targeting\Condition\PageViewNumberCondition;
use Kameleoon\Targeting\Condition\PreviousPageCondition;
use Kameleoon\Targeting\Condition\SdkInfo;
use Kameleoon\Targeting\Condition\SdkLanguageCondition;
use Kameleoon\Targeting\Condition\SegmentCondition;
use Kameleoon\Targeting\Condition\TargetExperimentCondition;
use Kameleoon\Targeting\Condition\TargetFeatureFlagCondition;
use Kameleoon\Targeting\Condition\TargetPersonalizationCondition;
use Kameleoon\Targeting\Condition\TimeElapsedSinceVisitCondition;
use Kameleoon\Targeting\Condition\TargetingDataVisitNumberToday;
use Kameleoon\Targeting\Condition\VersionCondition;
use Kameleoon\Targeting\Condition\VisitNumberTodayCondition;
use Kameleoon\Targeting\Condition\VisitNumberTotalCondition;
use Kameleoon\Targeting\Condition\VisitorCodeCondition;
use Kameleoon\Targeting\Condition\VisitorNewReturnCondition;

class TargetingManagerImpl implements TargetingManager
{
    private VisitorManager $visitorManager;
    private DataManager $dataManager;

    public function __construct(DataManager $dataManager, VisitorManager $visitorManager)
    {
        $this->dataManager = $dataManager;
        $this->visitorManager = $visitorManager;
    }

    public function checkTargeting(string $visitorCode, ?int $containerID, ?TargetingSegment $segment): bool
    {
        KameleoonLogger::debug(
            "CALL: TargetingManager.checkTargeting(visitorCode: '%s', containerID: %s, segment: %s)",
            $visitorCode,
            $containerID,
            $segment
        );
        $targeting = true;

        // performing targeting
        if ($segment !== null) {
            $targetingTree = $segment->getTargetingTree();
            // obtaining targeting checking result and assigning targeting to container
            $targeting = TargetingEngine::checkTargetingTree(
                $targetingTree,
                function (string $type) use ($visitorCode, $containerID) {
                    return $this->getConditionData($type, $visitorCode, $containerID);
                }
            );
        }
        if ($targeting) {
            KameleoonLogger::info("Visitor '%s' has been targeted for %s", $visitorCode, $segment);
        }
        KameleoonLogger::debug(
            "RETURN: TargetingManager.checkTargeting(visitorCode: '%s', containerID: %s, segment: %s)" .
                " -> (targeted: %s)",
            $visitorCode,
            $containerID,
            $segment,
            $targeting
        );
        return $targeting;
    }

    private function getConditionData(string $type, string $visitorCode, ?int $campaignId)
    {
        KameleoonLogger::debug(
            "CALL: TargetingManager.getConditionData(type: '%s', visitorCode: '%s', campaignId: %s)",
            $type,
            $visitorCode,
            $campaignId
        );
        $conditionData = null;
        $visitor = $this->visitorManager->getVisitor($visitorCode);
        switch ($type) {
            case CustomDatum::TYPE:
                $conditionData = !is_null($visitor) ? $visitor->getCustomData() : null;
                break;
            case PageTitleCondition::TYPE:
            case PageUrlCondition::TYPE:
            case PageViewNumberCondition::TYPE:
            case PreviousPageCondition::TYPE:
                $conditionData = !is_null($visitor) ? $visitor->getPageViewVisits() : null;
                break;
            case DeviceCondition::TYPE:
                $conditionData = !is_null($visitor) ? $visitor->getDevice() : null;
                break;
            case VersionCondition::TYPE:
                $conditionData = !is_null($visitor) && $visitor->getApplicationVersion() !== null
                    ? $visitor->getApplicationVersion()->getVersion()
                    : null;
                break;
            case BrowserCondition::TYPE:
                $conditionData = !is_null($visitor) ? $visitor->getBrowser() : null;
                break;
            case ConversionCondition::TYPE:
                if ($visitor !== null) {
                    $conditionData = [
                        "visitorVisits" => $visitor->getVisitorVisits(),
                        "conversions" => $visitor->getConversions(),
                    ];
                }
                break;
            case VisitorCodeCondition::TYPE:
                $conditionData = $visitorCode;
                break;
            case TargetFeatureFlagCondition::TYPE:
                if ($visitor != null) {
                    $conditionData = [
                        "visitorVisits" => $visitor->getVisitorVisits(),
                        "dataFile" => $this->dataManager->getDataFile(),
                        "variations" => $visitor->getAssignedVariations(),
                    ];
                }
                break;
            case TargetExperimentCondition::TYPE:
                if ($visitor !== null) {
                    $conditionData = [
                        "visitorVisits" => $visitor->getVisitorVisits(),
                        "variations"    => $visitor->getAssignedVariations(),
                    ];
                }
                break;
            case TargetPersonalizationCondition::TYPE:
                if ($visitor !== null) {
                    $conditionData = [
                        "visitorVisits"    => $visitor->getVisitorVisits(),
                        "personalizations" => $visitor->getPersonalizations(),
                    ];
                }
                break;
            case ExclusiveExperimentCondition::TYPE:
                if ($campaignId !== null) {
                    if (!is_null($visitor)) {
                        $conditionData = [
                            "visitorVisits"      => $visitor->getVisitorVisits(),
                            "currentExperimentId" => $campaignId,
                            "variations"         => $visitor->getAssignedVariations(),
                            "personalizations"   => $visitor->getPersonalizations(),
                        ];
                    } else {
                        $conditionData = [];
                    }
                }
                break;
            case SdkLanguageCondition::TYPE:
                $conditionData = new SdkInfo(SdkVersion::getName(), SdkVersion::getVersion());
                break;
            case CookieCondition::TYPE:
                $conditionData = ($visitor != null) ? $visitor->getCookie() : null;
                break;
            case GeolocationCondition::TYPE:
                $conditionData = ($visitor != null) ? $visitor->getGeolocation() : null;
                break;
            case OperatingSystemCondition::TYPE:
                $conditionData = ($visitor != null) ? $visitor->getOperatingSystem() : null;
                break;
            case SegmentCondition::TYPE:
                $conditionData = [
                    $this->dataManager->getDataFile(),
                    function (string $type) use ($visitorCode, $campaignId) {
                        return $this->getConditionData($type, $visitorCode, $campaignId);
                    }
                ];
                break;
            case TimeElapsedSinceVisitCondition::FIRST_VISIT_TYPE:
            case TimeElapsedSinceVisitCondition::LAST_VISIT_TYPE:
            case VisitNumberTotalCondition::TYPE:
            case VisitorNewReturnCondition::TYPE:
                $conditionData = ($visitor != null) ? $visitor->getVisitorVisits() : null;
                break;
            case VisitNumberTodayCondition::TYPE:
                $conditionData = ($visitor != null)
                    ? new TargetingDataVisitNumberToday($visitor->getTimeStarted(), $visitor->getVisitorVisits())
                    : new TargetingDataVisitNumberToday(null, null);
                break;
            case KcsHeatRangeCondition::TYPE:
                $conditionData = ($visitor != null) ? $visitor->getKcsHeat() : null;
                break;
        }

        KameleoonLogger::debug(
            "CALL: TargetingManager.getConditionData(type: '%s', visitorCode: '%s', campaignId: %s)" .
                " -> (conditionData: %s)",
            $type,
            $visitorCode,
            $campaignId,
            $conditionData
        );
        return $conditionData;
    }
}
