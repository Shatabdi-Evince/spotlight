<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model\Geocoding;

class GeocodeResult
{
    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly ?string $displayName = null
    ) {
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }
}

