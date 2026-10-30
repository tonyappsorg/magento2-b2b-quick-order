<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bQuickOrder\Model\QuickOrderService;

class QuickOrderServiceTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;
    private Collection&MockObject $collection;
    private Cart&MockObject $cart;
    private Quote&MockObject $quote;
    private QuickOrderService $service;

    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        foreach (['setStoreId', 'addStoreFilter', 'addAttributeToSelect', 'addAttributeToFilter', 'setFlag', 'addOptionsToResult'] as $method) {
            $this->collection->method($method)->willReturnSelf();
        }
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods(['getStoreId'])->getMock();
        $this->quote->method('getStoreId')->willReturn(4);
        $this->cart = $this->getMockBuilder(Cart::class)->disableOriginalConstructor()->onlyMethods(['getQuote', 'addProduct', 'save'])->getMock();
        $this->cart->method('getQuote')->willReturn($this->quote);
        $this->service = new QuickOrderService($this->collectionFactory, $this->cart);
    }

    public function testLoadsAllSkusInOneCollectionAndReportsUnsupportedType(): void
    {
        $this->collectionFactory->expects(self::once())->method('create');
        $this->collection->expects(self::once())->method('setStoreId')->with(4)->willReturnSelf();
        $this->collection->expects(self::once())->method('addAttributeToFilter')
            ->with('sku', ['in' => ['SKU-1', 'sku-v', 'SKU-2']])->willReturnSelf();
        $this->collection->method('getItems')->willReturn([
            $this->product('SKU-1', Type::TYPE_SIMPLE),
            $this->product('SKU-V', Type::TYPE_VIRTUAL),
            $this->product('SKU-2', 'configurable'),
        ]);
        $addedTypes = [];
        $addedQuantities = [];
        $this->cart->expects(self::exactly(2))->method('addProduct')->willReturnCallback(
            static function (Product $product, DataObject $request) use (&$addedTypes, &$addedQuantities): void {
                $addedTypes[] = $product->getTypeId();
                $addedQuantities[] = (float)$request->getData('qty');
            }
        );
        $this->cart->expects(self::once())->method('save');

        $result = $this->service->addRows([
            ['sku' => 'SKU-1', 'qty' => 2.0, 'line' => 1],
            ['sku' => 'sku-v', 'qty' => 1.0, 'line' => 2],
            ['sku' => 'SKU-2', 'qty' => 1.0, 'line' => 3],
        ]);

        self::assertSame(2, $result['added']);
        self::assertSame([Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL], $addedTypes);
        self::assertSame([2.0, 1.0], $addedQuantities);
        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('SKU-2', $result['errors'][0]);
    }

    public function testUnavailableSkuDoesNotSaveTheCart(): void
    {
        $this->collection->method('getItems')->willReturn([]);
        $this->cart->expects(self::never())->method('addProduct');
        $this->cart->expects(self::never())->method('save');

        $result = $this->service->addRows([['sku' => 'MISSING', 'qty' => 1.0, 'line' => 1]]);

        self::assertSame(0, $result['added']);
        self::assertSame(['Line 1: SKU "MISSING" was not found.'], $result['errors']);
    }

    public function testRejectsInvalidRowsBeforeLookingUpProducts(): void
    {
        $this->collectionFactory->expects(self::never())->method('create');

        $result = $this->service->addRows([['sku' => '', 'qty' => -1.0, 'line' => 5]]);

        self::assertSame(0, $result['added']);
        self::assertCount(1, $result['errors']);
    }

    private function product(string $sku, string $type): Product
    {
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods(['getSku', 'getTypeId'])->getMock();
        $product->method('getSku')->willReturn($sku);
        $product->method('getTypeId')->willReturn($type);
        return $product;
    }
}
