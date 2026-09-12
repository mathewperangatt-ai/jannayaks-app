@extends('layouts.app')

@section('content')
@php
    $isSubmitted = (bool) $readOnly;
    $sectionLabels = config('online_interview.sections', []);
    $tierMap = ['emerging' => 'Emerging','accomplished' => 'Accomplished','distinguished' => 'Distinguished'];
@endphp

<section class="card {{ $isSubmitted ? 'readonly' : '' }}" aria-label="Online Interview">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag">ONLINE INTERVIEW · {{ strtoupper($tierMap[(string)($package_tier ?? 'emerging')] ?? '') }}</span>
            @if($isSubmitted)
                <span class="tag ok" style="margin-left:6px">SUBMITTED · READ-ONLY</span>
            @endif
        </div>
        <a class="btn ghost" style="padding:8px 12px;min-height:36px;font-size:14px" href="{{ route('applications.show', $application) }}">← Back to dashboard</a>
    </div>
    <h1 style="margin-top:10px">
        {{ $isSubmitted ? 'Your submitted answers' : 'Tell us about your journey' }}
    </h1>
    <p class="sub">
        One answer field per question. Write freely in English, Malayalam, Manglish or any mix — the text you write is the source we keep.
        @if(!$isSubmitted) Press <b>Save and continue</b> frequently; you can close the tab and resume later. @endif
    </p>

    <div class="progress" aria-label="Interview progress">
        <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['total'] ? (int)(($progress['answered']/$progress['total'])*100) : 0 }}">
            <span style="width:{{ $progress['total'] ? (int)(($progress['answered']/$progress['total'])*100) : 0 }}%"></span>
        </div>
        <div class="progress-meta">
            <div>Answered <b>{{ $progress['answered'] }}</b> / {{ $progress['total'] }}</div>
            <div>Required <b>{{ $progress['required_answered'] }}</b> / {{ $progress['required_total'] }}</div>
            @if (! $isSubmitted && ! empty($progress['missing_required']))
                <div style="color:var(--bad);font-weight:600">{{ count($progress['missing_required']) }} required left</div>
            @endif
            @if (! $isSubmitted && empty($progress['missing_required']))
                <div style="color:var(--ok);font-weight:600">Ready to submit</div>
            @endif
        </div>
    </div>
</section>

@if(!$isSubmitted && ! empty($progress['missing_required']))
    <section class="card" style="padding:14px 16px">
        <div class="missbox" role="note" style="margin:0" aria-live="polite">
            Required questions still pending: {{ implode(', ', array_map('strtoupper', $progress['missing_required'])) }}. Complete them to unlock Submit.
        </div>
    </section>
@endif
@if($isSubmitted && $submittedAt)
    <section class="card" style="padding:14px 16px">
        <div class="warnbox" role="status" style="margin:0;background:#eef7f1;border-left-color:#2e8b57;color:#0e4a23">
            Submitted on {{ \Illuminate\Support\Carbon::parse($submittedAt)->format('l, j F Y · H:i') }}. Editorial will prepare a draft and contact you for review before publication. This interview is now read-only.
        </div>
    </section>
@endif

<form id="interview-form" method="POST" action="{{ route('online-interview.save', $application) }}" class="stack" @if($isSubmitted) aria-disabled="true" @endif>
    @csrf
    <input type="hidden" name="_method" value="PATCH">
    <input type="hidden" name="honey_bot" value="" maxlength="0" autocomplete="off" tabindex="-1" aria-hidden="true">

    @foreach($sections as $sectionKey => $sectionQs)
        <details class="stack" @if($loop->first) open @endif>
            <summary>
                PART {{ $loop->iteration }} · {{ $sectionLabels[$sectionKey] ?? ucwords(str_replace('_',' ',$sectionKey)) }}
                <span style="float:right;font-weight:500;color:var(--ink-soft)">
                    {{ count($sectionQs) }} question{{ count($sectionQs) !== 1 ? 's' : '' }}
                </span>
            </summary>
            <div class="card" style="margin-top:12px">
                <div class="stack">
                    @foreach($sectionQs as $q)
                        @php
                            $qid = (string)($q['id'] ?? '');
                            $required = !empty($q['required']);
                            $existing = isset($answers[$qid]) ? (string)$answers[$qid] : '';
                        @endphp
                        <div class="field">
                            <div class="q-meta">
                                <span class="q-sec">{{ $sectionLabels[$sectionKey] ?? $sectionKey }}</span>
                                <span class="q-num">{{ strtoupper($qid) }}</span>
                                @if($required)
                                    <span class="req" aria-label="Required">· Required</span>
                                @else
                                    <span class="list-pill">Optional</span>
                                @endif
                            </div>
                            <div class="bilingual">
                                <label class="en" for="q-{{ $qid }}">{{ $q['label_en'] ?? '' }}</label>
                                <span class="ml">{{ $q['label_ml'] ?? '' }}</span>
                            </div>
                            @if(!empty($q['help_en']))
                                <div class="hint">{{ $q['help_en'] }}</div>
                            @endif
                            <textarea
                                id="q-{{ $qid }}"
                                name="answers[{{ $qid }}]"
                                rows="6"
                                maxlength="20000"
                                data-qid="{{ $qid }}"
                                @if($isSubmitted || !in_array($qid, $allowed_qids, true))
                                    disabled readonly aria-disabled="true"
                                @endif
                            >{{ old('answers.'.$qid, $existing) }}</textarea>
                            <div class="hint bytes" data-count-for="{{ $qid }}">
                                @if($existing !== '') {{ mb_strlen($existing) }} characters saved. @else 0 characters @endif
                                @if(!in_array($qid, $allowed_qids, true))
                                     · <span style="color:var(--bad);font-weight:700">Locked for this tier</span>
                                @endif
                            </div>
                        </div>
                        @if(!$loop->last)<div class="divider"></div>@endif
                    @endforeach
                </div>
            </div>
        </details>
    @endforeach
</form>

<section class="card" style="padding:14px 16px 18px">
    <form method="POST" action="{{ route('online-interview.submit', $application) }}" id="submit-form" onsubmit="return confirm('Submit this Online Interview for editorial processing? Once submitted, answers become read-only and ordinary editing is locked.');">
        @csrf
        <div class="actions">
            <button class="btn ghost" type="submit" form="interview-form" formaction="{{ route('online-interview.save', $application) }}" formmethod="POST" @if($isSubmitted) disabled @endif>
                💾 Save and continue (partial)
            </button>
            <button class="btn primary" type="submit" @if($isSubmitted || !empty($progress['missing_required'])) disabled aria-disabled="true" @endif>
                Submit for editorial processing →
            </button>
        </div>
        <p class="hint" style="margin-top:10px">
            @if(!empty($progress['missing_required']))
                Complete the {{ count($progress['missing_required']) }} remaining required questions to enable Submit.
            @else
                Submission does not publish a profile. Editorial prepares a draft; you review and approve before publication.
            @endif
        </p>
    </form>
</section>

<div id="savedToast" class="saved-toast" role="status" aria-live="polite">Saved</div>
@endsection

@push('scripts')
<script>
(function(){
    var form = document.getElementById('interview-form');
    if (!form) return;
    var toast = document.getElementById('savedToast');
    var counts = document.querySelectorAll('[data-count-for]');
    function updateCounts(){
        counts.forEach(function(el){
            var qid = el.getAttribute('data-count-for');
            var ta = document.getElementById('q-'+qid);
            if (!ta) return;
            ta.addEventListener('input', function(){
                var n = (ta.value || '').length;
                el.textContent = n + ' characters' + ((ta.value || '').trim() === '' ? '.' : ' — not saved yet.');
            });
        });
    }
    updateCounts();

    var submitting = false;
    form.addEventListener('submit', function(e){
        if (submitting) return;
        submitting = true;
        var originalAction = form.getAttribute('action');
        // The save button sets formaction/formmethod, modern browsers populate the <form>'s action/method
        // but we ensure we always POST to save endpoint for the top-level partial form.
        if (originalAction === '{{ route('online-interview.save', $application) }}') {
            // proceed normally
        }
        var data = new FormData(form);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.getAttribute('action') || '{{ route('online-interview.save', $application) }}', true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) return;
            submitting = false;
            var status = xhr.status;
            try { var payload = JSON.parse(xhr.responseText || '{}'); } catch(err) { var payload = {}; }
            if (status >= 200 && status < 300 && payload.ok) {
                var saved = payload.saved ?? 0;
                var prog = payload;
                if (toast) {
                    toast.textContent = 'Saved ' + saved + ' answer' + (saved === 1 ? '' : 's') + ' · ' + (prog.required_answered ?? 0) + '/' + (prog.required_total ?? 0) + ' required.';
                    toast.classList.add('show');
                    setTimeout(function(){ toast.classList.remove('show'); }, 1800);
                }
                // Update per-question "saved" counters to reflect actually persisted length from server? Not available here; set counter suffix to "saved".
                document.querySelectorAll('textarea[data-qid]').forEach(function(ta){
                    var val = ta.value || '';
                    var id = ta.getAttribute('data-qid');
                    var label = document.querySelector('[data-count-for="'+id+'"]');
                    if (label && val.trim() !== '') label.textContent = (val.length) + ' characters · saved.';
                });
                // Update progress bar
                var total = Math.max(1, parseInt(prog.total || '0', 10));
                var answered = parseInt(prog.answered || '0', 10);
                var pct = Math.round((answered/total)*100);
                var bars = document.querySelectorAll('.progress .bar > span');
                bars.forEach(function(b){ b.style.width = pct + '%'; });
                var meta = document.querySelectorAll('.progress-meta > div');
                if (meta[0]) meta[0].innerHTML = 'Answered <b>'+answered+'</b> / '+total;
                if (meta[1]) meta[1].innerHTML = 'Required <b>'+(prog.required_answered || 0)+'</b> / '+(prog.required_total || 0);
                if (meta[2]) meta[2].innerHTML = (prog.missing_required && prog.missing_required.length)
                    ? '<span style="color:var(--bad);font-weight:600">'+prog.missing_required.length+' required left</span>'
                    : '<span style="color:var(--ok);font-weight:600">Ready to submit</span>';
                var submitBtn = document.querySelector('#submit-form .btn.primary');
                if (submitBtn && (!prog.missing_required || prog.missing_required.length === 0)) {
                    submitBtn.disabled = false; submitBtn.removeAttribute('aria-disabled');
                }
            } else if (status === 422) {
                if (toast) { toast.textContent = 'Missing required — scroll up to highlighted.'; toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2200); }
            } else {
                if (toast) { toast.textContent = 'Could not save. Try again in a moment.'; toast.classList.add('show'); setTimeout(function(){toast.classList.remove('show');},2200); }
            }
        };
        xhr.send(data);
        e.preventDefault();
    });
})();
</script>
@endpush
