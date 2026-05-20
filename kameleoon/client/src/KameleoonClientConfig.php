<?php

declare(strict_types=1);

namespace Kameleoon;

use Kameleoon\Exception\ConfigCredentialsInvalid;
use Kameleoon\Helpers\Domain;
use Kameleoon\Helpers\StringHelper;
use Kameleoon\Logging\KameleoonLogger;

class KameleoonClientConfig
{
    const DEFAULT_KAMELEOON_WORK_DIR = "/tmp/kameleoon/php-client/";
    const DEFAULT_TIMEOUT_MILLISECONDS = 10_000;
    const SECONDS_IN_MINUTE = 60;
    const DEFAULT_REFRESH_INTERVAL_MINUTES = 60;
    const REQUEST_BODY_SIZE_LIMIT = 2560 * 1024; // 2.5 * 1024^2 bytes

    private string $clientId;
    private string $clientSecret;
    private string $kameleoonWorkDir;
    private int $refreshIntervalSecond;
    private int $defaultTimeoutMillisecond;
    private bool $debugMode;
    private CookieOptions $cookieOptions;
    private ?string $environment;
    private ?string $networkDomain;
    private int $requestBodySizeLimitBytes;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $kameleoonWorkDir = self::DEFAULT_KAMELEOON_WORK_DIR,
        int $refreshIntervalMinute = self::DEFAULT_REFRESH_INTERVAL_MINUTES,
        int $defaultTimeoutMillisecond = self::DEFAULT_TIMEOUT_MILLISECONDS,
        bool $debugMode = false,
        ?CookieOptions $cookieOptions = null,
        ?string $environment = null,
        ?string $networkDomain = null,
        int $requestBodySizeLimitBytes = self::REQUEST_BODY_SIZE_LIMIT
    ) {
        if (empty($clientId)) {
            throw new ConfigCredentialsInvalid("Client ID is not specified or empty");
        }
        $this->clientId = $clientId;
        if (empty($clientSecret)) {
            throw new ConfigCredentialsInvalid("Client secret is not specified or empty");
        }
        $this->clientSecret = $clientSecret;
        $this->kameleoonWorkDir = $kameleoonWorkDir;
        if ($refreshIntervalMinute <= 0) {
            KameleoonLogger::warning("Refresh interval must have positive value. Default refresh interval ("
                . self::DEFAULT_REFRESH_INTERVAL_MINUTES . " minutes) is applied");
            $refreshIntervalMinute = self::DEFAULT_REFRESH_INTERVAL_MINUTES;
        }
        $this->refreshIntervalSecond = $refreshIntervalMinute * self::SECONDS_IN_MINUTE;
        if ($defaultTimeoutMillisecond <= 0) {
            KameleoonLogger::warning("Default timeout must have positive value. Default timeout ("
                . self::DEFAULT_TIMEOUT_MILLISECONDS . " ms) is applied");
            $defaultTimeoutMillisecond = self::DEFAULT_TIMEOUT_MILLISECONDS;
        }
        $this->defaultTimeoutMillisecond = $defaultTimeoutMillisecond;
        if (
            $requestBodySizeLimitBytes <= 0
            || $requestBodySizeLimitBytes > self::REQUEST_BODY_SIZE_LIMIT
        ) {
            KameleoonLogger::warning(
                "Request body size limit is invalid. It must be greater than 0 and less than or equal "
                    . "to %s bytes. Default value (%s bytes) is applied.",
                self::REQUEST_BODY_SIZE_LIMIT,
                self::REQUEST_BODY_SIZE_LIMIT
            );
            $requestBodySizeLimitBytes = self::REQUEST_BODY_SIZE_LIMIT;
        }
        $this->requestBodySizeLimitBytes = $requestBodySizeLimitBytes;
        $this->debugMode = $debugMode;
        if ($cookieOptions == null || empty($cookieOptions->getTopLevelDomain())) {
            KameleoonLogger::warning("Setting parameter 'topLevelDomain' (top_level_domain) is strictly "
                . "recommended otherwise you may have problems when using subdomains.");
        }
        if ($cookieOptions == null) {
            $this->cookieOptions = new CookieOptions();
        } else {
            $this->cookieOptions = new CookieOptions(
                Domain::validateTopLevelDomain($cookieOptions->getTopLevelDomain()),
                $cookieOptions->getSecure(),
                $cookieOptions->getHttpOnly(),
                $cookieOptions->getSameSite()
            );
        }
        $this->environment = $environment;
        $this->networkDomain = Domain::validateNetworkDomain($networkDomain);
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getKameleoonWorkDir(): string
    {
        return $this->kameleoonWorkDir;
    }

    public function getRefreshIntervalSecond(): int
    {
        return $this->refreshIntervalSecond;
    }

    public function getDefaultTimeoutMillisecond(): int
    {
        return $this->defaultTimeoutMillisecond;
    }

    public function getDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function getCookieOptions(): CookieOptions
    {
        return $this->cookieOptions;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function getNetworkDomain(): ?string
    {
        return $this->networkDomain;
    }

    public function getRequestBodySizeLimitBytes(): int
    {
        return $this->requestBodySizeLimitBytes;
    }

    public static function readFromFile(string $filePath)
    {
        $kameleoonConfigJson = array();
        if (file_exists($filePath) && is_file($filePath)) {
            $config = file_get_contents($filePath, true);
            $kameleoonConfigJson = json_decode($config, true);
        }
        return new self(
            $kameleoonConfigJson["client_id"] ?? "",
            $kameleoonConfigJson["client_secret"] ?? "",
            $kameleoonConfigJson["kameleoon_work_dir"] ?? self::DEFAULT_KAMELEOON_WORK_DIR,
            $kameleoonConfigJson["refresh_interval_minute"] ?? self::DEFAULT_REFRESH_INTERVAL_MINUTES,
            $kameleoonConfigJson["default_timeout_millisecond"] ?? self::DEFAULT_TIMEOUT_MILLISECONDS,
            $kameleoonConfigJson["debug_mode"] ?? false,
            CookieOptions::readFromArray($kameleoonConfigJson["cookie_options"] ?? null),
            $kameleoonConfigJson["environment"] ?? null,
            $kameleoonConfigJson["network_domain"] ?? null,
            $kameleoonConfigJson["request_body_size_limit_bytes"] ?? self::REQUEST_BODY_SIZE_LIMIT
        );
    }

    public static function createCookieOptions(
        ?string $topLevelDomain = null,
        bool $secure = false,
        bool $httpOnly = false,
        string $sameSite = CookieOptions::DEFAULT_SAMESITE
    ) {
        return new CookieOptions($topLevelDomain, $secure, $httpOnly, $sameSite);
    }

    public function __toString()
    {
        return sprintf(
            "KameleoonClientConfig{kameleoonWorkDir:'%s',refreshInterval:%s,defaultTimeout:%s,
                requestBodySizeLimitBytes:%s,environment:'%s', clientId:'%s',clientSecret:'%s',
                networkDomain:'%s',cookieOptions:%s}",
            $this->kameleoonWorkDir,
            $this->refreshIntervalSecond,
            $this->defaultTimeoutMillisecond,
            $this->requestBodySizeLimitBytes,
            $this->environment,
            StringHelper::secret($this->clientId),
            StringHelper::secret($this->clientSecret),
            $this->networkDomain,
            $this->cookieOptions
        );
    }
}

class CookieOptions
{
    const DEFAULT_SAMESITE = "Lax";
    // Need to make it public readonly after migration to PHP 8.1+
    private bool $secure;
    private bool $httpOnly;
    private string $sameSite;
    private ?string $topLevelDomain;

    public function __construct(
        ?string $topLevelDomain = null,
        bool $secure = false,
        bool $httpOnly = false,
        string $sameSite = self::DEFAULT_SAMESITE
    ) {
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
        $this->topLevelDomain = $topLevelDomain;
    }

    public function getSecure(): bool
    {
        return $this->secure;
    }

    public function getHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    public function getTopLevelDomain(): ?string
    {
        return $this->topLevelDomain;
    }

    public static function readFromArray(?array $cookieOption)
    {
        if (is_null($cookieOption)) {
            return new self();
        }

        return new self(
            $cookieOption["domain"] ?? null,
            $cookieOption["secure"] ?? false,
            $cookieOption["http_only"] ?? false,
            $cookieOption["samesite"] ?? self::DEFAULT_SAMESITE,
        );
    }

    public function __toString()
    {
        return sprintf(
            "CookieOptions{topLevelDomain:'%s',secure:%b,httpOnly:%b,sameSite:'%s'}",
            $this->topLevelDomain,
            $this->secure,
            $this->httpOnly,
            $this->sameSite,
        );
    }
}
