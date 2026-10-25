@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between">
        <span class="tag">MALAYALAM TEXT</span>
        <a class="btn ghost" style="padding:8px 12px;min-height:36px;font-size:14px" href="{{ route('applications.show', $application) }}">← Back to dashboard</a>
    </div>

    <h1>Review the Malayalam profile</h1>
    <p class="lead" style="margin:0">This is the exact Malayalam text prepared for your published profile. Approving it records your consent for this version only. It does not change the published English text.</p>

    @if (session('status'))
        <p class="lead" style="color:var(--ok);margin:0">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="alert" style="color:var(--bad)">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <h2 style="margin-top:8px;font-size:18px">{{ $malayalam->title }}</h2>
    @if(filled($malayalam->summary))
        <p class="lead" style="margin:0">{{ $malayalam->summary }}</p>
    @endif
    <div class="stack" style="margin-top:8px;white-space:pre-wrap">{{ $malayalam->body }}</div>
    <p class="sub" style="margin:0">Version {{ $malayalam->version_number }}</p>

    @if($alreadyConsented)
        <p class="lead" style="color:var(--ok);margin:0">Your consent for this Malayalam text is recorded.</p>
        <p class="lead" style="margin:0">
            @if($alreadyPublished)
                This version is published on your profile. English approval and publication are unchanged.
            @else
                The next step is publication by the editorial team. English approval and publication are unchanged.
            @endif
        </p>
    @else
        <form method="post" action="{{ route('applications.malayalam-consent.store', $application) }}" class="stack">
            @csrf
            <input type="hidden" name="malayalam_editorial_content_id" value="{{ $malayalam->id }}">
            <button type="submit" class="btn primary">I approve this Malayalam text</button>
        </form>
    @endif
</section>
@endsection
