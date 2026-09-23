<?php

namespace App\Http\Controllers;

use App\Actions\Companies\UpdateCompanyDetails;
use App\Enums\CompanyStatus;
use App\Http\Requests\CompanyUpdateRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * «Компания» в кабинете (ТЗ §8: /account/company; §11): реквизиты организации, банка и
 * контакт. Смена ИНН или юр. названия возвращает компанию на проверку — UpdateCompanyDetails.
 * У клиента без компании раздела нет: он ведёт на заявку на опт.
 */
final class AccountCompanyController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $company = $this->company($request);

        if ($company === null) {
            return redirect()->route('wholesale');
        }

        Gate::authorize('update', $company);

        /** @var User $user */
        $user = $request->user();

        return view('account.company', ['user' => $user, 'company' => $company]);
    }

    public function update(CompanyUpdateRequest $request, UpdateCompanyDetails $update): RedirectResponse
    {
        /** @var Company $company */
        $company = $this->company($request);

        $wasPending = $company->status === CompanyStatus::Pending;

        $update->handle($company, $request->validated());

        $recheck = ! $wasPending && $company->status === CompanyStatus::Pending;

        return redirect()->route('account.company')
            ->with('notice', ['text' => __($recheck ? 'shop.account.company.saved_recheck' : 'shop.account.company.saved')]);
    }

    private function company(Request $request): ?Company
    {
        /** @var User $user */
        $user = $request->user();

        return $user->company;
    }
}
