<?php

declare(strict_types=1);

namespace Kameleoon\Network;

use Kameleoon\Network\AccessToken\AccessTokenSource;
use Kameleoon\Types\RemoteVisitorDataFilter;

interface NetworkManager
{
    public function getUrlProvider(): UrlProvider;
    public function getEnvironment(): ?string;
    public function getDefaultTimeout(): int;
    public function getNetProvider(): NetProvider;
    public function getAccessTokenSource(): AccessTokenSource;

    // Automation API
    public function fetchAccessJWToken(string $basicAuthToken, ?int $timeout = null): ?object;

    // SDK config API
    public function fetchConfiguration(?int $timeout = null, ?string $ifModifiedSince = null): ?FetchedConfiguration;

    // Data API
    public function getRemoteData(string $key, ?int $timeout = null);
    public function getRemoteVisitorData(string $visitorCode, RemoteVisitorDataFilter $filter, bool $isUniqueIdentifier,
        ?int $timeout = null);
    public function sendTrackingData(string $lines, bool $debug): void;
    public function sendTrackingDataInstantly(string $lines, bool $debug, ?int $timeout = null): bool;
}
