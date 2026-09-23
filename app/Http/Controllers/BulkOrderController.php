<?php

namespace App\Http\Controllers;

use App\Actions\BulkOrder\AddBulkOrderToCart;
use App\Actions\BulkOrder\ParseBulkOrderList;
use App\Actions\BulkOrder\RequestBulkOrderPrices;
use App\Http\Middleware\RememberUtm;
use App\Http\Requests\BulkOrderRequest;
use App\Models\User;
use App\Services\Analytics\Metrika;
use App\Services\BulkOrder\BulkOrderLine;
use App\Services\BulkOrder\BulkOrderMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Заказ списком (ТЗ §8: /account/bulk-order, §11; сценарий 4 из §2). Список проверяется
 * формой: строки разбираются и запоминаются в сессии, превью показывает по каждой строке,
 * что нашлось, по текущим ценам. Затем «Добавить в корзину» или «Запросить цену на эти
 * позиции». Всё — обычными формами, без скриптов. Открыт только одобренным оптовикам
 * (EnsureWholesaleApproved).
 */
final class BulkOrderController extends Controller
{
    public const string SESSION_KEY = 'bulk_order';

    public function show(Request $request, BulkOrderMatcher $matcher): View
    {
        /** @var User $user */
        $user = $request->user();
        $saved = $request->session()->get(self::SESSION_KEY);
        $matches = is_array($saved) ? $matcher->match($this->lines($saved), $user) : null;

        return view('account.bulk-order', [
            'user' => $user,
            'company' => $user->company()->first(['id', 'legal_name', 'inn', 'status']),
            'text' => is_array($saved) ? (string) ($saved['text'] ?? '') : '',
            'source' => is_array($saved) ? ($saved['source'] ?? null) : null,
            'matches' => $matches,
            'counts' => $matches === null ? [] : collect($matches)->countBy(fn ($match): string => $match->status->value)->all(),
        ]);
    }

    public function check(BulkOrderRequest $request, ParseBulkOrderList $parse): RedirectResponse
    {
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $lines = $parse->fromXlsx((string) $file->getRealPath());
            $text = '';
            $source = $file->getClientOriginalName();
        } else {
            $text = (string) $request->validated('list');
            $lines = $parse->fromText($text);
            $source = null;
        }

        if ($lines === []) {
            return back()->withInput()->withErrors(['list' => __('shop.bulk.errors.empty')]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'lines' => array_map(fn (BulkOrderLine $line): array => $line->toArray(), $lines),
            'text' => $text,
            'source' => $source,
        ]);

        return redirect()->route('account.bulk-order');
    }

    public function addToCart(Request $request, AddBulkOrderToCart $add): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saved = $request->session()->get(self::SESSION_KEY);

        if (! is_array($saved)) {
            return redirect()->route('account.bulk-order');
        }

        $choices = $request->input('choice', []);
        $result = $add->handle($this->lines($saved), is_array($choices) ? $choices : [], $user);

        if ($result['added'] === 0) {
            return redirect()->route('account.bulk-order')->with('notice', ['text' => __('shop.bulk.nothing_added')]);
        }

        $request->session()->forget(self::SESSION_KEY);
        Metrika::flash(Metrika::event('bulk_order', ['positions' => $result['added']]));

        return redirect()->route('cart')->with('notice', [
            'text' => $result['skipped'] > 0
                ? __('shop.bulk.added_skipped', ['added' => $result['added'], 'skipped' => $result['skipped']])
                : __('shop.bulk.added', ['added' => $result['added']]),
        ]);
    }

    public function requestPrices(Request $request, RequestBulkOrderPrices $requestPrices): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saved = $request->session()->get(self::SESSION_KEY);

        if (! is_array($saved)) {
            return redirect()->route('account.bulk-order');
        }

        $requestPrices->handle($this->lines($saved), $user, [
            'ip' => $request->ip(),
            'utm' => $request->session()->get(RememberUtm::SESSION_KEY),
        ]);

        return redirect()->route('account.bulk-order')->with('notice', ['text' => __('shop.bulk.prices_requested')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('account.bulk-order');
    }

    /**
     * An example file: the heading and two lines, so the customer sees the columns.
     */
    public function template(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([__('shop.bulk.template.sku'), __('shop.bulk.template.qty')], (new Style)->setFontBold()));
        $writer->addRow(Row::fromValues(['11000019106', 2]));
        $writer->addRow(Row::fromValues(['ЦБ-Ц0012345', 1]));
        $writer->close();

        return response()->download($path, 'zakaz-spiskom.xlsx')->deleteFileAfterSend();
    }

    /**
     * @param  array<string, mixed>  $saved
     * @return list<BulkOrderLine>
     */
    private function lines(array $saved): array
    {
        $lines = is_array($saved['lines'] ?? null) ? $saved['lines'] : [];

        return array_values(array_map(fn (mixed $line): BulkOrderLine => BulkOrderLine::fromArray(is_array($line) ? $line : []), $lines));
    }
}
