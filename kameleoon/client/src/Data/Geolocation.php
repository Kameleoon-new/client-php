<?php

declare(strict_types=1);

namespace Kameleoon\Data;

use Kameleoon\Network\QueryBuilder;
use Kameleoon\Network\QueryParam;
use Kameleoon\Network\QueryParams;
use Kameleoon\Network\Sendable;

class Geolocation extends Sendable implements Data
{
    public const EVENT_TYPE = "geolocation";

    private string $country;
    private ?string $region;
    private ?string $city;
    private ?string $postalCode;
    private float $latitude;
    private float $longitude;

    public function __construct(
        string $country,
        ?string $region = null,
        ?string $city = null,
        ?string $postalCode = null,
        float $latitude = NAN,
        float $longitude = NAN
    ) {
        $this->country = $country;
        $this->region = $region;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /** @internal */
    public function getQuery(): string
    {
        $qb = new QueryBuilder(
            new QueryParam(QueryParams::EVENT_TYPE, self::EVENT_TYPE),
            new QueryParam(QueryParams::COUNTRY, $this->country),
            new QueryParam(QueryParams::REGION, $this->region),
            new QueryParam(QueryParams::CITY, $this->city),
            new QueryParam(QueryParams::POSTAL_CODE, $this->postalCode),
            new QueryParam(QueryParams::NONCE, $this->getNonce()),
        );
        if (!is_nan($this->latitude) && !is_nan($this->longitude)) {
            $qb->append(new QueryParam(QueryParams::LATITUDE, (string)$this->latitude));
            $qb->append(new QueryParam(QueryParams::LONGITUDE, (string)$this->longitude));
        }
        return (string)$qb;
    }

    public function __toString(): string
    {
        $latitude = is_nan($this->latitude) ? 'NaN' : $this->latitude;
        $longitude = is_nan($this->longitude) ? 'NaN' : $this->longitude;

        return "Geolocation{country:'" . $this->country . "',region:'" . $this->region . "',city:'" .
            $this->city . "',postalCode:'" . $this->postalCode . "',latitude:" . $latitude . ",longitude:" .
            $longitude . "}";
    }
}
