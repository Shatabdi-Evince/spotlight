<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Model;

/**
 * Simple value object for a cutpoint matrix row.
 */
class MatrixRow
{
    public function __construct(
        public readonly int $productId,
        public readonly string $manufacturer,
        public readonly string $model,
        public readonly string $size,
        public readonly string $cutPoint,
        public readonly ?string $additionalInfo
    ) {
    }
}

