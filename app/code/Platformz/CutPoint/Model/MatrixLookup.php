<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * DB lookup + grouping for per-product manufacturer/model/size matrix.
 */
class MatrixLookup
{
    private const TABLE = 'platformz_cutpoint_matrix';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Return the fixed cut_point + additional_info for (product, manufacturer, model, size).
     *
     * @throws NoSuchEntityException
     */
    public function getRow(int $productId, string $manufacturer, string $model, string $size): MatrixRow
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()
            ->from(
                $table,
                ['product_id', 'manufacturer', 'model', 'size', 'cut_point', 'additional_info']
            )
            ->where('product_id = ?', $productId)
            ->where('manufacturer = ?', $manufacturer)
            ->where('model = ?', $model)
            ->where('size = ?', $size)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!$row) {
            throw new NoSuchEntityException(
                __(
                    'Invalid manufacture selection for this product (manufacturer/model/size not found).'
                )
            );
        }

        return new MatrixRow(
            (int)$row['product_id'],
            (string)$row['manufacturer'],
            (string)$row['model'],
            (string)$row['size'],
            (string)$row['cut_point'],
            $row['additional_info'] !== null ? (string)$row['additional_info'] : null
        );
    }

    /**
     * Returns nested options grouped manufacturer -> model -> sizes.
     *
     * Shape:
     * [
     *   ['manufacturer' => 'Eclipse', 'models' => [
     *      ['model' => 'Astra', 'sizes' => [
     *          ['size' => '7 1/4', 'cut_point' => '4.8', 'additional_info' => '...'],
     *      ]],
     *   ]],
     * ]
     */
    public function getGroupedOptions(int $productId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()
            ->from(
                $table,
                ['manufacturer', 'model', 'size', 'cut_point', 'additional_info']
            )
            ->where('product_id = ?', $productId)
            ->order(['manufacturer ASC', 'model ASC', 'size ASC']);

        $rows = $connection->fetchAll($select);
        if (!$rows) {
            return [];
        }

        $byManufacturer = [];
        foreach ($rows as $row) {
            $mfr = (string)$row['manufacturer'];
            $model = (string)$row['model'];

            $byManufacturer[$mfr] ??= [];
            $byManufacturer[$mfr][$model] ??= [];
            $byManufacturer[$mfr][$model][] = [
                'size' => (string)$row['size'],
                'cut_point' => (string)$row['cut_point'],
                'additional_info' => $row['additional_info'] !== null ? (string)$row['additional_info'] : null,
            ];
        }

        $result = [];
        foreach ($byManufacturer as $mfr => $models) {
            $modelItems = [];
            foreach ($models as $model => $sizes) {
                $modelItems[] = [
                    'model' => $model,
                    'sizes' => $sizes,
                ];
            }
            $result[] = [
                'manufacturer' => $mfr,
                'models' => $modelItems,
            ];
        }

        return $result;
    }
}

