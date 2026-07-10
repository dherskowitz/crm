@component('mail::message')
# You're invited to {{ $appName }}

Hi {{ $inviteeName }},

@if($inviterName)
{{ $inviterName }} has invited you to join the {{ $appName }} CRM.
@else
You have been invited to join the {{ $appName }} CRM.
@endif

Click the button below to set your password and activate your account. This invitation link expires in 72 hours.

@component('mail::button', ['url' => $url])
Accept invitation
@endcomponent

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ $appName }}
@endcomponent
