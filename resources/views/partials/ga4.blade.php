{{-- Google Analytics 4 (public marketing pages). Empty measurement ID = no output. --}}
@php
    $ga4MeasurementId = trim((string) config('jannayaks.ga4.measurement_id', ''));
    $ga4PageTitle = null;
    if ($ga4MeasurementId !== '') {
        if (request()->routeIs('profiles.public', 'profiles.public.legacy')) {
            // Never send the profile person's display name as page_title.
            $ga4PageTitle = 'Public profile';
        }
    }

    $ga4Config = [
        'anonymize_ip' => true,
        'allow_google_signals' => false,
        'allow_ad_personalization_signals' => false,
    ];
    if (is_string($ga4PageTitle) && $ga4PageTitle !== '') {
        $ga4Config['page_title'] = $ga4PageTitle;
    }
@endphp
@if ($ga4MeasurementId !== '')
<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga4MeasurementId) }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('set', 'allow_google_signals', false);
gtag('set', 'allow_ad_personalization_signals', false);
gtag('config', @json($ga4MeasurementId), @json($ga4Config));
</script>
@endif
