<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Platformz_CutPoint::import';

    private const TABLE = 'platformz_cutpoint_matrix';

    public function __construct(
        Context $context,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly Csv $csv,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index');

        try {
            $sku = trim((string)$this->getRequest()->getParam('product_sku'));
            if ($sku === '') {
                throw new LocalizedException(__('Please enter a product SKU.'));
            }

            $replace = (bool)$this->getRequest()->getParam('replace_existing');

            $product = $this->productRepository->get($sku, false, null, true);
            $productId = (int)$product->getId();
            if ($productId <= 0) {
                throw new NoSuchEntityException(__('Product not found.'));
            }

            $file = $this->uploadFile();
            $rows = $this->readRows($file['path'], $file['ext']);

            $stats = $this->importRows($productId, $rows, $replace);

            $this->messageManager->addSuccessMessage(__(
                'Imported %1 row(s). Skipped %2 row(s).',
                $stats['imported'],
                $stats['skipped']
            ));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage() ?: __('Product not found.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Import failed: %1', $e->getMessage()));
        }

        return $resultRedirect;
    }

    /**
     * @return array{path: string, ext: string}
     * @throws LocalizedException
     */
    private function uploadFile(): array
    {
        $uploader = $this->uploaderFactory->create(['fileId' => 'import_file']);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $uploader->setAllowedExtensions(['csv', 'xlsx']);

        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $dest = $varDir->getAbsolutePath('platformz_cutpoint_import');
        $varDir->create('platformz_cutpoint_import');

        $result = $uploader->save($dest);
        if (!$result || empty($result['path']) || empty($result['file'])) {
            throw new LocalizedException(__('File upload failed.'));
        }

        $path = rtrim((string)$result['path'], '/') . '/' . ltrim((string)$result['file'], '/');
        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        return ['path' => $path, 'ext' => $ext];
    }

    /**
     * @return array<int, array<string, string|null>>
     * @throws LocalizedException
     */
    private function readRows(string $absolutePath, string $ext): array
    {
        if ($ext === 'csv') {
            $data = $this->csv->getData($absolutePath);
            return $this->normalizeTabularData($data);
        }

        if ($ext === 'xlsx') {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new LocalizedException(
                    __(
                        'XLSX import requires PhpSpreadsheet (phpoffice/phpspreadsheet). Please install it, or upload CSV instead.'
                    )
                );
            }

            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($absolutePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            // Convert keyed-by-column-letter rows into indexed arrays
            $indexed = [];
            foreach ($data as $row) {
                $indexed[] = array_values($row);
            }

            return $this->normalizeTabularData($indexed);
        }

        throw new LocalizedException(__('Unsupported file type.'));
    }

    /**
     * @param array $data Each row is an indexed array, first row is header
     * @return array<int, array<string, string|null>>
     * @throws LocalizedException
     */
    private function normalizeTabularData(array $data): array
    {
        if (count($data) < 2) {
            throw new LocalizedException(__('The file is empty or missing data rows.'));
        }

        $header = array_map(
            static fn($v) => strtolower(trim((string)$v)),
            $data[0]
        );

        $required = [
            'manufacturer',
            'model',
            'size',
            'cutpoint',
        ];

        $colIndex = [];
        foreach ($header as $idx => $name) {
            if ($name === 'cut point') {
                $name = 'cutpoint';
            }
            if ($name === 'additional info') {
                $name = 'additional_info';
            }
            $colIndex[$name] = (int)$idx;
        }

        foreach ($required as $req) {
            if (!array_key_exists($req, $colIndex)) {
                throw new LocalizedException(
                    __(
                        'Missing required column "%1". Required headers: Manufacturer, Model, Size, CutPoint, Additional Info (optional).',
                        $req
                    )
                );
            }
        }

        $rows = [];
        for ($i = 1; $i < count($data); $i++) {
            $r = $data[$i];

            $manufacturer = trim((string)($r[$colIndex['manufacturer']] ?? ''));
            $model = trim((string)($r[$colIndex['model']] ?? ''));
            $size = trim((string)($r[$colIndex['size']] ?? ''));
            $cutPoint = trim((string)($r[$colIndex['cutpoint']] ?? ''));
            $additionalInfo = isset($colIndex['additional_info'])
                ? trim((string)($r[$colIndex['additional_info']] ?? ''))
                : '';

            // skip empty lines
            if ($manufacturer === '' && $model === '' && $size === '' && $cutPoint === '' && $additionalInfo === '') {
                continue;
            }

            $rows[] = [
                'manufacturer' => $manufacturer,
                'model' => $model,
                'size' => $size,
                'cut_point' => $cutPoint,
                'additional_info' => $additionalInfo !== '' ? $additionalInfo : null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, string|null>> $rows
     * @return array{imported:int, skipped:int}
     * @throws LocalizedException
     */
    private function importRows(int $productId, array $rows, bool $replace): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        if ($replace) {
            $connection->delete($table, ['product_id = ?' => $productId]);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $manufacturer = trim((string)($row['manufacturer'] ?? ''));
            $model = trim((string)($row['model'] ?? ''));
            $size = trim((string)($row['size'] ?? ''));
            $cutPoint = trim((string)($row['cut_point'] ?? ''));
            $additionalInfo = $row['additional_info'] ?? null;

            if ($manufacturer === '' || $model === '' || $size === '' || $cutPoint === '') {
                $skipped++;
                continue;
            }

            $connection->insertOnDuplicate(
                $table,
                [
                    'product_id' => $productId,
                    'manufacturer' => $manufacturer,
                    'model' => $model,
                    'size' => $size,
                    'cut_point' => $cutPoint,
                    'additional_info' => $additionalInfo,
                ],
                ['cut_point', 'additional_info']
            );
            $imported++;
        }

        if ($imported === 0) {
            throw new LocalizedException(__('No valid rows found to import.'));
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}

