<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\DataObject;

class QuickOrderService
{
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly Cart $cart
    ) {
    }

    /**
     * Adds each supported SKU independently so invalid or unavailable lines do not discard valid ones.
     *
     * @param array<int, array{sku: string, qty: float, line?: int}> $rows
     * @return array{added: int, errors: string[]}
     */
    public function addRows(array $rows): array
    {
        $added = 0;
        $errors = [];
        $storeId = (int)$this->cart->getQuote()->getStoreId();

        $valid = [];
        foreach (array_slice($rows, 0, CsvImporter::MAX_ROWS) as $row) {
            $sku = trim((string)($row['sku'] ?? ''));
            $qty = (float)($row['qty'] ?? 0);
            $line = (int)($row['line'] ?? 0);
            if ($sku === '' || strlen($sku) > 64 || !is_finite($qty) || $qty <= 0 || $qty > CsvImporter::MAX_QTY) {
                $errors[] = (string)__('Line %1: invalid SKU or quantity.', $line ?: '?');
                continue;
            }
            $valid[] = [$sku, $qty, $line ?: '?'];
        }
        $products = $valid ? $this->loadProducts(array_column($valid, 0), $storeId) : [];

        foreach ($valid as [$sku, $qty, $line]) {
            $product = $products[strtolower($sku)] ?? null;
            if ($product === null) {
                $errors[] = (string)__('Line %1: SKU "%2" was not found.', $line, $sku);
                continue;
            }
            if (!in_array($product->getTypeId(), [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL], true)) {
                $errors[] = (string)__('Line %1: SKU "%2" is not a supported simple or virtual product.', $line, $sku);
                continue;
            }
            try {
                $this->cart->addProduct($product, new DataObject(['qty' => $qty]));
                $added++;
            } catch (\Exception $exception) {
                $errors[] = (string)__('Line %1: could not add SKU "%2": %3', $line, $sku, $exception->getMessage());
            }
        }

        if ($added > 0) {
            try {
                $this->cart->save();
            } catch (\Exception $exception) {
                $errors[] = (string)__('The cart could not be saved: %1', $exception->getMessage());
                $added = 0;
            }
        }

        return ['added' => $added, 'errors' => $errors];
    }

    /**
     * One collection load for all SKUs instead of one repository load per line. Loaded the way the
     * quote's own item collection loads cart products (store scope, custom options, no stock filter,
     * so out-of-stock lines get Magento's stock error rather than "not found").
     *
     * @param string[] $skus
     * @return array<string, Product> keyed by lower-cased SKU
     */
    private function loadProducts(array $skus, int $storeId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('sku', ['in' => array_values(array_unique($skus))])
            ->setFlag('has_stock_status_filter', true);
        $collection->addOptionsToResult();

        $products = [];
        foreach ($collection->getItems() as $product) {
            $products[strtolower((string)$product->getSku())] = $product;
        }
        return $products;
    }
}
