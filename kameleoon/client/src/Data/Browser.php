<?php

namespace Kameleoon\Data;

use Kameleoon\Network\QueryBuilder;
use Kameleoon\Network\QueryParam;
use Kameleoon\Network\QueryParams;
use Kameleoon\Network\Sendable;

class Browser extends Sendable implements Data
{
    public const EVENT_TYPE = "staticData";

    public const CHROME = 0;
    public const INTERNET_EXPLORER = 1;
    public const FIREFOX = 2;
    public const SAFARI = 3;
    public const OPERA = 4;
    public const OTHER = 5;

    public static $browsers = array(
        "CHROME" => 0,
        "INTERNET_EXPLORER" => 1,
        "FIREFOX" => 2,
        "SAFARI" => 3,
        "OPERA" => 4,
        "OTHER" => 5
    );
    private int $browserType;
    private float $version;

    public function __construct(int $browserType, float $version = NAN)
    {
        $this->browserType = $browserType;
        $this->version = $version;
    }

    public function getBrowserType(): float
    {
        return $this->browserType;
    }

    public function getVersion(): float
    {
        return $this->version;
    }

    /** @internal */
    public function getQuery(): string
    {
        $qb = new QueryBuilder(
            new QueryParam(QueryParams::EVENT_TYPE, self::EVENT_TYPE),
            new QueryParam(QueryParams::BROWSER_INDEX, (string)$this->browserType),
            new QueryParam(QueryParams::NONCE, $this->getNonce()),
        );
        if (!is_nan($this->version)) {
            $qb->append(new QueryParam(QueryParams::BROWSER_VERSION, (string)$this->version));
        }
        return (string)$qb;
    }

    public function __toString(): string
    {
        $version = is_nan($this->version) ? 'NaN' : $this->version;

        return "Browser{browserType:" . $this->browserType . ",version:" . $version . "}";
    }
}
