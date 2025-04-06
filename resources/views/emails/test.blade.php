@component('mail::message')
# Test Email

This is a test email from the {{ config('app.name') }} application.

@component('mail::button', ['url' => url('/')])
Visit Our Site
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent 