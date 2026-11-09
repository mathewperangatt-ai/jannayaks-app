{{-- Approved customer notices. $notice: 'intro-full' | 'reminder' | 'preparation'.
     'intro-full' accepts $languages (default both) so a page with a language switch shows the selected language.
     'reminder' requires $application (renders the actual Day-10 submission date). --}}
@if(($notice ?? '') === 'intro-full')
    @php($noticeLanguages = $languages ?? ['en', 'ml'])
    <div class="warnbox" role="note" aria-label="Taking your time and submission period" style="margin:0">
        @if(in_array('en', $noticeLanguages, true))
        <div><b>Take Your Time. Tell Your Story Thoughtfully.</b></div>
        <div style="margin-top:6px">Your Jannayaks profile is a reflection of your life's work, experiences and achievements. We encourage you to take your time, think carefully about your responses and provide the information that best represents you. You can complete the questionnaire at your own pace, saving your progress as you go.</div>
        <div style="margin-top:6px">You have <b>10 calendar days</b> from the day you first start the questionnaire to complete and submit it. If it remains unsubmitted at the end of this period, the incomplete application and its associated working records, including uploaded photographs and documents, will be permanently deleted on the 11th day. Your account will remain active, and you can start a fresh application whenever you wish to proceed.</div>
        @endif
        @if(in_array('ml', $noticeLanguages, true))
        <div lang="ml" style="margin-top:{{ in_array('en', $noticeLanguages, true) ? '10px' : '0' }}"><b>സമയമെടുത്ത്, ചിന്തിച്ച്, നിങ്ങളുടെ ജീവിതകഥ പങ്കുവെക്കൂ.</b></div>
        <div lang="ml" style="margin-top:6px">നിങ്ങളുടെ ജീവിതത്തിലെ പ്രവർത്തനങ്ങളും അനുഭവങ്ങളും നേട്ടങ്ങളും പ്രതിഫലിപ്പിക്കുന്നതാണ് Jannayaks പ്രൊഫൈൽ. അതിനാൽ, തിരക്കുകൂട്ടാതെ ഓരോ ചോദ്യത്തിനും ശ്രദ്ധയോടെ ആലോചിച്ച്, നിങ്ങളെ ഏറ്റവും നന്നായി പ്രതിനിധീകരിക്കുന്ന വിവരങ്ങൾ നൽകണമെന്ന് ഞങ്ങൾ അഭ്യർത്ഥിക്കുന്നു. നിങ്ങളുടെ പുരോഗതി സേവ് ചെയ്തുകൊണ്ട് സൗകര്യപ്രദമായ രീതിയിൽ ചോദ്യാവലി പൂർത്തിയാക്കാം.</div>
        <div lang="ml" style="margin-top:6px">ചോദ്യാവലി ആദ്യമായി ആരംഭിക്കുന്ന ദിവസം മുതൽ പൂർത്തിയാക്കി സമർപ്പിക്കാൻ <b>10 കലണ്ടർ ദിവസങ്ങൾ</b> ലഭിക്കും. ഈ കാലയളവിനുള്ളിൽ സമർപ്പിക്കാത്ത പക്ഷം, പൂർത്തിയാകാത്ത അപേക്ഷയും അതുമായി ബന്ധപ്പെട്ട പ്രവർത്തന രേഖകളും അപ്‌ലോഡ് ചെയ്ത ഫോട്ടോകളും രേഖകളും പതിനൊന്നാം ദിവസം സ്ഥിരമായി ഇല്ലാതാക്കുന്നതാണ്. നിങ്ങളുടെ അക്കൗണ്ട് സജീവമായി തുടരും. പിന്നീട് തുടരാൻ ആഗ്രഹിക്കുന്നുവെങ്കിൽ പുതിയ അപേക്ഷ ആരംഭിക്കാവുന്നതാണ്.</div>
        @endif
    </div>
    <div style="margin-top:10px">@include('partials.interview-notices', ['notice' => 'preparation', 'languages' => $noticeLanguages])</div>
@elseif(($notice ?? '') === 'reminder')
    @php($lastDay = isset($application) ? \App\Support\InterviewSubmissionDeadline::lastSubmissionDay($application) : null)
    <div class="warnbox" role="note" aria-label="Questionnaire submission deadline" style="margin:0">
        @if($lastDay)
            <div>You have <b>10 calendar days</b> from the day you first started this questionnaire — submit by the end of <b>{{ $lastDay->format('l, j F Y') }}</b>.</div>
            <div lang="ml" style="margin-top:4px">ആദ്യമായി ആരംഭിച്ച ദിവസം മുതൽ <b>10 കലണ്ടർ ദിവസങ്ങളുണ്ട്</b> — സമർപ്പിക്കേണ്ട അവസാന ദിവസം <b>{{ $lastDay->format('j F Y') }}</b>.</div>
        @else
            <div>You have <b>10 calendar days</b> from the day you first start this questionnaire to submit it.</div>
            <div lang="ml" style="margin-top:4px">ഈ ചോദ്യാവലി ആരംഭിക്കുന്ന ദിവസം മുതൽ സമർപ്പിക്കാൻ <b>10 കലണ്ടർ ദിവസങ്ങളുണ്ട്</b>.</div>
        @endif
    </div>
@elseif(($notice ?? '') === 'preparation')
    @php($prepLanguages = $languages ?? ['en', 'ml'])
    <div class="warnbox" role="note" aria-label="Profile preparation time" style="margin:0">
        @if(in_array('en', $prepLanguages, true))
        <div><b>What Happens After Submission?</b></div>
        <div style="margin-top:4px">Once you submit the questionnaire, please allow up to <b>72 hours</b> for the Jannayaks team to prepare and review your profile before sharing it with you for your approval.</div>
        @endif
        @if(in_array('ml', $prepLanguages, true))
        <div lang="ml" style="margin-top:{{ in_array('en', $prepLanguages, true) ? '6px' : '0' }}"><b>സമർപ്പിച്ചതിന് ശേഷം എന്ത് സംഭവിക്കും?</b></div>
        <div lang="ml" style="margin-top:4px">ചോദ്യാവലി സമർപ്പിച്ചുകഴിഞ്ഞാൽ, നിങ്ങളുടെ അംഗീകാരത്തിനായി പ്രൊഫൈൽ തയ്യാറാക്കി പരിശോധിച്ച് നൽകുന്നതിന് Jannayaks ടീമിന് <b>72 മണിക്കൂർ വരെ</b> സമയം അനുവദിക്കുക.</div>
        @endif
    </div>
@endif
