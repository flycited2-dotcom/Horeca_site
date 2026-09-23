{{-- Заявка на опт менеджерам (ТЗ §11, §13): реквизиты и контакт таблицей, кнопка в админку. --}}
@php
    $rows = [
        __('admin.company.legal_name') => $company->legal_name,
        __('admin.company.inn') => $company->inn,
        __('admin.company.segment') => $company->segment->getLabel(),
        __('admin.company.city') => $company->city,
        __('admin.company.contact_person') => $company->contact_person,
        __('admin.company.phone') => $company->phone,
        __('admin.company.email') => $company->email,
        __('shop.wholesale.fields.comment') => $company->comment,
    ];
@endphp

<x-mail.frame :title="__('notifications.wholesale.managers_subject', ['company' => $company->legal_name])" :heading="__('notifications.wholesale.managers_heading')">
    <p style="margin:0 0 16px 0;">{{ __('notifications.wholesale.managers_intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
        @foreach ($rows as $label => $value)
            @if (filled($value))
                <tr>
                    <td style="padding:6px 12px 6px 0; border-bottom:1px solid #e7ebee; color:#5d686f; vertical-align:top; white-space:nowrap;">{{ $label }}</td>
                    <td style="padding:6px 0; border-bottom:1px solid #e7ebee; vertical-align:top;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <p style="margin:20px 0 0 0;">
        <a href="{{ $adminUrl }}" style="display:inline-block; padding:12px 20px; border-radius:6px; background-color:#0072b0; color:#ffffff; font-weight:500; text-decoration:none;">{{ __('notifications.wholesale.open') }}</a>
    </p>
</x-mail.frame>
