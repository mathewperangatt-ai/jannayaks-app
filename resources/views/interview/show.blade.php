@extends('layouts.app')

@section('content')
@php
    $isSubmitted = (bool) $readOnly;
    $sectionLabels = config('online_interview.sections', []);
@endphp

<section class="card {{ $isSubmitted ? 'readonly' : '' }}" aria-label="Online Interview">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag">ONLINE INTERVIEW</span>
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
                                @if($isSubmitted)
                                    disabled readonly aria-disabled="true"
                                @endif
                            >{{ old('answers.'.$qid, $existing) }}</textarea>
                            <div class="hint bytes" data-count-for="{{ $qid }}">
                                @if($existing !== '') {{ mb_strlen($existing) }} characters saved. @else 0 characters @endif
                            </div>
                        </div>
                        @if(!$loop->last)<div class="divider"></div>@endif
                    @endforeach
                </div>
            </div>
        </details>
    @endforeach
</form>

@if(! $isSubmitted)
<section class="card" style="padding:16px 18px">
    <h2 style="margin:0 0 8px;font-size:1.15rem">Help us tell your story well</h2>
    <p class="sub" style="margin:0">The more relevant detail you share, the more material our editorial team has to prepare a substantial, distinctive profile that reflects your life and contributions. Please answer as fully as you comfortably can. If a question does not apply to you, or you prefer not to answer it, you may leave it blank.</p>
    <p class="sub" style="margin:10px 0 0">Your answers will be used to prepare your editorial draft. You will have a separate opportunity to review and approve your profile before publication.</p>
    <h2 lang="ml" style="margin:16px 0 8px;font-size:1.15rem">നിങ്ങളുടെ കഥ നന്നായി പറയാൻ ഞങ്ങളെ സഹായിക്കുക</h2>
    <p class="sub" lang="ml" style="margin:0">നിങ്ങൾ പങ്കുവെക്കുന്ന പ്രസക്തമായ വിവരങ്ങൾ കൂടുന്തോറും, നിങ്ങളുടെ ജീവിതവും സംഭാവനകളും പ്രതിഫലിപ്പിക്കുന്ന സമഗ്രവും വ്യത്യസ്തവുമായ ഒരു പ്രൊഫൈൽ തയ്യാറാക്കാൻ ഞങ്ങളുടെ എഡിറ്റോറിയൽ സംഘത്തിന് കൂടുതൽ വസ്തുത ലഭിക്കും. നിങ്ങൾക്ക് സൗകര്യമായിടത്തോളം പൂർണമായി ഉത്തരം നൽകുക. ഒരു ചോദ്യം നിങ്ങൾക്ക് ബാധകമല്ലെങ്കിലോ ഉത്തരം നൽകാൻ ആഗ്രഹമില്ലെങ്കിലോ അത് ശൂന്യമായി വിടാം.</p>
    <p class="sub" lang="ml" style="margin:10px 0 0">നിങ്ങളുടെ ഉത്തരങ്ങൾ എഡിറ്റോറിയൽ ഡ്രാഫ്റ്റ് തയ്യാറാക്കാൻ ഉപയോഗിക്കും. പ്രസിദ്ധീകരണത്തിന് മുമ്പ് പ്രൊഫൈൽ പരിശോധിച്ച് അംഗീകരിക്കാൻ നിങ്ങൾക്ക് പ്രത്യേക അവസരം ലഭിക്കും.</p>
</section>
@endif

<section class="card" style="padding:14px 16px 18px">
    <form method="POST" action="{{ route('online-interview.submit', $application) }}" id="submit-form" onsubmit="return window.jannayaksSubmitInterview(this);">
        @csrf
        @if(! $isSubmitted)
            <label class="rc-terms" style="display:flex;gap:10px;align-items:flex-start;margin:0 0 14px;line-height:1.55">
                <input type="checkbox" id="questionnaireConfirmed" name="questionnaire_confirmed" value="1" @checked(old('questionnaire_confirmed'))>
                <span>
                    I have reviewed my answers and am ready to submit my questionnaire for editorial preparation.
                    <span lang="ml" style="display:block;margin-top:4px">ഞാൻ എന്റെ ഉത്തരങ്ങൾ പരിശോധിച്ചു. എഡിറ്റോറിയൽ തയ്യാറെടുപ്പിനായി ചോദ്യാവലി സമർപ്പിക്കാൻ ഞാൻ തയ്യാറാണ്.</span>
                </span>
            </label>
            @error('questionnaire_confirmed')<div class="hint" style="color:#7a1414;margin:-6px 0 12px">{{ $message }}</div>@enderror
        @endif
        <div class="actions">
            <button class="btn ghost" type="submit" form="interview-form" formaction="{{ route('online-interview.save', $application) }}" formmethod="POST" @if($isSubmitted) disabled @endif>
                💾 Save and continue (partial)
            </button>
            <button class="btn primary" type="submit" id="submitQuestionnaire" @if($isSubmitted) disabled aria-disabled="true" data-submitted="1" @else disabled aria-disabled="true" data-missing="{{ empty($progress['missing_required']) ? '0' : '1' }}" @endif>
                Submit Questionnaire →
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

    function syncQuestionnaireSubmit() {
        var button = document.getElementById('submitQuestionnaire');
        var box = document.getElementById('questionnaireConfirmed');
        if (!button || button.getAttribute('data-submitted') === '1') return;
        var missing = button.getAttribute('data-missing') === '1';
        var ready = !missing && box && box.checked;
        button.disabled = !ready;
        if (ready) button.removeAttribute('aria-disabled');
        else button.setAttribute('aria-disabled', 'true');
    }
    var confirmBox = document.getElementById('questionnaireConfirmed');
    if (confirmBox) confirmBox.addEventListener('change', syncQuestionnaireSubmit);
    syncQuestionnaireSubmit();

    window.jannayaksSubmitInterview = function (submitForm) {
        var box = document.getElementById('questionnaireConfirmed');
        if (!box || !box.checked) {
            return false;
        }
        if (!confirm('Submit this questionnaire for editorial preparation? Once submitted, answers become read-only. This does not approve or publish the finished profile.')) {
            return false;
        }
        var interview = document.getElementById('interview-form');
        if (!interview) {
            return true;
        }
        var data = new FormData(interview);
        var saveRequest = new XMLHttpRequest();
        saveRequest.open('POST', interview.getAttribute('action') || '{{ route('online-interview.save', $application) }}', true);
        saveRequest.setRequestHeader('Accept', 'application/json');
        saveRequest.onreadystatechange = function () {
            if (saveRequest.readyState !== 4) return;
            if (saveRequest.status < 200 || saveRequest.status >= 300) {
                if (toast) {
                    toast.textContent = 'Could not save your latest answers. They are still on this page — try again.';
                    toast.classList.add('show');
                }
                return;
            }
            submitForm.querySelectorAll('input[data-copied-answer]').forEach(function (el) { el.remove(); });
            interview.querySelectorAll('textarea[name^="answers["]').forEach(function (ta) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = ta.name;
                input.value = ta.value;
                input.setAttribute('data-copied-answer', '1');
                submitForm.appendChild(input);
            });
            HTMLFormElement.prototype.submit.call(submitForm);
        };
        saveRequest.send(data);
        return false;
    };

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
                var submitBtn = document.getElementById('submitQuestionnaire');
                if (submitBtn) {
                    submitBtn.setAttribute('data-missing', (prog.missing_required && prog.missing_required.length) ? '1' : '0');
                    syncQuestionnaireSubmit();
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
