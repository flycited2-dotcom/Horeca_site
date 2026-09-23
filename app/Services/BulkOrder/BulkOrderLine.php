<?php

namespace App\Services\BulkOrder;

/**
 * Строка заказа списком (ТЗ §11): номер строки в списке или файле, артикул как его ввели,
 * количество и ошибка разбора — «sku», если артикула нет, «qty», если количество не целое
 * от 1 до 9999. Хранится в сессии между превью и «Добавить в корзину».
 */
final readonly class BulkOrderLine
{
    public function __construct(
        public int $row,
        public string $sku,
        public int $qty,
        public ?string $error = null,
    ) {}

    /**
     * @return array{row: int, sku: string, qty: int, error: ?string}
     */
    public function toArray(): array
    {
        return ['row' => $this->row, 'sku' => $this->sku, 'qty' => $this->qty, 'error' => $this->error];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function fromArray(array $line): self
    {
        return new self(
            (int) ($line['row'] ?? 0),
            (string) ($line['sku'] ?? ''),
            (int) ($line['qty'] ?? 1),
            isset($line['error']) ? (string) $line['error'] : null,
        );
    }
}
