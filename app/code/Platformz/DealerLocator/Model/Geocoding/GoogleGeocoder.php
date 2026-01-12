<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model\Geocoding;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Platformz\DealerLocator\Exception\GeocodingException;
use Platformz\DealerLocator\Model\Config;

class GoogleGeocoder implements GeocoderInterface
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly Config $config
    ) {
    }

    public function geocode(string $query): GeocodeResult
    {
        $apiKey = $this->config->getGoogleApiKey();
        if (!$apiKey) {
            throw new GeocodingException('Google API key not configured.');
        }

        $query = trim($query);
        if ($query === '') {
            throw new GeocodingException('Empty geocoding query.');
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'address' => $query,
            'key' => $apiKey,
        ]);

        try {
            $this->curl->reset();
            $this->curl->setTimeout(10);
            $this->curl->addHeader('Accept', 'application/json');
            $this->curl->get($url);
        } catch (\Throwable $e) {
            throw new GeocodingException('Failed to call Google Geocoding API.', 0, $e);
        }

        $statusCode = (int)$this->curl->getStatus();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new GeocodingException('Google Geocoding API returned HTTP ' . $statusCode . '.');
        }

        $body = (string)$this->curl->getBody();
        try {
            $data = $this->json->unserialize($body);
        } catch (\Throwable $e) {
            throw new GeocodingException('Invalid JSON returned by Google Geocoding API.', 0, $e);
        }

        if (!is_array($data)) {
            throw new GeocodingException('Unexpected response from Google Geocoding API.');
        }

        $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : 'UNKNOWN';
        if ($status !== 'OK') {
            $errorMessage = isset($data['error_message']) && is_string($data['error_message'])
                ? $data['error_message']
                : null;
            $message = 'Google Geocoding API error: ' . $status . ($errorMessage ? (' - ' . $errorMessage) : '');
            throw new GeocodingException($message);
        }

        $results = $data['results'] ?? null;
        if (!is_array($results) || empty($results[0]) || !is_array($results[0])) {
            throw new GeocodingException('No geocoding results found.');
        }

        $first = $results[0];
        $location = $first['geometry']['location'] ?? null;
        if (!is_array($location) || !isset($location['lat'], $location['lng'])) {
            throw new GeocodingException('Geocoding result missing coordinates.');
        }

        $lat = (float)$location['lat'];
        $lng = (float)$location['lng'];
        $displayName = isset($first['formatted_address']) && is_string($first['formatted_address'])
            ? $first['formatted_address']
            : null;

        return new GeocodeResult($lat, $lng, $displayName);
    }
}

