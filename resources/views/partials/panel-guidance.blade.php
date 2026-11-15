{{-- Bilingual panel guidance (Phase 3, approved workstream L).
     Self-contained inline styles so the same partial renders correctly on
     the customer panel (layouts.app) and the public layout (preview page).
     Pass 'ml' => true|false on language-switched pages to render a single
     language; omit it to show both stacked.
--}}
@php($guidance = \App\Support\PanelGuidance::for((string) $key))
@php($guidanceMl = $ml ?? null)
<div role="note" aria-label="{{ $guidance['title_en'] }}" style="border:1px solid #dbe6f0;border-left:4px solid #214d68;background:#f4f8fc;border-radius:0 10px 10px 0;padding:12px 16px;margin:10px 0;font-size:13px;line-height:1.65">
    <span style="display:block;font-weight:600;letter-spacing:.05em;font-size:12px;color:#214d68;margin-bottom:6px">
        @if($guidanceMl === true)
            {{ $guidance['title_ml'] }}
        @elseif($guidanceMl === false)
            {{ $guidance['title_en'] }}
        @else
            {{ $guidance['title_en'] }} · <span lang="ml">{{ $guidance['title_ml'] }}</span>
        @endif
    </span>
    @foreach($guidance['en'] as $i => $line)
        @continue($guidanceMl === true)
        <span style="display:block;color:#33475b;margin:2px 0">{{ $line }}</span>
    @endforeach
    @if($guidanceMl !== false)
        @foreach($guidance['ml'] as $line)
            <span lang="ml" style="display:block;color:#33475b;margin:2px 0">{{ $line }}</span>
        @endforeach
    @endif
</div>
