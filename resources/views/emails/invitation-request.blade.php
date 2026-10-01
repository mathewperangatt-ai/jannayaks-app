<x-mail::message>
# Invitation request — {{ $invitation->name }}

**Contact:** {{ $invitation->contact }}
@if($invitation->town)**Town:** {{ $invitation->town }}@endif
@if($invitation->role)**Role:** {{ $invitation->role }}@endif

**About the person:**

{{ $invitation->reason }}

<x-mail::panel>
A request does not guarantee inclusion; Jannayaks editorial selection applies, and inclusion is not an electoral or political endorsement.
</x-mail::panel>
</x-mail::message>
