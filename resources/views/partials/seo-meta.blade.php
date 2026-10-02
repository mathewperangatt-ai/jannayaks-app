{{-- SEO-1: single shared mechanism for page metadata.
     Receives: $title (required), $description, $canonical, $image, $type,
     $locale — all optional with config/request defaults. Outputs meta
     description + Open Graph + Twitter card. Indexing directives are NOT
     handled here: the construction-stage noindex stays in each shell's head. --}}
@php($seoTitle = trim((string) ($title ?? '')) !== '' ? trim((string) $title) : (string) config('app.name', 'Jannayaks'))
@php($seoDescription = trim((string) ($description ?? '')))
@php($seoUrl = trim((string) ($canonical ?? '')) !== '' ? $canonical : url()->current())
{{-- The partial is the ONLY canonical emitter (SEO-2): one self-referencing
     canonical per page. Profile/memorial canonical values flow in via the
     seoCanonical section; static pages pass their route() base URL, so query
     strings never enter the canonical. --}}
<link rel="canonical" href="{{ $seoUrl }}">
@php($seoImage = trim((string) ($image ?? '')) !== '' ? $image : asset((string) config('jannayaks.seo.share_image', 'branding/jannayaks-logo-approved.jpg')))
@php($seoType = trim((string) ($type ?? '')) !== '' ? $type : 'website')
@php($seoLocale = trim((string) ($locale ?? '')) !== '' ? $locale : 'en_IN')
@if($seoDescription !== '')<meta name="description" content="{{ $seoDescription }}">
@endif<meta property="og:site_name" content="{{ (string) config('jannayaks.seo.site_name', 'Jannayaks') }}">
<meta property="og:title" content="{{ $seoTitle }}">
@if($seoDescription !== '')<meta property="og:description" content="{{ $seoDescription }}">
@endif<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:locale" content="{{ $seoLocale }}">
<meta name="twitter:card" content="{{ (string) config('jannayaks.seo.twitter_card', 'summary') }}">
<meta name="twitter:title" content="{{ $seoTitle }}">
@if($seoDescription !== '')<meta name="twitter:description" content="{{ $seoDescription }}">
@endif<meta name="twitter:image" content="{{ $seoImage }}">
