<?php

declare(strict_types=1);

namespace Kameleoon\Network;

use Kameleoon\Events\EventManager;
use Kameleoon\KameleoonClientConfig;
use Kameleoon\Network\AccessToken\AccessTokenSourceFactory;

interface NetworkManagerFactory
{
    public function create(
        string $siteCode,
        KameleoonClientConfig $clientConfig,
        string $kameleoonWorkDir,
        AccessTokenSourceFactory $accessTokenSourceFactory,
        EventManager $eventManager
    ): NetworkManager;
}
