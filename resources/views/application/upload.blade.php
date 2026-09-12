@extends('layouts.app')

@section('content')
<section class="card stack">
    <div>
        <span class="tag">SOURCE MATERIAL</span>
        <span class="list-pill">Optional · Editorial context only</span>
    </div>
    <h1>Upload source material</h1>
    <p class="sub">
        You can optionally upload written material to help editorial preparation.
        This material is <b>additional context</b> — it does not replace the Online Interview answers.
        Uploads remain private and are never published as-is.
    </p>
    <div class="warnbox" role="note">
        Accepted formats: PDF, DOC/DOCX, RTF, TXT, CSV. Max {{ $maxKb }} KB per file.
        Forbidden file types: executable code, scripts, HTML, SVG (cannot be uploaded).
    </div>

    <form method="POST" action="{{ route('applications.upload.material', $application) }}" enctype="multipart/form-data" novalidate id="uploadForm">
        @csrf
        <input type="hidden" name="honey_bot" value="" maxlength="0" autocomplete="off" tabindex="-1" aria-hidden="true">
        <div class="grid" style="grid-template-columns:1fr;gap:12px">
            <div class="field">
                <label for="material_type">Type of material</label>
                <select id="material_type" name="material_type" required>
                    <option value="" disabled selected>Choose a purpose…</option>
                    @foreach($materialTypes as $k => $label)
                        <option value="{{ $k }}" @selected(old('material_type') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('material_type')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="material">File</label>
                <input id="material" name="material" type="file" accept=".pdf,.doc,.docx,.rtf,.txt,.csv,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/rtf" required>
                <div class="hint">Max {{ $maxKb }} KB. File is stored privately. No public download URLs are created.</div>
                @error('material')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="actions">
            <a class="btn ghost" href="{{ route('applications.show', $application) }}">← Back to application</a>
            <button class="btn primary" type="submit" id="uploadBtn">Upload this file →</button>
        </div>
    </form>
</section>

<section class="card stack">
    <div class="row" style="justify-content:space-between">
        <h2 style="font-size:16px;margin:0">Files uploaded</h2>
        <span class="tag">{{ $materials->count() }} files</span>
    </div>
    @if($materials->isEmpty())
        <p class="sub" style="margin:0">No files uploaded yet.</p>
    @else
        @foreach($materials as $m)
            <div class="mat-row">
                <div class="mat-meta">
                    <b>{{ $m->original_filename }}</b>
                    <span>
                        <span class="list-pill">{{ $typeMap[$m->material_type] ?? $m->material_type }}</span>
                        <span class="bytes">{{ number_format((int)$m->file_bytes) }} bytes</span>
                        <span class="bytes">· uploaded {{ $m->uploaded_at ? $m->uploaded_at->format('d M Y · H:i') : '—' }}</span>
                        @if($m->isPurged())
                            <span class="tag bad" style="margin-left:6px">PURGED</span>
                        @else
                            <span class="tag ok" style="margin-left:6px">Received</span>
                        @endif
                    </span>
                </div>
            </div>
        @endforeach
    @endif
</section>
@endsection

@push('scripts')
<script>
(function(){
    var form = document.getElementById('uploadForm');
    if (!form) return;
    var btn = document.getElementById('uploadBtn');
    form.addEventListener('submit', function(){
        if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
    });
})();
</script>
@endpush
