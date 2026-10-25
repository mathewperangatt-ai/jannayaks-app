<x-mail::message>
# Someone has recommended you to Jannayaks

@if($recommendation->share_recommender_name && filled(trim((string) $recommendation->recommender_name)))
{{ $recommendation->recommender_name }} recommended you to Jannayaks and wrote:
@else
Someone recommended you to Jannayaks and wrote:
@endif

{{ $recommendation->reason }}

@if(filled($recommendation->supporting_info))
{{ $recommendation->supporting_info }}
@endif

A recommendation does not guarantee selection or inclusion.

Jannayaks has not created a profile or an account for you. If you would like to be considered, you can learn about Jannayaks and apply yourself. This is a single invitation. It does not subscribe you to marketing messages or to any further invitations.

<x-mail::button :url="route('home')">
Learn about Jannayaks
</x-mail::button>

<x-mail::button :url="route('apply')">
Apply yourself
</x-mail::button>
</x-mail::message>
