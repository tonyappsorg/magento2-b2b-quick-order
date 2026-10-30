<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Tonyapps\B2bQuickOrder\Model\CsvImporter;

class CsvImporterTest extends TestCase
{
    public function testParsesHeaderedCsvAndUtf8Bom(): void
    {
        $result = (new CsvImporter())->parse("\xEF\xBB\xBFsku,qty\nSKU-1,2\nSKU-2,1.5\n");

        self::assertSame([], $result['errors']);
        self::assertSame([
            ['sku' => 'SKU-1', 'qty' => 2.0, 'line' => 2],
            ['sku' => 'SKU-2', 'qty' => 1.5, 'line' => 3],
        ], $result['rows']);
    }

    public function testAcceptsQuantityHeaderInAnyColumnOrder(): void
    {
        $result = (new CsvImporter())->parse("Quantity,SKU\n2,SKU-1\n");

        self::assertSame([], $result['errors']);
        self::assertSame([['sku' => 'SKU-1', 'qty' => 2.0, 'line' => 2]], $result['rows']);
    }

    public function testParsesHeaderlessPastedRowsAndSkipsBlankLines(): void
    {
        $result = (new CsvImporter())->parse(" SKU-1 , 3 \n\nSKU-2,1\n");

        self::assertSame([], $result['errors']);
        self::assertCount(2, $result['rows']);
        self::assertSame('SKU-1', $result['rows'][0]['sku']);
        self::assertSame(3.0, $result['rows'][0]['qty']);
        self::assertSame(3, $result['rows'][1]['line']);
    }

    public function testReportsInvalidSkuAndQuantityRowsButKeepsValidRows(): void
    {
        $result = (new CsvImporter())->parse("sku,qty\n,1\nSKU-1,0\nSKU-2,2\n");

        self::assertCount(2, $result['errors']);
        self::assertCount(1, $result['rows']);
        self::assertSame('SKU-2', $result['rows'][0]['sku']);
    }

    public function testRejectsInputLargerThanOneMegabyte(): void
    {
        $result = (new CsvImporter())->parse(str_repeat('x', CsvImporter::MAX_BYTES + 1));

        self::assertSame([], $result['rows']);
        self::assertCount(1, $result['errors']);
    }

    public function testLimitsProcessedLines(): void
    {
        $csv = implode("\n", array_fill(0, CsvImporter::MAX_ROWS + 1, ',invalid'));

        $result = (new CsvImporter())->parse($csv);

        self::assertLessThanOrEqual(CsvImporter::MAX_ROWS + 1, count($result['errors']));
        $lastError = end($result['errors']);
        self::assertSame((string)__('Only the first %1 order lines can be processed.', CsvImporter::MAX_ROWS), $lastError);
    }
}
