<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $profile->display_name ?: $profile->full_name }} — Jannayaks</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
</head>
<body style="font-family:Georgia,serif;max-width:40rem;margin:2rem auto;padding:0 1rem;color:#1b1b18">
    <p style="letter-spacing:.08em;font-size:.75rem;text-transform:uppercase;color:#6b6b66">Jannayaks profile</p>
    <h1 style="font-size:1.75rem;margin:0 0 .5rem">{{ $profile->display_name ?: $profile->full_name }}</h1>
    @if($profile->bio_headline)
        <p style="margin:0 0 1rem;color:#4b4b48">{{ $profile->bio_headline }}</p>
    @endif
    <p style="margin:0;color:#6b6b66;font-size:.95rem">
        Public profile presentation will be expanded in a later phase. This page confirms the canonical URL
        <code>{{ $canonicalUrl }}</code>.
    </p>
</body>
</html>
