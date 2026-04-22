<?php

declare(strict_types=1);

namespace Kameleoon\Network;

use Exception;
use Kameleoon\Helpers\SdkVersion;
use Kameleoon\Types\RemoteVisitorDataFilter;

class UrlProvider
{
    const TRACKING_PATH = "/visit/events";
    const VISITOR_DATA_PATH = "/visit/visitor";
    const GET_DATA_PATH = "/map/map";
    public const ACCESS_TOKEN_PATH = "/oauth/token";
    public const TEST_DATA_API_DOMAIN = "data.kameleoon.net";
    public const DEFAULT_DATA_API_DOMAIN = "data.kameleoon.io";
    public const DEFAULT_EVENTS_DOMAIN = "events.kameleoon.eu";
    public const DEFAULT_CONFIGURATION_DOMAIN = "sdk-config.kameleoon.eu";
    public const DEFAULT_ACCESS_TOKEN_DOMAIN = "api.kameleoon.com";
    const CONFIGURATION_API_URL_FORMAT = "https://%s/v3/%s";
    const RT_CONFIGURATION_URL_FORMAT = "https://%s:8110/sse";
    const ACCESS_TOKEN_URL_FORMAT = "https://%s/oauth/token";
    const DATA_API_URL_FORMAT = "https://%s%s?%s";

    private bool $isCustomDomain = false;
    private string $siteCode;
    private string $sdkSiteCodeQueryBase;
    private string $trackingQueryBase;

    protected string $dataApiDomain = UrlProvider::DEFAULT_DATA_API_DOMAIN;
    private string $eventsDomain = UrlProvider::DEFAULT_EVENTS_DOMAIN;
    private string $configurationDomain = UrlProvider::DEFAULT_CONFIGURATION_DOMAIN;
    private string $accessTokenDomain = UrlProvider::DEFAULT_ACCESS_TOKEN_DOMAIN;

    public function __construct(string $siteCode, ?string $networkDomain = null)
    {
        $this->siteCode = $siteCode;
        $queryBuilder = $this->makeSdkSiteCodeQueryBase();
        $this->sdkSiteCodeQueryBase = (string)$queryBuilder;
        $queryBuilder->append(new QueryParam(QueryParams::BODY_UA, "true"));
        $this->trackingQueryBase = (string)$queryBuilder;
        $this->updateDomains($networkDomain);
    }

    private function updateDomains(?string $networkDomain): void
    {
        if (empty($networkDomain)) {
            return;
        }
        $this->isCustomDomain = true;

        $this->eventsDomain = "events." . $networkDomain;
        $this->configurationDomain = "sdk-config." . $networkDomain;
        $this->dataApiDomain = "data." . $networkDomain;
        $this->accessTokenDomain = "api." . $networkDomain;
    }

    public function getSiteCode(): string
    {
        return $this->siteCode;
    }

    public function getDataApiDomain(): string
    {
        return $this->dataApiDomain;
    }

    public function getEventsDomain(): string
    {
        return $this->eventsDomain;
    }

    public function getConfigurationDomain(): string
    {
        return $this->configurationDomain;
    }

    public function getAccessTokenDomain(): string
    {
        return $this->accessTokenDomain;
    }

    public function applyDataApiDomain(?string $dataApiDomain): void
    {
        if (!empty($dataApiDomain)) {
            if ($this->isCustomDomain) {
                $subDomain = strstr($dataApiDomain, '.', true);
                $this->dataApiDomain = preg_replace('/^[^.]+/', $subDomain, $this->dataApiDomain);
            } else {
                $this->dataApiDomain = $dataApiDomain;
            }
        }
    }

    private function makeSdkSiteCodeQueryBase(): QueryBuilder
    {
        return new QueryBuilder(
            new QueryParam(QueryParams::SDK_NAME, SdkVersion::getName()),
            new QueryParam(QueryParams::SDK_VERSION, SdkVersion::getVersion()),
            new QueryParam(QueryParams::SITE_CODE, $this->siteCode),
        );
    }

    public function makeTrackingUrl(): string
    {
        return sprintf(self::DATA_API_URL_FORMAT, $this->dataApiDomain, self::TRACKING_PATH, $this->trackingQueryBase);
    }

    public function makeExperimentRegisterDebugParams(): ?string
    {
        try {
            $currentUrl = "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            if ($ip === "") {
                $ip = "";
            }
            $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? "";
            return "&" . new QueryBuilder(
                new QueryParam(QueryParams::DEBUG, "true", false),
                new QueryParam(QueryParams::URL, rawurlencode($currentUrl)),
                new QueryParam(QueryParams::IP, rawurlencode($ip)),
                new QueryParam(QueryParams::UA, rawurlencode($http_user_agent)),
            );
        } catch (Exception $_) {
            return null;
        }
    }

    public function makeVisitorDataGetUrl(
        string $visitorCode,
        RemoteVisitorDataFilter $filter,
        bool $isUniqueIdentifier
    ): string {
        $qb = new QueryBuilder(
            new QueryParam(QueryParams::SITE_CODE, $this->siteCode),
            new QueryParam($isUniqueIdentifier ? QueryParams::MAPPING_VALUE : QueryParams::VISITOR_CODE, $visitorCode),
            new QueryParam(QueryParams::MAX_NUMBER_PREVIOUS_VISITS, (string)$filter->previousVisitAmount),
            new QueryParam(QueryParams::VERSION, "0", false),
            new QueryParam(QueryParams::STATIC_DATA, "true", false),
        );
        self::addFlagParamIfRequired($qb, QueryParams::KCS, $filter->kcs);
        self::addFlagParamIfRequired($qb, QueryParams::CURRENT_VISIT, $filter->currentVisit);
        self::addFlagParamIfRequired($qb, QueryParams::CUSTOM_DATA, $filter->customData);
        self::addFlagParamIfRequired($qb, QueryParams::CONVERSION, $filter->conversion);
        self::addFlagParamIfRequired($qb, QueryParams::GEOLOCATION, $filter->geolocation);
        self::addFlagParamIfRequired($qb, QueryParams::EXPERIMENT, $filter->experiments);
        self::addFlagParamIfRequired($qb, QueryParams::PAGE, $filter->pageViews);
        self::addFlagParamIfRequired($qb, QueryParams::PERSONALIZATION, $filter->personalization);
        self::addFlagParamIfRequired($qb, QueryParams::CBS, $filter->cbs);
        return sprintf(self::DATA_API_URL_FORMAT, $this->dataApiDomain, self::VISITOR_DATA_PATH, $qb);
    }
    private static function addFlagParamIfRequired(QueryBuilder $qb, string $paramName, bool $state): void
    {
        if ($state) {
            $qb->append(new QueryParam($paramName, "true", false));
        }
    }

    public function makeApiDataGetRequestUrl(string $key): string
    {
        $qb = new QueryBuilder(
            new QueryParam(QueryParams::SITE_CODE, $this->siteCode),
            new QueryParam(QueryParams::KEY, $key),
        );
        return sprintf(self::DATA_API_URL_FORMAT, $this->dataApiDomain, self::GET_DATA_PATH, $qb);
    }

    public function makeConfigurationUrl(?string $environment = null): string
    {
        $qb = new QueryBuilder();
        if ($environment !== null) {
            $qb->append(new QueryParam(QueryParams::ENVIRONMENT, $environment));
        }
        $url = sprintf(self::CONFIGURATION_API_URL_FORMAT, $this->configurationDomain, $this->siteCode);
        $query = (string)$qb;
        if (!empty($query)) {
            $url .= "?$query";
        }
        return $url;
    }

    public function makeRealTimeUrl(): string
    {
        $qp = new QueryParam(QueryParams::SITE_CODE, $this->siteCode);
        return sprintf(self::RT_CONFIGURATION_URL_FORMAT . "?%s", $this->eventsDomain, $qp);
    }

    public function makeAccessTokenUrl(): string
    {
        return sprintf(self::ACCESS_TOKEN_URL_FORMAT, $this->accessTokenDomain) . '?' . $this->sdkSiteCodeQueryBase;
    }
}
