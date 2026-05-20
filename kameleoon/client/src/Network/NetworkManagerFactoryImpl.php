<?php

declare(strict_types=1);

namespace Kameleoon\Network;

use Kameleoon\Network\AccessToken\AccessTokenSourceFactory;

class NetworkManagerFactoryImpl implements NetworkManagerFactory
{
    private int $asyncRequestBodySizeLimit;

    public function __construct(
        int $asyncRequestBodySizeLimit = NetProviderImpl::ASYNC_REQUEST_BODY_SIZE_LIMIT
    ) {
        $this->asyncRequestBodySizeLimit = $asyncRequestBodySizeLimit;
    }

    public function create(
        string $siteCode,
        ?string $environment,
        int $defaultTimeout,
        string $kameleoonWorkDir,
        AccessTokenSourceFactory $accessTokenSourceFactory,
        ?string $networkDomain
    ): NetworkManager {
        $urlProvider = new UrlProvider($siteCode, $networkDomain);
        $netProvider = new NetProviderImpl($siteCode, $kameleoonWorkDir, $this->asyncRequestBodySizeLimit);
        return new NetworkManagerImpl(
            $urlProvider,
            $environment,
            $defaultTimeout,
            $netProvider,
            $accessTokenSourceFactory
        );
    }
}
