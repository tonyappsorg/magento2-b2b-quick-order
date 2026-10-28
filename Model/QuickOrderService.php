<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Checkout\Model\Cart;
use Magento\Framework\DataObject;

class QuickOrderService
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param Cart $cart
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
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

        foreach (array_slice($rows, 0, CsvImporter::MAX_ROWS) as $row) {
            $sku = trim((string)($row['sku'] ?? ''));
            $qty = (float)($row['qty'] ?? 0);
            $line = (int)($row['line'] ?? 0);
            if ($sku === '' || strlen($sku) > 64 || !is_finite($qty) || $qty <= 0 || $qty > CsvImporter::MAX_QTY) {
                $errors[] = (string)__('Line %1: invalid SKU or quantity.', $line ?: '?');
                continue;
            }

            try {
                $product = $this->productRepository->get($sku, false, $storeId);
                if (!in_array($product->getTypeId(), [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL], true)) {
                    $errors[] = (string)__('Line %1: SKU "%2" is not a supported simple or virtual product.', $line ?: '?', $sku);
                    continue;
                }
                $this->cart->addProduct($product, new DataObject(['qty' => $qty]));
                $added++;
            } catch (\Exception $exception) {
                $errors[] = (string)__('Line %1: could not add SKU "%2": %3', $line ?: '?', $sku, $exception->getMessage());
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
}
