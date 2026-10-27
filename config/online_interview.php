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
| - One Standard Profile. The same 22-question pool is used for every new
|   living application and for historical living tiers. Internal package_tier
|   / pricing_generation control editorial depth only — never question access.
| - Q1 is the ONLY required question; Q2–Q22 are optional and skippable.
| - Conditional/domain-specific interview modules are OUT OF SCOPE by
|   editorial decision. Do not implement or add modules, triggers, or any
|   new bilingual interview wording.
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
        ['id' => 'q1',  'section' => 'about',      'required' => true,  'label_en' => 'Tell us about yourself. A brief introduction to your current role or main area of work is enough; the following questions will explore the details.', 'label_ml' => 'താങ്കളെക്കുറിച്ച് പറയാമോ? താങ്കളുടെ ഇപ്പോഴത്തെ ചുമതലയെയോ പ്രധാന പ്രവർത്തനമേഖലയെയോ കുറിച്ച് ചുരുക്കമായി പറയാം. തുടർന്നുള്ള ചോദ്യങ്ങളിൽ കൂടുതൽ വിശദാംശങ്ങൾ പങ്കുവെക്കാം.'],
        ['id' => 'q2',  'section' => 'about',      'required' => false, 'label_en' => 'Where did you grow up, and what do you remember most about your childhood or the place where you grew up?', 'label_ml' => 'താങ്കൾ എവിടെയാണ് വളർന്നത്? ബാല്യകാലത്തെയോ താങ്കൾ വളർന്ന സ്ഥലത്തെയോ കുറിച്ച് ഏറ്റവും ഓർമ്മയിൽ നിൽക്കുന്നത് എന്താണ്?'],
        ['id' => 'q3',  'section' => 'about',      'required' => false, 'label_en' => 'Where do you currently live? If your main work or public activity is based elsewhere, please mention that too.', 'label_ml' => 'താങ്കൾ ഇപ്പോൾ എവിടെയാണ് താമസിക്കുന്നത്? താങ്കളുടെ പ്രധാന പ്രവർത്തനമോ പൊതുപ്രവർത്തനമോ മറ്റൊരു സ്ഥലത്താണെങ്കിൽ അതും പറയുക.'],

        // PART 2 — EDUCATION, WORK AND LIFE
        ['id' => 'q4',  'section' => 'education_work_life', 'required' => false, 'label_en' => 'Tell us about your education and early working life. How did you move from education into working life?', 'label_ml' => 'താങ്കളുടെ വിദ്യാഭ്യാസത്തെക്കുറിച്ചും തൊഴിൽജീവിതത്തിന്റെ തുടക്കത്തെക്കുറിച്ചും പറയാമോ? പഠനത്തിൽനിന്ന് തൊഴിൽജീവിതത്തിലേക്ക് താങ്കൾ എങ്ങനെയാണ് കടന്നത്?'],
        ['id' => 'q5',  'section' => 'education_work_life', 'required' => false, 'label_en' => 'Who or what influenced you in the early stages of your life or career?', 'label_ml' => 'ജീവിതത്തിന്റെയോ തൊഴിൽജീവിതത്തിന്റെയോ ആദ്യഘട്ടങ്ങളിൽ താങ്കളെ സ്വാധീനിച്ച വ്യക്തികളോ അനുഭവങ്ങളോ എന്തൊക്കെയായിരുന്നു?'],
        ['id' => 'q6',  'section' => 'education_work_life', 'required' => false, 'label_en' => 'What are your interests outside your main work or public responsibilities? You may mention hobbies or other activities that are important to you.', 'label_ml' => 'പ്രധാന ജോലിക്കോ പൊതുചുമതലകൾക്കോ പുറമെ താങ്കൾക്ക് താൽപര്യമുള്ള കാര്യങ്ങൾ എന്തൊക്കെയാണ്? താങ്കൾക്ക് പ്രാധാന്യമുള്ള വിനോദങ്ങളോ മറ്റു പ്രവർത്തനങ്ങളോ പങ്കുവെക്കാം.'],

        // PART 3 — ENTERING PUBLIC LIFE
        ['id' => 'q7',  'section' => 'entering_public_life', 'required' => false, 'label_en' => 'How did you first become involved in public, community, organisational, social, cultural or civic life?', 'label_ml' => 'പൊതുരംഗത്തോ സാമൂഹിക, സംഘടനാ, സാംസ്കാരിക പ്രവർത്തനങ്ങളിലോ പൗരകാര്യങ്ങളുമായി ബന്ധപ്പെട്ട പ്രവർത്തനങ്ങളിലോ താങ്കൾ ആദ്യമായി എങ്ങനെയാണ് സജീവമായത്?'],
        ['id' => 'q8',  'section' => 'entering_public_life', 'required' => false, 'label_en' => 'What were the main stages, roles or developments that led from your early involvement to what you do today?', 'label_ml' => 'തുടക്കത്തിലെ പങ്കാളിത്തത്തിൽനിന്ന് ഇന്നത്തെ പ്രവർത്തനങ്ങളിലേക്കെത്തുന്നതുവരെ പ്രധാനമായി ഏതെല്ലാം ഘട്ടങ്ങളോ ചുമതലകളോ മാറ്റങ്ങളോ ഉണ്ടായി?'],

        // PART 4 — TURNING POINTS AND EXPERIENCES
        ['id' => 'q9',  'section' => 'turning_points', 'required' => false, 'label_en' => 'Was there a turning point, difficult situation, setback or important decision that changed or shaped the direction of your life or work? Share only what is relevant and what you feel comfortable discussing.', 'label_ml' => 'താങ്കളുടെ ജീവിതത്തിന്റെയോ തൊഴിൽ-പൊതുപ്രവർത്തനത്തിന്റെയോ ദിശ മാറ്റുകയോ രൂപപ്പെടുത്തുകയോ ചെയ്ത ഒരു വഴിത്തിരിവോ പ്രയാസകരമായ സാഹചര്യമോ തിരിച്ചടിയോ നിർണായക തീരുമാനമോ ഉണ്ടായിട്ടുണ്ടോ? പ്രസക്തമെന്ന് തോന്നുന്നതും താങ്കൾക്ക് പങ്കുവെക്കാൻ സൗകര്യമുള്ളതും മാത്രം പറയാം.'],
        ['id' => 'q10', 'section' => 'turning_points', 'required' => false, 'label_en' => 'Can you recall an incident or experience that illustrates your character or approach to work? It need not be a major event or turning point.', 'label_ml' => 'താങ്കളുടെ സ്വഭാവത്തെയോ പ്രവർത്തനരീതിയെയോ വ്യക്തമാക്കുന്ന ഒരു സംഭവമോ അനുഭവമോ ഓർമ്മയിലുണ്ടോ? അത് വലിയൊരു സംഭവമോ വഴിത്തിരിവോ ആയിരിക്കണമെന്നില്ല.'],
        ['id' => 'q11', 'section' => 'turning_points', 'required' => false, 'label_en' => 'What action did you take in response to that situation or decision, and what was the immediate outcome? There is no need to repeat details already covered in your previous answer.', 'label_ml' => 'ആ സാഹചര്യത്തെയോ തീരുമാനത്തെയോ നേരിടാൻ താങ്കൾ എന്ത് നടപടി സ്വീകരിച്ചു? അതിന്റെ തൽഫലം എന്തായിരുന്നു? മുൻ ഉത്തരത്തിൽ പറഞ്ഞ കാര്യങ്ങൾ വീണ്ടും ആവർത്തിക്കേണ്ടതില്ല.'],

        // PART 5 — WORK AND CONTRIBUTION
        ['id' => 'q12', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'What would you describe as your main work or public contribution? What did you do, and what was it intended to achieve?', 'label_ml' => 'താങ്കളുടെ പ്രധാന പ്രവർത്തനമോ പൊതുസംഭാവനയോ എന്താണെന്ന് വിശദീകരിക്കാമോ? താങ്കൾ എന്താണ് ചെയ്തത്, അതിലൂടെ എന്താണ് കൈവരിക്കാൻ ലക്ഷ്യമിട്ടത്?'],
        ['id' => 'q13', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'Apart from your main contribution, is there another distinct achievement, initiative or contribution that you feel is important to mention?', 'label_ml' => 'പ്രധാന സംഭാവനയ്ക്ക് പുറമെ പ്രത്യേകം എടുത്തുപറയേണ്ട മറ്റൊരു നേട്ടമോ സംരംഭമോ സംഭാവനയോ ഉണ്ടോ?'],
        ['id' => 'q14', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'What important positions, responsibilities or appointments have you held?', 'label_ml' => 'താങ്കൾ വഹിച്ച പ്രധാന സ്ഥാനങ്ങൾ, ചുമതലകൾ, നിയമനങ്ങൾ എന്നിവ ഏതൊക്കെയാണ്?'],
        ['id' => 'q15', 'section' => 'work_contribution', 'required' => false, 'label_en' => 'What awards, honours or other recognition have you received? Where possible, include the name of the awarding organisation and the year.', 'label_ml' => 'താങ്കൾക്ക് ലഭിച്ച പുരസ്കാരങ്ങൾ, ബഹുമതികൾ, മറ്റ് അംഗീകാരങ്ങൾ എന്നിവ ഏതൊക്കെയാണ്? സാധ്യമെങ്കിൽ അംഗീകാരം നൽകിയ സ്ഥാപനത്തിന്റെ പേരും വർഷവും ചേർക്കുക.'],

        // PART 6 — PEOPLE AND PERSONALITY
        ['id' => 'q16', 'section' => 'people_personality', 'required' => false, 'label_en' => 'What is it like to work with or lead other people? If possible, describe your approach with an example.', 'label_ml' => 'മറ്റുള്ളവരോടൊപ്പം പ്രവർത്തിക്കുമ്പോഴും അവരെ നയിക്കുമ്പോഴും താങ്കളുടെ സമീപനം എങ്ങനെയാണ്? കഴിയുമെങ്കിൽ ഒരു ഉദാഹരണവും പങ്കുവെക്കാം.'],
        ['id' => 'q17', 'section' => 'people_personality', 'required' => false, 'label_en' => 'Is there an aspect of your working style, approach or personality that readers might not discover from your positions and achievements?', 'label_ml' => 'താങ്കളുടെ സ്ഥാനങ്ങളെയും നേട്ടങ്ങളെയും മാത്രം നോക്കിയാൽ അറിയാനാകാത്ത, താങ്കളുടെ പ്രവർത്തനശൈലിയിലോ സമീപനത്തിലോ വ്യക്തിത്വത്തിലോ ഉള്ള ഒരു പ്രത്യേക വശം പങ്കുവെക്കാനുണ്ടോ?'],

        // PART 7 — REFLECTION
        ['id' => 'q18', 'section' => 'reflection', 'required' => false, 'label_en' => 'Of the work or experiences you have described, what are you most proud or satisfied about, and why does it matter to you?', 'label_ml' => 'ഇതുവരെ പറഞ്ഞ പ്രവർത്തനങ്ങളിലോ അനുഭവങ്ങളിലോ താങ്കൾക്ക് ഏറ്റവും അഭിമാനമോ സംതൃപ്തിയോ നൽകുന്നത് ഏതാണ്? അത് താങ്കൾക്ക് പ്രധാനമാകുന്നത് എന്തുകൊണ്ടാണ്?'],
        ['id' => 'q19', 'section' => 'reflection', 'required' => false, 'label_en' => 'How have your experiences changed the way you think or work?', 'label_ml' => 'താങ്കളുടെ അനുഭവങ്ങൾ താങ്കളുടെ ചിന്താഗതിയെയോ പ്രവർത്തനരീതിയെയോ എങ്ങനെയാണ് മാറ്റിയിട്ടുള്ളത്?'],
        ['id' => 'q20', 'section' => 'reflection', 'required' => false, 'label_en' => 'What would you like to see happen next in your field, organisation, community or work?', 'label_ml' => 'താങ്കളുടെ പ്രവർത്തനമേഖലയിലോ സംഘടനയിലോ സമൂഹത്തിലോ ഇനി എന്ത് മാറ്റമോ പുരോഗതിയോ കാണാനാണ് താങ്കൾ ആഗ്രഹിക്കുന്നത്?'],
        ['id' => 'q21', 'section' => 'reflection', 'required' => false, 'label_en' => 'What would you like people to remember about you or your work? What would you hope they take away from your work or the example you have set?', 'label_ml' => 'താങ്കളെയോ താങ്കളുടെ പ്രവർത്തനങ്ങളെയോ കുറിച്ച് ആളുകൾ എന്താണ് ഓർക്കണമെന്ന് താങ്കൾ ആഗ്രഹിക്കുന്നത്? താങ്കളുടെ പ്രവർത്തനങ്ങളിൽനിന്നോ താങ്കൾ നൽകിയ മാതൃകയിൽനിന്നോ ആളുകൾ എന്താണ് ഉൾക്കൊള്ളണമെന്ന് പ്രതീക്ഷിക്കുന്നു?'],

        // PART 8 — IMPORTANT FACTS
        ['id' => 'q22', 'section' => 'important_facts', 'required' => false, 'label_en' => 'Is there anything else you would like to add? You may include important details not already covered that would help us understand your story correctly, such as dates, positions, organisations, locations, awards, major initiatives or professional, political or institutional responsibilities.', 'label_ml' => 'താങ്കൾക്ക് മറ്റെന്തെങ്കിലും വിവരങ്ങൾ കൂടി ചേർക്കാനുണ്ടോ? താങ്കളുടെ ജീവിതവും പ്രവർത്തനവും ശരിയായി മനസ്സിലാക്കാൻ സഹായിക്കുന്ന, ഇതുവരെ പറഞ്ഞിട്ടില്ലാത്ത പ്രധാന വിവരങ്ങൾ ഇവിടെ ചേർക്കാം. ഉദാഹരണത്തിന്, തീയതികൾ, സ്ഥാനങ്ങൾ, സംഘടനകൾ, സ്ഥലങ്ങൾ, പുരസ്കാരങ്ങൾ, പ്രധാന സംരംഭങ്ങൾ, തൊഴിൽപരമായ വിവരങ്ങൾ, രാഷ്ട്രീയമോ സ്ഥാപനപരമോ ആയ ചുമതലകൾ എന്നിവ.'],
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
