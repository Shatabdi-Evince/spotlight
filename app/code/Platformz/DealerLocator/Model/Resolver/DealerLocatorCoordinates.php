<?php
declare(strict_types=1);

namespace Platformz\DealerLocator\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Platformz\DealerLocator\Model\Geocoding\GeocoderInterface;

class DealerLocatorCoordinates implements ResolverInterface
{
    public function __construct(
        private readonly GeocoderInterface $geocoder
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $input = $args['input'] ?? null;
        if (!is_array($input)) {
            throw new GraphQlInputException(__('Required parameter "input" is missing.'));
        }

        $address1 = (string)($input['address_1'] ?? '');
        $address2 = isset($input['address_2']) ? (string)$input['address_2'] : '';
        $city = (string)($input['city'] ?? '');
        $state = (string)($input['state'] ?? '');
        $country = (string)($input['country'] ?? '');
        $postalCode = (string)($input['postal_code'] ?? '');

        $queryParts = array_values(array_filter(array_map('trim', [
            $address1,
            $address2,
            $city,
            $state,
            $postalCode,
            $country,
        ]), static fn($v) => $v !== ''));

        if (!$queryParts) {
            throw new GraphQlInputException(__('At least one address field must be provided.'));
        }

        $query = implode(', ', $queryParts);

        try {
            $result = $this->geocoder->geocode($query);
        } catch (\Throwable $e) {
            // Avoid leaking upstream error details.
            throw new GraphQlInputException(__('Unable to geocode the provided address.'));
        }

        return [
            'latitude' => $result->getLatitude(),
            'longitude' => $result->getLongitude(),
            'display_name' => $result->getDisplayName(),
        ];
    }
}

