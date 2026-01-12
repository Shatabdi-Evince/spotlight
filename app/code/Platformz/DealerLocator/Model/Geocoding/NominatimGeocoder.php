<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model\Geocoding;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Platformz\DealerLocator\Exception\GeocodingException;

/**
 * Default geocoder using OpenStreetMap Nominatim.
 *
 * Note: Nominatim requires a valid User-Agent / Referer identifying the application.
 */
class NominatimGeocoder implements GeocoderInterface
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json
    ) {
    }

    public function geocode(string $query): GeocodeResult
    {
        $query = trim($query);
        if ($query === '') {
            throw new GeocodingException('Empty geocoding query.');
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'format' => 'json',
            'limit' => 1,
            'q' => $query,
        ]);

        try {
            $this->curl->reset();
            $this->curl->setTimeout(10);
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->addHeader('User-Agent', 'Platformz_DealerLocator Magento2 GraphQL (contact: admin@example.com)');
            $this->curl->get($url);
        } catch (\Throwable $e) {
            throw new GeocodingException('Failed to call geocoding service.', 0, $e);
        }

        $status = (int)$this->curl->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new GeocodingException('Geocoding service returned HTTP ' . $status . '.');
        }

        $body = (string)$this->curl->getBody();
        try {
            $data = $this->json->unserialize($body);
        } catch (\Throwable $e) {
            throw new GeocodingException('Invalid JSON returned by geocoding service.', 0, $e);
        }

        if (!is_array($data) || empty($data[0]) || !is_array($data[0])) {
            throw new GeocodingException('No geocoding results found.');
        }

        $first = $data[0];
        $lat = isset($first['lat']) ? (float)$first['lat'] : null;
        $lon = isset($first['lon']) ? (float)$first['lon'] : null;

        if ($lat === null || $lon === null) {
            throw new GeocodingException('Geocoding result missing coordinates.');
        }

        $displayName = isset($first['display_name']) && is_string($first['display_name'])
            ? $first['display_name']
            : null;

        return new GeocodeResult($lat, $lon, $displayName);
    }
}

