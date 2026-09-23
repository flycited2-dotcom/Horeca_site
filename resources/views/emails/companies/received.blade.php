{{-- Подтверждение клиенту: заявка на опт принята (ТЗ §11, §13). --}}
<x-mail.frame :title="__('notifications.wholesale.received_subject')" :heading="__('notifications.wholesale.received_heading')">
    <p style="margin:0 0 12px 0;">{{ __('notifications.wholesale.received_intro', ['name' => $company->contact_person, 'company' => $company->legal_name]) }}</p>
    <p style="margin:0 0 20px 0; color:#5d686f;">{{ __('notifications.wholesale.received_until') }}</p>

    <p style="margin:0;">
        <a href="{{ route('catalog') }}" style="display:inline-block; padding:12px 20px; border-radius:6px; background-color:#0072b0; color:#ffffff; font-weight:500; text-decoration:none;">{{ __('notifications.wholesale.approved_button') }}</a>
    </p>

    <x-mail.contacts :phone="$phone" :email="$email" />
</x-mail.frame>
