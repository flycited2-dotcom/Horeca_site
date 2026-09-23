<?php

namespace App\Actions\BulkOrder;

use App\Services\BulkOrder\BulkOrderLine;
use App\Services\Cart\CartRules;
use DateInterval;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Разбор заказа списком (ТЗ §11): строки «артикул;количество» или «артикул<TAB>количество» —
 * так их вставляют из таблицы, — либо первый лист XLSX: артикул в первой колонке, количество
 * во второй. Без количества — одна штука. Пустые строки пропускаются, строка-заголовок файла
 * тоже. Больше 500 строк не берём — список лучше разбить.
 */
final class ParseBulkOrderList
{
    public const int MAX_LINES = 500;

    /**
     * @return list<BulkOrderLine>
     */
    public function fromText(string $text, string $field = 'list'): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $index => $raw) {
            if (trim($raw) === '') {
                continue;
            }

            $parts = preg_split('/[;\t]/u', $raw, 2) ?: [$raw];
            $lines[] = $this->line($index + 1, $parts[0], $parts[1] ?? null);
            $this->guardSize($lines, $field);
        }

        return $lines;
    }

    /**
     * @return list<BulkOrderLine>
     */
    public function fromXlsx(string $path, string $field = 'file'): array
    {
        // Empty rows are kept, so a line is numbered as in the spreadsheet.
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => __('shop.bulk.errors.file_unreadable')]);
        }

        $lines = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $cells = $row->toArray();
                    $sku = $this->cellText($cells[0] ?? null);
                    $qty = $this->cellText($cells[1] ?? null);

                    if ($sku === '' && $qty === '') {
                        continue;
                    }

                    // A heading such as «Артикул | Количество» is not an order line.
                    if ($lines === [] && $index === 1 && $qty !== '' && ! is_numeric(str_replace(',', '.', $qty))) {
                        continue;
                    }

                    $lines[] = $this->line($index, $sku, $qty);
                    $this->guardSize($lines, $field);
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $lines;
    }

    private function line(int $row, string $sku, ?string $qty): BulkOrderLine
    {
        $sku = trim((string) preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $sku));
        $qty = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', (string) $qty) ?? '';

        if ($sku === '') {
            return new BulkOrderLine($row, '', 1, 'sku');
        }

        if ($qty === '') {
            return new BulkOrderLine($row, $sku, 1);
        }

        if (preg_match('/^(\d+)(?:[.,]0+)?$/', $qty, $match) !== 1 || (int) $match[1] < 1 || (int) $match[1] > CartRules::MAX_QUANTITY) {
            return new BulkOrderLine($row, $sku, 1, 'qty');
        }

        return new BulkOrderLine($row, $sku, (int) $match[1]);
    }

    /**
     * A cell as text: a number the spreadsheet keeps as 1.1000019106E10 becomes «11000019106».
     */
    private function cellText(mixed $value): string
    {
        return match (true) {
            is_int($value) => (string) $value,
            is_float($value) => floor($value) === $value ? sprintf('%.0f', $value) : (string) $value,
            is_string($value) => trim($value),
            $value instanceof DateTimeInterface, $value instanceof DateInterval, is_bool($value) => '?',
            default => '',
        };
    }

    /**
     * @param  list<BulkOrderLine>  $lines
     */
    private function guardSize(array $lines, string $field): void
    {
        if (count($lines) > self::MAX_LINES) {
            throw ValidationException::withMessages([$field => __('shop.bulk.errors.too_many', ['max' => self::MAX_LINES])]);
        }
    }
}
