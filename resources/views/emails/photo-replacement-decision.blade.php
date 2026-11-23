Jannayaks — your replacement photograph request
@if($approved)
Dear {{ $request->member?->name ?? 'profile owner' }},

Your replacement photograph for {{ $request->profile->slug }} has been reviewed and APPROVED by our security editorial team, and it is now live on your published profile:

  https://jannayaks.in/{{ $request->profile->slug }}

@else
Dear {{ $request->member?->name ?? 'profile owner' }},

Thank you for your replacement photograph submission for {{ $request->profile->slug }}.

After security review, we are unable to publish it at this time.
@if(!empty($request->review_note))
Reviewer note: {{ $request->review_note }}
@endif

Your published profile remains unchanged. You are welcome to submit a new photograph at any time through your profile maintenance page.
@endif

Submission reference: #{{ $request->id }}
Submitted: {{ $request->created_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i') }} IST

— Jannayaks editorial security team
