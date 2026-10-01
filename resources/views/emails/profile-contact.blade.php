<x-mail::message>
# Contact request for {{ $profileName }}

{{ $notificationText }}

<x-mail::panel>
This message was sent through your Jannayaks profile page. The visitor's details are shown only to you and are not displayed publicly.
</x-mail::panel>

Thanks,
{{ config('app.name') }}
</x-mail::message>
