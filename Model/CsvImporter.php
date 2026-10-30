<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Model;

/** Parses pasted SKU/quantity rows and uploaded CSV files. */
class CsvImporter
{
    public const MAX_ROWS = 500;
    public const MAX_QTY = 10000.0;
    public const MAX_BYTES = 1048576;
    private const QTY_HEADERS = ['qty', 'quantity'];

    /**
     * @return array{rows: array<int, array{sku: string, qty: float, line: int}>, errors: string[]}
     */
    public function parse(string $csv): array
    {
        if (strlen($csv) > self::MAX_BYTES) {
            return ['rows' => [], 'errors' => [(string)__('Quick order input must be no larger than 1 MB.')]];
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return ['rows' => [], 'errors' => [(string)__('Could not read the quick order list.')]];
        }
        fwrite($stream, $csv);
        rewind($stream);

        $rows = [];
        $errors = [];
        $line = 0;
        $skuColumn = 0;
        $qtyColumn = 1;
        $headerChecked = false;
        $processed = 0;
        while (($columns = fgetcsv($stream, 8192, ',', '"', '')) !== false) {
            $line++;
            if ($columns === [null] || array_filter($columns, static fn($value): bool => trim((string)$value) !== '') === []) {
                continue;
            }
            $columns = array_map(static fn($value): string => trim((string)$value), $columns);

            if (!$headerChecked) {
                $headerChecked = true;
                $header = array_map(static fn(string $value): string => strtolower($value), $columns);
                $headerQty = array_values(array_intersect($header, self::QTY_HEADERS));
                if (in_array('sku', $header, true) && $headerQty) {
                    $skuColumn = (int)array_search('sku', $header, true);
                    $qtyColumn = (int)array_search($headerQty[0], $header, true);
                    continue;
                }
            }

            if ($processed >= self::MAX_ROWS) {
                $errors[] = (string)__('Only the first %1 order lines can be processed.', self::MAX_ROWS);
                break;
            }
            $processed++;

            $sku = $columns[$skuColumn] ?? '';
            $qtyText = $columns[$qtyColumn] ?? '';
            if ($sku === '' || strlen($sku) > 64) {
                $errors[] = (string)__('Line %1: enter a SKU up to 64 characters long.', $line);
                continue;
            }
            if ($qtyText === '' || !is_numeric($qtyText)) {
                $errors[] = (string)__('Line %1: enter a numeric quantity.', $line);
                continue;
            }
            $qty = (float)$qtyText;
            if (!is_finite($qty) || $qty <= 0 || $qty > self::MAX_QTY) {
                $errors[] = (string)__('Line %1: quantity must be greater than 0 and no more than %2.', $line, self::MAX_QTY);
                continue;
            }

            $rows[] = ['sku' => $sku, 'qty' => $qty, 'line' => $line];
        }

        fclose($stream);
        if (!$rows && !$errors) {
            $errors[] = (string)__('Enter at least one SKU and quantity.');
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
