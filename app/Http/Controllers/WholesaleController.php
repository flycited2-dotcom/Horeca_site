<?php

namespace App\Http\Controllers;

use App\Actions\Companies\ApplyForWholesale;
use App\Http\Requests\WholesaleApplicationRequest;
use App\Models\Page;
use App\Models\User;
use App\Services\Settings\Settings;
use App\View\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * «Оптовым клиентам» (ТЗ §8: /wholesale, §11; макет — экраны 15c и 15d). Гостю и клиенту
 * без компании — лендинг и форма заявки; клиенту с компанией — статус его заявки. Выгоды
 * на лендинге — только из страницы «Оптовикам», которую ведёт администратор: утверждения
 * о ценах, отсрочке и менеджере даёт заказчик (§11).
 */
final class WholesaleController extends Controller
{
    public const string BENEFITS_PAGE = 'optovikam';

    public function show(Request $request, Settings $settings): View
    {
        /** @var User|null $user */
        $user = $request->user();
        $company = $user?->company()->with('priceTier:id,name')->first();

        if ($company !== null) {
            return view('wholesale.status', [
                'company' => $company,
                'phone' => $settings->get('contacts.phones'),
                'showTier' => $settings->boolean('pricing.show_tier_name', false),
            ]);
        }

        $benefits = Page::query()->where('slug', self::BENEFITS_PAGE)->where('is_active', true)->value('content');

        return view('wholesale.show', [
            'user' => $user,
            'benefits' => is_string($benefits) && trim($benefits) !== '' ? RichText::html($benefits) : null,
            'started' => WholesaleApplicationRequest::openedAt(),
        ]);
    }

    public function store(WholesaleApplicationRequest $request, ApplyForWholesale $apply): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        /** @var array{inn: string, legal_name: string, segment: string, city: string, contact_person: string, email: string, phone: string, comment?: ?string, password?: string} $data */
        $data = $request->validated();

        $company = $apply->handle($data, $user);

        if ($user === null) {
            Auth::guard('web')->login($company->users()->firstOrFail());
            $request->session()->regenerate();
        }

        return redirect()->route('wholesale')
            ->with('notice', ['text' => __('shop.wholesale.sent', ['email' => $company->email])]);
    }
}
