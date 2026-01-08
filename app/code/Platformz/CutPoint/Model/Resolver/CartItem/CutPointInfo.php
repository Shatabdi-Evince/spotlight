<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Model\Resolver\CartItem;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class CutPointInfo implements ResolverInterface
{
    public function __construct(
        private readonly Json $serializer
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $item = $value['model'] ?? null;
        if (!$item instanceof QuoteItem) {
            return null;
        }

        $opt = $item->getOptionByCode('additional_options');
        if (!$opt || !$opt->getValue()) {
            return null;
        }

        try {
            $decoded = $this->serializer->unserialize((string)$opt->getValue());
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $map = [];
        foreach ($decoded as $row) {
            if (!is_array($row) || !isset($row['label'], $row['value'])) {
                continue;
            }
            $map[(string)$row['label']] = (string)$row['value'];
        }

        if (!isset($map['Manufacturer'], $map['Model'], $map['Size'], $map['Cut Point'])) {
            return null;
        }

        return [
            'manufacturer' => $map['Manufacturer'],
            'model' => $map['Model'],
            'size' => $map['Size'],
            'cut_point' => $map['Cut Point'],
            'additional_info' => $map['Additional Info'] ?? null,
        ];
    }
}

