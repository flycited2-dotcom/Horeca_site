<?php

namespace App\Http\Controllers;

use App\Actions\Leads\CreateLead;
use App\Enums\LeadType;
use App\Http\Middleware\RememberUtm;
use App\Http\Requests\LeadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Короткие заявки всех типов (ТЗ §8: POST /leads). Не больше 10 в час с одного IP
 * (§10.4, §15). Скрипт витрины получает JSON и закрывает окно на месте; без скриптов —
 * возврат на страницу с уведомлением.
 */
class LeadController extends Controller
{
    public const int PER_IP = 10;

    public function store(LeadRequest $request, CreateLead $create): JsonResponse|RedirectResponse
    {
        $key = 'leads:ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::PER_IP)) {
            throw ValidationException::withMessages(['form' => __('shop.leads.too_many')])->errorBag('lead');
        }

        $lead = $create->handle($request->validated(), [
            'ip' => $request->ip(),
            'utm' => $request->session()->get(RememberUtm::SESSION_KEY),
        ]);

        RateLimiter::hit($key, 3600);

        $notice = ['text' => __('shop.leads.sent.'.($lead->type === LeadType::NotFound ? 'not_found' : 'default'))];

        return $request->expectsJson()
            ? response()->json(['notice' => $notice])
            : redirect()->back()->with('notice', $notice);
    }
}
