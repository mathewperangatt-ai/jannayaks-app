@extends('layouts.public', ['htmlLang' => $language === 'ml' ? 'ml' : 'en', 'nav' => $isDemonstration ? 'demo-profiles' : 'gallery'])
@php($listingUrl = $isDemonstration ? route('demo-profiles.index') : route('gallery.index'))
@php($listingLabel = $isDemonstration ? 'Demo Profiles' : 'Gallery')

@section('title', $displayName.' — Jannayaks')

@php($seoSource = trim((string) ($activeEditorial?->summary ?: $headline ?: $profession)))
@php($seoFirstSentence = preg_match('/^(.{40,}?[.!?])(\s|$)/us', $seoSource, $seoMatch) === 1 ? $seoMatch[1] : $seoSource)
@section('seoDescription', mb_strlen($seoFirstSentence) <= 200 ? $seoFirstSentence : rtrim(\Illuminate\Support\Str::limit($seoFirstSentence, 197, ''), ' ,;:').'…')
@section('seoCanonical', $canonicalUrl)
@section('seoImage', $photo ? route('profiles.public.photo', [$profile, $photo]) : '')
@section('seoType', 'profile')

@section('schemaJson', app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->profileGraph(
    $canonicalUrl,
    $displayName,
    trim((string) ($activeEditorial?->summary ?? '')) !== '' ? trim((string) $activeEditorial->summary) : null,
    $photo ? route('profiles.public.photo', [$profile, $photo]) : null,
    filled($profession) ? $profession : null,
    [['Home', route('home')], [$listingLabel, $listingUrl], [$displayName, $canonicalUrl]],
)))

@section('content')
@include('public.profile-content')
@endsection
