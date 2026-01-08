<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Plugin\Quote;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote as QuoteModel;
use Platformz\CutPoint\Model\MatrixLookup;

/**
 * Attach cutpoint selection as quote item additional_options so it flows to order/invoice/email.
 */
class AddProductPlugin
{
    public function __construct(
        private readonly MatrixLookup $matrixLookup,
        private readonly Json $serializer
    ) {
    }

    /**
     * After plugin for \Magento\Quote\Model\Quote::addProduct
     *
     * @param QuoteModel $subject
     * @param mixed $result
     * @param Product $product
     * @param mixed $request
     * @param string|null $processMode
     * @return mixed
     */
    public function afterAddProduct(QuoteModel $subject, $result, Product $product, $request = null, $processMode = null)
    {
        if (!($result instanceof QuoteItem)) {
            return $result;
        }
        if (!($request instanceof DataObject)) {
            return $result;
        }

        $selection = $request->getData('platformz_cutpoint');
        if (!is_array($selection)) {
            return $result;
        }

        $manufacturer = (string)($selection['manufacturer'] ?? '');
        $model = (string)($selection['model'] ?? '');
        $size = (string)($selection['size'] ?? '');

        if ($manufacturer === '' || $model === '' || $size === '') {
            return $result;
        }

        $row = $this->matrixLookup->getRow((int)$product->getId(), $manufacturer, $model, $size);

        $newOptions = [
            ['label' => 'Manufacturer', 'value' => $row->manufacturer],
            ['label' => 'Model', 'value' => $row->model],
            ['label' => 'Size', 'value' => $row->size],
            ['label' => 'Cut Point', 'value' => $row->cutPoint],
            ['label' => 'Additional Info', 'value' => $row->additionalInfo ?? ''],
        ];

        $existing = [];
        $existingOption = $result->getOptionByCode('additional_options');
        if ($existingOption && $existingOption->getValue()) {
            try {
                $decoded = $this->serializer->unserialize((string)$existingOption->getValue());
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            } catch (\InvalidArgumentException $e) {
                // Ignore malformed existing payload.
            }
        }

        // Remove any previous cutpoint values to avoid duplicates on repeated adds/updates.
        $existing = array_values(array_filter(
            $existing,
            static function ($opt): bool {
                if (!is_array($opt) || !isset($opt['label'])) {
                    return true;
                }
                return !in_array((string)$opt['label'], ['Manufacturer', 'Model', 'Size', 'Cut Point', 'Additional Info'], true);
            }
        ));

        $merged = array_merge($existing, $newOptions);
        $payload = $this->serializer->serialize($merged);

        $result->addOption([
            'code' => 'additional_options',
            'value' => $payload,
        ]);

        return $result;
    }
}

