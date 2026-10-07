@php
    $service = app(\App\Services\PhotoEnhancementService::class);
    $run = $run instanceof \App\Models\PhotoEnhancementRun ? $run : $service->latestRunFor($source);
    $candidate = $run?->candidate;
    $ready = $run !== null
        && $run->status === \App\Models\PhotoEnhancementRun::STATUS_COMPLETED
        && $candidate !== null;
@endphp
<div style="font-family:'DM Sans',system-ui,sans-serif">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
            <div style="font:600 11px/1.4 'DM Sans',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#6f8075;margin-bottom:6px">Customer source</div>
            <div style="border:1px solid #d3ddd1;border-radius:10px;padding:8px;background:#fbfaf7;text-align:center">
                <img src="{{ route('staff.profile-media.preview', ['media' => $source]) }}" alt="Customer source photograph"
                    style="max-width:100%;max-height:340px;border-radius:6px;object-fit:contain">
            </div>
            <p style="font-size:12px;color:#6f8075;margin:6px 0 0">The optimized photograph the customer uploaded. This always remains available.</p>
        </div>
        <div>
            <div style="font:600 11px/1.4 'DM Sans',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#7a4a1c;margin-bottom:6px">AI enhanced candidate</div>
            <div style="border:1px solid #e4d3b8;border-radius:10px;padding:8px;background:#fffaf3;text-align:center">
                @if($ready)
                    <img src="{{ route('staff.profile-media.preview', ['media' => $candidate]) }}" alt="AI enhanced candidate photograph"
                        style="max-width:100%;max-height:340px;border-radius:6px;object-fit:contain">
                @else
                    <div style="min-height:200px;display:flex;align-items:center;justify-content:center;color:#6f8075;font-size:14px">
                        {{ $run?->statusLabel() ?? 'No enhancement run' }}
                    </div>
                @endif
            </div>
            <p style="font-size:12px;color:#6f8075;margin:6px 0 0">
                @if($ready)
                    AI-assisted suggestion — private and pending review. Not automatically better: you decide.
                @elseif($run?->status === \App\Models\PhotoEnhancementRun::STATUS_FAILED)
                    Enhancement failed. The original customer photograph remains available. {{ $run->retry_count }} retry/retries used.
                @else
                    Provenance: {{ $run?->provider }} · {{ $run?->model }}.
                @endif
            </p>
        </div>
    </div>

    <div style="margin-top:14px;padding:10px 14px;border:1px solid #d3ddd1;border-radius:8px;background:#f7faf5;font-size:13px;color:#1f2924">
        <b>Status:</b> {{ $run?->statusLabel() ?? 'No enhancement run' }}
        @if($run)
            · <b>Provider:</b> {{ $run->provider }} · {{ $run->model }}
            @if($run->error_message)
                · <b>Error:</b> {{ \Illuminate\Support\Str::limit($run->error_message, 200) }}
            @endif
        @endif
    </div>

    <p style="font-size:12px;color:#82907f;margin:10px 0 0">
        The deciding actions (Accept Enhanced / Keep Original / Regenerate) are on the source row of the table. For real people: enhance the photograph, never reinvent the person.
    </p>
</div>
