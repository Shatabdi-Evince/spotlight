<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Plugin\QuoteGraphQl\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Converts manufacture_selection input into entered_options so the downstream DTO-based cart pipeline can carry it.
 *
 * This lets us extend Magento's default addProductsToCart GraphQL without changing core DTOs.
 */
class AddProductsToCartPlugin
{
    private const PREFIX = 'platformz-cutpoint';

    public function beforeResolve(
        \Magento\QuoteGraphQl\Model\Resolver\AddProductsToCart $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        if (empty($args['cartItems']) || !is_array($args['cartItems'])) {
            return [$field, $context, $info, $value, $args];
        }

        $args['cartItems'] = array_map(
            function (array $item): array {
                if (empty($item['manufacture_selection']) || !is_array($item['manufacture_selection'])) {
                    return $item;
                }

                $sel = $item['manufacture_selection'];
                $entered = $item['entered_options'] ?? [];
                if (!is_array($entered)) {
                    $entered = [];
                }

                foreach (['manufacturer', 'model', 'size'] as $key) {
                    if (!isset($sel[$key])) {
                        continue;
                    }
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    $uid = base64_encode(self::PREFIX . '/' . $key);
                    $entered[] = [
                        'uid' => $uid,
                        'value' => (string)$sel[$key],
                    ];
                }

                $item['entered_options'] = $entered;
                unset($item['manufacture_selection']);

                return $item;
            },
            $args['cartItems']
        );

        return [$field, $context, $info, $value, $args];
    }
}

