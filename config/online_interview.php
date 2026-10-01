<?php

/*
|--------------------------------------------------------------------------
| Online Interview — approved bilingual question catalog
|--------------------------------------------------------------------------
|
| The 22 question pairs below are the APPROVED production wording
| (English + Malayalam) supplied by the human editor. Do not translate,
| paraphrase, rewrite or "naturalise" this wording; textual changes
| require an explicit editorial approval.
|
| Architecture (Master Editorial Specification §17–§19):
| - Same comprehensive source pool for ALL living tiers — tier controls
|   editorial depth, never access to questions.
| - Q1 is the ONLY required question; Q2–Q22 are optional and skippable.
| - Conditional/domain-specific interview modules are OUT OF SCOPE by
|   editorial decision: the 22-question Online Interview is the complete
|   interview for all living tiers. Do not implement or add modules,
|   triggers, or any new bilingual interview wording.
|
| Q3 implementation note (approved): collect only the town/area of
| current residence — never request a full residential address.
|
*/

return [

    'sections' => [
        'about'               => 'About You',
        'education_work_life' => 'Education, Work and Life',
        'entering_public_life' => 'Entering Public Life',
        'turning_points'      => 'Turning Points and Experiences',
        'work_contribution'   => 'Work and Contribution',
        'people_personality'  => 'People and Personality',
        'reflection'          => 'Reflection',
        'important_facts'     => 'Important Facts',
    ],

    'questions' => [
        // PART 1 — ABOUT YOU
        ['id' => 'q1',  'section' => 'about',      'required' => true,  'label_en' => 'Tell us about yourself.', 'label_ml' => 'നിങ്ങളെക്കുറിച്ച് പറയാമോ?'],
        ['id' => 'q2',  'section' => 'about',      'required' => false, 'label_en' => 'Where did you grow up, and what do you remember about your early years?', 'label_ml' => 'എവിടെയാണ് വളർന്നത്? കുട്ടിക്കാലത്തെക്കുറിച്ച് ഓർക്കുന്ന പ്രധാന കാര്യങ്ങൾ എന്തൊക്കെയാണ്?'],
        ['id' => 'q3',  'section' => 'about',      'required' => false, 'label_en' => 'Where do you currently live? If your main work or public activity is in another town or area, please mention that as well.', 'label_ml' => 'ഇപ്പോൾ നിങ്ങൾ എവിടെയാണ് താമസിക്കുന്നത്? നിങ്ങളുടെ പ്രധാന ജോലി അല്ലെങ്കിൽ പൊതുപ്രവർത്തനം മറ്റൊരു പട്ടണത്തിലോ പ്രദേശത്തോ ആണെങ്കിൽ അതും പറയാമോ?'],

        // PART 2 — EDUCATION, WORK AND LIFE
        ['id' => 'q4',  'section' => 'education_work_life', 'required' => false, 'label_en' => 'Tell us about your education and the early part of your working life.', 'label_ml' => 'നിങ്ങളുടെ വിദ്യാഭ്യാസത്തെയും ജോലി ജീവിതത്തിന്റെ ആദ്യഘട്ടത്തെയും കുറിച്ച് പറയാമോ?'],
        ['id' => 'q5',  'section' => 'education_work_life', 'required' => false, 'label_en' => 'Was there anything or anyone that influenced the way you think or the person you became?', 'label_ml' => 'നിങ്ങളുടെ ചിന്തകളെയും വ്യക്തിത്വത്തെയും സ്വാധീനിച്ച വ്യക്തികളോ അനുഭവങ്ങളോ കാര്യങ്ങളോ ഉണ്ടായിരുന്നോ?'],
        ['id' => 'q6',  'section' => 'education_work_life', 'required' => false, 'label_en' => 'What interests you outside your main work or public responsibilities?', 'label_ml' => 'നിങ്ങളുടെ പ്രധാന ജോലിക്കും പൊതുപ്രവർത്തനങ്ങൾക്കും പുറത്ത് നിങ്ങൾക്ക് താൽപര്യമുള്ള കാര്യങ്ങൾ എന്തൊക്കെയാണ്?'],

        // PART 3 — ENTERING PUBLIC LIFE
        ['id' => 'q7',  'section' => 'entering_public_life', 'required' => false, 'label_en' => 'How did you first become involved in public, community, organisational or social life?', 'label_ml' => 'പൊതുജീവിതത്തിലോ സാമൂഹിക, സംഘടനാ, സമൂഹ പ്രവർത്തനങ്ങളിലോ നിങ്ങൾ ആദ്യമായി എങ്ങനെ എത്തിപ്പെട്ടു?'],
        ['id' => 'q8',  'section' => 'entering_public_life', 'required' => false, 'label_en' => 'How did that involvement develop into what you do today?', 'label_ml' => 'ആ ആദ്യ ഇടപെടൽ പിന്നീട് നിങ്ങൾ ഇന്ന് ചെയ്യുന്ന പ്രവർത്തനങ്ങളിലേക്ക് എങ്ങനെ വളർന്നു?'],

        // PART 4 — TURNING POINTS AND EXPERIENCES
        ['id' => 'q9',  'section' => 'turning_points', 'required' => false, 'label_en' => 'Were there any important turning points in your journey?', 'label_ml' => 'നിങ്ങളുടെ ജീവിതയാത്രയിൽ പ്രധാനപ്പെട്ട വഴിത്തിരിവുകൾ ഉണ്ടായിരുന്നോ?'],
        ['id' => 'q10', 'section' => 'turning_points', 'required' => false, 'label_en' => 'Is there a particular incident or experience that you remember especially clearly?', 'label_ml' => 'പ്രത്യേകമായി ഓർമ്മയിൽ നിൽക്കുന്ന ഒരു സംഭവം അല്ലെങ്കിൽ അനുഭവം പങ്കുവെക്കാമോ?'],
        ['id' => 'q11', 'section' => 'turning_points', 'required' => false, 'label_en' => 'Was there a difficult situation, setback or important decision that affected your journey?', 'label_ml' => 'നിങ്ങളുടെ യാത്രയെ സ്വാധീനിച്ച ഒരു ബുദ്ധിമുട്ടുള്ള സാഹചര്യമോ തിരിച്ചടിയോ പ്രധാന തീരുമാനമോ ഉണ്ടായിരുന്നോ?'],

        // PART 5 — WORK AND CONTRIBUTION
        ['id' => 'q12', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'What part of your work or public contribution is most closely associated with you?', 'label_ml' => 'നിങ്ങളുടെ പ്രവർത്തനങ്ങളിലോ പൊതുജീവിതത്തിലോ നിങ്ങളുമായി ഏറ്റവും അടുത്ത് ബന്ധിപ്പിക്കപ്പെടുന്ന സംഭാവന ഏതാണ്?'],
        ['id' => 'q13', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'Is there another achievement, initiative or contribution that is important to you?', 'label_ml' => 'നിങ്ങൾക്ക് പ്രധാനപ്പെട്ട മറ്റൊരു നേട്ടമോ സംരംഭമോ സംഭാവനയോ ഉണ്ടോ?'],
        ['id' => 'q14', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'What important positions, responsibilities or appointments have you held?', 'label_ml' => 'നിങ്ങൾ വഹിച്ച പ്രധാനപ്പെട്ട സ്ഥാനങ്ങളും ഉത്തരവാദിത്തങ്ങളും നിയമനങ്ങളും ഏതൊക്കെയാണ്?'],
        ['id' => 'q15', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'Have you received any awards, honours or other recognition?', 'label_ml' => 'പുരസ്കാരങ്ങളോ ബഹുമതികളോ മറ്റ് അംഗീകാരങ്ങളോ ലഭിച്ചിട്ടുണ്ടോ?'],

        // PART 6 — PEOPLE AND PERSONALITY
        ['id' => 'q16', 'section' => 'people_personality', 'required' => false, 'label_en' => 'What is it like to work with or lead other people?', 'label_ml' => 'മറ്റുള്ളവരോടൊപ്പം പ്രവർത്തിക്കുമ്പോഴോ അവരെ നയിക്കുമ്പോഴോ നിങ്ങളുടെ സമീപനം എങ്ങനെയാണ്?'],
        ['id' => 'q17', 'section' => 'people_personality', 'required' => false, 'label_en' => 'Is there something about you that people who know you well might notice, but others may not?', 'label_ml' => 'നിങ്ങളെ അടുത്തറിയുന്നവർ ശ്രദ്ധിക്കുന്ന, എന്നാൽ മറ്റുള്ളവർക്ക് അറിയാത്ത നിങ്ങളുടെ ഒരു പ്രത്യേക വശമുണ്ടോ?'],

        // PART 7 — REFLECTION
        ['id' => 'q18', 'section' => 'reflection', 'required' => false, 'label_en' => 'Looking back, what are you most proud or satisfied about?', 'label_ml' => 'തിരിഞ്ഞുനോക്കുമ്പോൾ നിങ്ങൾക്ക് ഏറ്റവും അഭിമാനമോ സംതൃപ്തിയോ നൽകുന്ന കാര്യം എന്താണ്?'],
        ['id' => 'q19', 'section' => 'reflection', 'required' => false, 'label_en' => 'How have your experiences changed the way you think or work?', 'label_ml' => 'നിങ്ങളുടെ അനുഭവങ്ങൾ നിങ്ങളുടെ ചിന്തയെയോ പ്രവർത്തനരീതിയെയോ എങ്ങനെ മാറ്റി?'],
        ['id' => 'q20', 'section' => 'reflection', 'required' => false, 'label_en' => 'What would you like to see happen next?', 'label_ml' => 'ഇനി എന്താണ് സംഭവിക്കണമെന്ന് നിങ്ങൾ ആഗ്രഹിക്കുന്നത്?'],
        ['id' => 'q21', 'section' => 'reflection', 'required' => false, 'label_en' => 'What would you most like people to remember about you or your contribution?', 'label_ml' => 'നിങ്ങളെയോ നിങ്ങളുടെ സംഭാവനകളെയോ കുറിച്ച് ആളുകൾ എന്താണ് ഓർക്കണമെന്ന് നിങ്ങൾ ആഗ്രഹിക്കുന്നത്?'],

        // PART 8 — IMPORTANT FACTS
        ['id' => 'q22', 'section' => 'important_facts', 'required' => false, 'label_en' => 'Is there anything important we should know to understand your story correctly?', 'label_ml' => 'നിങ്ങളുടെ കഥ ശരിയായി മനസ്സിലാക്കാൻ ഞങ്ങൾ അറിയേണ്ട മറ്റേതെങ്കിലും പ്രധാനപ്പെട്ട വിവരമുണ്ടോ?'],
    ],

    'source_material_types' => [
        'biography'      => 'Biography',
        'article'        => 'Article',
        'profile'        => 'Profile',
        'resume'         => 'Résumé / CV',
        'personal_notes' => 'Personal notes',
        'other'          => 'Other',
    ],

    'uploads' => [
        'max_upload_kb'          => 10240,
        'allowed_mime_types'     => [
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/rtf',
        ],
        'forbidden_extensions'   => ['php', 'phar', 'phtml', 'exe', 'bat', 'cmd', 'ps1', 'sh', 'js', 'html', 'svg', 'vbs'],
        // "source_materials" (private R2) is durable on Railway; "private_uploads" (local) is the legacy default.
        'disk'                   => env('SOURCE_MATERIALS_DISK', 'private_uploads'),
        'storage_prefix'         => 'source-materials/applications',
        'sanitize_original_name' => true,
    ],

    'submission' => [
        'prevent_duplicate_within_seconds' => 30,
        'valid_source_methods_for_interview' => ['online_interview', 'admin_test_demo'],
        'valid_source_methods_for_direct'    => ['direct_submission', 'admin_test_demo'],
    ],
];
