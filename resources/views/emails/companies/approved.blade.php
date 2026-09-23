{{-- «Оптовые цены открыты» (ТЗ §11, §13): компания проверена, цены — после входа в кабинет. --}}
<x-mail.frame :title="__('notifications.wholesale.approved_subject')" :heading="__('notifications.wholesale.approved_heading')">
    <p style="margin:0 0 20px 0;">{{ __('notifications.wholesale.approved_intro', ['company' => $company->legal_name]) }}</p>

    <p style="margin:0 0 8px 0;">
        <a href="{{ route('catalog') }}" style="display:inline-block; padding:12px 20px; border-radius:6px; background-color:#0072b0; color:#ffffff; font-weight:500; text-decoration:none;">{{ __('notifications.wholesale.approved_button') }}</a>
    </p>

    <x-mail.contacts :phone="$phone" :email="$email" />
</x-mail.frame>
