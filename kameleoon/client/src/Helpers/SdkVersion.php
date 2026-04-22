<?php

namespace Kameleoon\Helpers;

define("VERSION_SDK", "4.21.0");

class SdkVersion
{
    public const SDK_NAME = "PHP";

    private static $version;

    public static function getName()
    {
        return self::SDK_NAME;
    }

    public static function getVersion()
    {
        if (self::$version === null) {
            self::readVersion();
        }
        return self::$version;
    }

    private static function readVersion()
    {
        self::$version = VERSION_SDK;
        // TODO: fix in https://project.kameleoon.net/issues/23069
        // self::$version = \Composer\InstalledVersions::getVersion('kameleoon/kameleoon-client-php');

        // if (empty(self::$version)) {
        //     echo 'Kameleoon SDK: Version of SDK is not defined';
        // }
    }
}
