<?php

namespace App\Actions\BulkOrder;

use App\Actions\Cart\AddToCart;
use App\Models\User;
use App\Services\BulkOrder\BulkOrderLine;
use App\Services\BulkOrder\BulkOrderMatcher;
use App\Services\BulkOrder\BulkOrderStatus;
use App\Services\Cart\CannotAddToCart;

/**
 * «Добавить в корзину» из заказа списком (ТЗ §11): найденные строки и строки, где клиент
 * выбрал товар из нескольких совпадений, ложатся в корзину через AddToCart — по текущим
 * ценам и с теми же правилами, что на витрине. Сопоставление повторяется: между превью и
 * нажатием цена или наличие могли измениться. Цена по запросу, снятые и ненайденные —
 * не добавляются.
 */
final class AddBulkOrderToCart
{
    public function __construct(
        private readonly BulkOrderMatcher $matcher,
        private readonly AddToCart $addToCart,
    ) {}

    /**
     * @param  list<BulkOrderLine>  $lines
     * @param  array<int|string, mixed>  $choices  line row => product id chosen for a line with several matches
     * @return array{added: int, skipped: int}
     */
    public function handle(array $lines, array $choices, User $user): array
    {
        $added = 0;
        $skipped = 0;

        foreach ($this->matcher->match($lines, $user) as $match) {
            $product = match ($match->status) {
                BulkOrderStatus::Found => $match->product(),
                BulkOrderStatus::Multiple => collect($match->candidates)->first(
                    fn ($candidate): bool => (string) $candidate->id === (string) ($choices[$match->line->row] ?? '') && $match->isBuyable($candidate),
                ),
                default => null,
            };

            if ($product === null) {
                $skipped++;

                continue;
            }

            try {
                $this->addToCart->handle($product, $match->line->qty, $user);
                $added++;
            } catch (CannotAddToCart) {
                $skipped++;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}
