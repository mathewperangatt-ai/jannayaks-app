@php
    $pair = \App\Support\PhotographReplacementComparison::pair($record);
    $current = $pair['current'];
    $proposed = $pair['proposed'];
@endphp
<div class="jy-photo-replace" style="font-family:'DM Sans',system-ui,sans-serif">
    <style>
        .jy-photo-replace-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        @media (min-width: 768px) {
            .jy-photo-replace-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
    <p style="margin:0 0 12px;font-size:13px;color:#1f2924">
        The proposed photograph is not public. The current photograph stays in place until this replacement is approved.
    </p>
    <div class="jy-photo-replace-grid">
        <div>
            <div style="font:600 11px/1.4 'DM Sans',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#214d68;margin-bottom:6px">Currently public</div>
            <div style="border:1px solid #d3ddd1;border-radius:10px;padding:8px;background:#fbfaf7;text-align:center">
                @if($current)
                    <img src="{{ route('staff.profile-media.preview', ['media' => $current]) }}" alt="{{ $current->alt_text ?: 'Currently public photograph' }}"
                        style="max-width:100%;max-height:340px;border-radius:6px;object-fit:contain">
                @else
                    <div style="min-height:160px;display:flex;align-items:center;justify-content:center;color:#6f8075;font-size:14px;padding:12px">No public photograph yet</div>
                @endif
            </div>
        </div>
        <div>
            <div style="font:600 11px/1.4 'DM Sans',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#7a4a1c;margin-bottom:6px">Proposed replacement — not public</div>
            <div style="border:1px solid #e4d3b8;border-radius:10px;padding:8px;background:#fffaf3;text-align:center">
                @if($proposed)
                    <img src="{{ route('staff.profile-media.preview', ['media' => $proposed]) }}" alt="{{ $proposed->alt_text ?: 'Proposed replacement photograph' }}"
                        style="max-width:100%;max-height:340px;border-radius:6px;object-fit:contain">
                @else
                    <div style="min-height:160px;display:flex;align-items:center;justify-content:center;color:#6f8075;font-size:14px;padding:12px">No proposed replacement</div>
                @endif
            </div>
        </div>
    </div>
</div>
