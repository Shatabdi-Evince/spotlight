<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model\Geocoding;

use Platformz\DealerLocator\Exception\GeocodingException;

interface GeocoderInterface
{
    /**
     * @throws GeocodingException
     */
    public function geocode(string $query): GeocodeResult;
}

