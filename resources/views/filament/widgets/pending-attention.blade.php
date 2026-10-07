<x-filament-widgets::widget>
    @php($outstandingTotal = collect($categories)->sum('total'))
    <div style="background:#fff;border:1px solid #d3ddd1;border-radius:12px;padding:20px 22px">
        <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:14px">
            <h2 style="font:600 16px/1.2 'Fraunces',Georgia,serif;color:#214d68;margin:0">Pending attention</h2>
            <span style="font-size:12px;color:#6f8075">{{ $outstandingTotal }} outstanding item{{ $outstandingTotal === 1 ? '' : 's' }}</span>
        </div>

        @if($outstandingTotal === 0)
            <p style="margin:0;font-size:14px;color:#6f8075">Nothing requires staff attention right now.</p>
        @else
            @foreach($categories as $category)
                <div style="margin-bottom:12px">
                    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px">
                        <span style="font:600 11px/1.4 'DM Sans',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#6f8075">
                            {{ $category['label'] }} — {{ $category['total'] }}
                        </span>
                        @if($category['total'] > count($category['items']) && filled($category['viewAllUrl']))
                            <a href="{{ $category['viewAllUrl'] }}" style="font-size:12px;font-weight:600;color:#214d68;text-decoration:none;flex:none">View all →</a>
                        @endif
                    </div>
                    <ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px">
                        @foreach($category['items'] as $item)
                            <li style="display:flex;align-items:center;gap:12px;border:1px solid #d3ddd1;border-radius:10px;padding:12px 14px;background:#fbfaf7">
                                <span style="width:9px;height:9px;border-radius:999px;background:{{ $item['tone'] }};flex:none" aria-hidden="true"></span>
                                <span style="min-width:0;flex:1">
                                    <span style="display:block;font-size:14px;font-weight:600;color:#1f2924">{{ $item['title'] }}</span>
                                    <span style="display:block;font-size:12.5px;color:#6f8075;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $item['subject'] }} · {{ $item['meta'] }}</span>
                                </span>
                                @if(! empty($item['url']))
                                    <a href="{{ $item['url'] }}" style="font-size:13px;font-weight:600;color:#214d68;text-decoration:none;flex:none">Open →</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @endif
    </div>
</x-filament-widgets::widget>
