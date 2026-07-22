@component('mail::message')
# Partnership Invitation

Hello,

{{ $labName }} would like to partner with your clinic on Denta+.

Join Denta+ or sign in to review and accept the partnership request.

@component('mail::button', ['url' => $inviteUrl])
Join Denta+
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
