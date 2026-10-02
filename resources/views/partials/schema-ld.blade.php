{{-- SEO-4: emits the page's JSON-LD. Callers pass pre-encoded JSON
     (StructuredDataService::encode()) so encoding happens next to the data
     with the safe flag set; this partial only embeds it. --}}
@if(trim((string) ($json ?? '')) !== '')
<script type="application/ld+json">{!! $json !!}</script>
@endif
