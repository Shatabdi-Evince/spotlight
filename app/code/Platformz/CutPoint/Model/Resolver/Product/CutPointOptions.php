<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Model\Resolver\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Platformz\CutPoint\Model\MatrixLookup;

class CutPointOptions implements ResolverInterface
{
    public function __construct(
        private readonly MatrixLookup $matrixLookup
    ) {
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $product = $value['model'] ?? null;
        if (!$product instanceof Product) {
            return [];
        }

        return $this->matrixLookup->getGroupedOptions((int)$product->getId());
    }
}

