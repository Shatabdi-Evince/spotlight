<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Model\Cart\BuyRequest;

use Magento\Quote\Model\Cart\BuyRequest\BuyRequestDataProviderInterface;
use Magento\Quote\Model\Cart\Data\CartItem;

/**
 * Extracts Platformz CutPoint selection from entered_options and injects into buyRequest.
 *
 * We encode EnteredOption.uid as base64("platformz-cutpoint/<key>") where key is manufacturer|model|size.
 */
class CutPointDataProvider implements BuyRequestDataProviderInterface
{
    private const PREFIX = 'platformz-cutpoint';

    public function execute(CartItem $cartItem): array
    {
        $selection = [];

        $entered = $cartItem->getEnteredOptions() ?? [];
        foreach ($entered as $option) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $decoded = base64_decode($option->getUid());
            if (!is_string($decoded) || $decoded === '') {
                continue;
            }

            $parts = explode('/', $decoded, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$prefix, $key] = $parts;
            if ($prefix !== self::PREFIX) {
                continue;
            }

            $selection[$key] = (string)$option->getValue();
        }

        if (!$selection) {
            return [];
        }

        return ['platformz_cutpoint' => $selection];
    }
}

