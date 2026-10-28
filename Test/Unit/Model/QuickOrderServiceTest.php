<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Checkout\Model\Cart;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bQuickOrder\Model\QuickOrderService;

class QuickOrderServiceTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private Cart&MockObject $cart;
    private Quote&MockObject $quote;
    private QuickOrderService $service;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods(['getStoreId'])->getMock();
        $this->quote->method('getStoreId')->willReturn(4);
        $this->cart = $this->getMockBuilder(Cart::class)->disableOriginalConstructor()->onlyMethods(['getQuote', 'addProduct', 'save'])->getMock();
        $this->cart->method('getQuote')->willReturn($this->quote);
        $this->service = new QuickOrderService($this->productRepository, $this->cart);
    }

    public function testAddsSupportedProductAndReportsUnsupportedType(): void
    {
        $simple = $this->createMock(ProductInterface::class);
        $simple->method('getTypeId')->willReturn(Type::TYPE_SIMPLE);
        $virtual = $this->createMock(ProductInterface::class);
        $virtual->method('getTypeId')->willReturn(Type::TYPE_VIRTUAL);
        $configurable = $this->createMock(ProductInterface::class);
        $configurable->method('getTypeId')->willReturn('configurable');
        $this->productRepository->expects(self::exactly(3))->method('get')->willReturnMap([
            ['SKU-1', false, 4, $simple],
            ['SKU-V', false, 4, $virtual],
            ['SKU-2', false, 4, $configurable],
        ]);
        $addedTypes = [];
        $addedQuantities = [];
        $this->cart->expects(self::exactly(2))->method('addProduct')->willReturnCallback(
            static function (ProductInterface $product, DataObject $request) use (&$addedTypes, &$addedQuantities): void {
                $addedTypes[] = $product->getTypeId();
                $addedQuantities[] = (float)$request->getData('qty');
            }
        );
        $this->cart->expects(self::once())->method('save');

        $result = $this->service->addRows([
            ['sku' => 'SKU-1', 'qty' => 2.0, 'line' => 1],
            ['sku' => 'SKU-V', 'qty' => 1.0, 'line' => 2],
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
        $this->productRepository->expects(self::once())->method('get')->with('MISSING', false, 4)
            ->willThrowException(new \RuntimeException('not found'));
        $this->cart->expects(self::never())->method('addProduct');
        $this->cart->expects(self::never())->method('save');

        $result = $this->service->addRows([['sku' => 'MISSING', 'qty' => 1.0, 'line' => 1]]);

        self::assertSame(0, $result['added']);
        self::assertCount(1, $result['errors']);
    }

    public function testRejectsInvalidRowsBeforeLookingUpProducts(): void
    {
        $this->productRepository->expects(self::never())->method('get');

        $result = $this->service->addRows([['sku' => '', 'qty' => -1.0, 'line' => 5]]);

        self::assertSame(0, $result['added']);
        self::assertCount(1, $result['errors']);
    }
}
