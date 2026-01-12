<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model\Geocoding;

use Platformz\DealerLocator\Exception\GeocodingException;
use Platformz\DealerLocator\Model\Config;

/**
 * Uses Google when API key is configured, otherwise falls back to Nominatim.
 */
class CompositeGeocoder implements GeocoderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly GoogleGeocoder $googleGeocoder,
        private readonly NominatimGeocoder $nominatimGeocoder
    ) {
    }

    public function geocode(string $query): GeocodeResult
    {
        if ($this->config->getGoogleApiKey()) {
            return $this->googleGeocoder->geocode($query);
        }

        return $this->nominatimGeocoder->geocode($query);
    }
}

