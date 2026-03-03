@component('mail::message')
# Your One-Time Password (OTP)

Hello,

Your OTP code is:

@component('mail::panel')
{{ $otp }}
@endcomponent

This code is valid for **5 minutes**.
Do not share this code with anyone.

Thanks,<br>
{{ config('app.name') }}
@endcomponent