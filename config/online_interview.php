<?php

return [

    'sections' => [
        'about'      => 'About You',
        'journey'    => 'Your Journey',
        'contrib'    => 'Your Contribution',
        'experience' => 'Experiences & Challenges',
        'recog'      => 'Responsibilities & Recognition',
        'person'     => 'The Person Behind the Public Life',
        'lookback'   => 'Looking Back',
        'closing'    => 'Closing',
    ],

    'section_tiers' => [
        'about'      => ['emerging', 'accomplished', 'distinguished'],
        'journey'    => ['emerging', 'accomplished', 'distinguished'],
        'contrib'    => ['accomplished', 'distinguished'],
        'experience' => ['accomplished', 'distinguished'],
        'recog'      => ['distinguished'],
        'person'     => ['distinguished'],
        'lookback'   => ['emerging', 'accomplished', 'distinguished'],
        'closing'    => ['emerging', 'accomplished', 'distinguished'],
    ],

    'questions' => [
        ['id' => 'q1',  'section' => 'about',      'required' => true,  'label_en' => 'Tell us about yourself.', 'label_ml' => 'നിങ്ങളെക്കുറിച്ച് നമുക്ക് പറയൂ.', 'help_en' => 'A short introduction in your own words.'],
        ['id' => 'q2',  'section' => 'about',      'required' => true,  'label_en' => 'How did you become involved in public life?', 'label_ml' => 'നിങ്ങൾക്ക് പൊതുജീവിതത്തിൽ എങ്ങനെ പ്രവേശിച്ചു?'],

        ['id' => 'q3',  'section' => 'journey',    'required' => true,  'label_en' => 'Tell us about your journey from your early involvement to where you are today.', 'label_ml' => 'ആദ്യ പ്രവര്‍ത്തനം മുതൽ ഇന്നത്തെ സ്ഥാനം വരെ നിങ്ങളുടെ യാത്രയെക്കുറിച്ച് പറയൂ.'],
        ['id' => 'q4',  'section' => 'journey',    'required' => false, 'label_en' => 'Were there any important turning points along the way?', 'label_ml' => 'മാർഗ്ഗത്തിൽ പ്രധാന തിരിവ് മാറ്റങ്ങൾ ഉണ്ടായിരുന്നോ?'],

        ['id' => 'q5',  'section' => 'contrib',    'required' => true,  'label_en' => 'What do you consider your most significant contribution?', 'label_ml' => 'നിങ്ങളുടെ ഏറ്റവും പ്രധാനപ്പെട്ട സംഭാവന ഏതാണെന്ന് നിങ്ങൾ കരുതുന്നു?'],
        ['id' => 'q6',  'section' => 'contrib',    'required' => false, 'label_en' => 'Tell us about another achievement, initiative or contribution that is important to you.', 'label_ml' => 'നിങ്ങൾക്ക് പ്രധാനമായ മറ്റൊരു നേട്ടം / ശ്രമം / സംഭാവനയെക്കുറിച്ച് പറയൂ. (ബാധിക്കുന്നില്ലെങ്കിൽ പ്രദേശം വിടരുത് / Not applicable സാധാരണമാണ്.)'],

        ['id' => 'q7',  'section' => 'experience', 'required' => false, 'label_en' => 'Was there a difficult moment or decision that shaped your journey?', 'label_ml' => 'നിങ്ങളുടെ യാത്രയെ രൂപപ്പെടുത്തിയ ഒരു ബുദ്ധിമുട്ടുള്ള നിമിഷമോ തീരുമാനമോ ഉണ്ടായിരുന്നോ?'],
        ['id' => 'q8',  'section' => 'experience', 'required' => false, 'label_en' => 'Is there a memorable incident or experience you would like to share?', 'label_ml' => 'പങ്കിടാൻ നിങ്ങൾ ആഗ്രഹിക്കുന്ന ഒരു മറക്കാനാവാത്ത സംഭവമോ അനുഭവമോ ഉണ്ടോ?'],

        ['id' => 'q9',  'section' => 'recog',      'required' => false, 'label_en' => 'What important positions, responsibilities or appointments have you held?', 'label_ml' => 'നിങ്ങൾ വഹിച്ച പ്രധാന സ്ഥാനങ്ങൾ, ചുമതലകൾ, നിയമനങ്ങൾ ഏതാണ്?'],
        ['id' => 'q10', 'section' => 'recog',      'required' => false, 'label_en' => 'Have you received any awards, honours or other recognition?', 'label_ml' => 'നിങ്ങൾക്ക് ഏതെങ്കിലും പുരസ്കാരങ്ങൾ, ബഹുമതികൾ അല്ലെങ്കിൽ മറ്റ് അംഗീകാരങ്ങൾ ലഭിച്ചിട്ടുണ്ടോ? (ബാധിക്കുന്നില്ലെങ്കിൽ പ്രദേശം വിടരുത്.)'],

        ['id' => 'q11', 'section' => 'person',     'required' => false, 'label_en' => 'Who or what has influenced you most?', 'label_ml' => 'ആരാണ് അല്ലെങ്കിൽ എന്താണ് നിങ്ങളെ ഏറ്റവും കൂടുതൽ സ്വാധീനിച്ചത്?'],
        ['id' => 'q12', 'section' => 'person',     'required' => false, 'label_en' => 'Is there a lesser-known aspect of your life or work that you would like people to know?', 'label_ml' => 'നിങ്ങളുടെ ജീവിതത്തിലോ ജോലിയിലോ കുറച്ച് ആളുകൾക്ക് മാത്രം അറിയാവുന്ന ഒരു വശം ആളുകൾക്കറിയാൻ നിങ്ങൾ ആഗ്രഹിക്കുന്നുണ്ടോ?'],

        ['id' => 'q13', 'section' => 'lookback',   'required' => true,  'label_en' => 'Looking back, what are you most proud or satisfied about?', 'label_ml' => 'പിന്നോക്കം നോക്കുമ്പോൾ, നിങ്ങൾ ഏതിനെക്കുറിച്ച് ഏറ്റവും അഭിമാനിക്കുന്നു അല്ലെങ്കിൽ തൃപ്തികരമായി കരുതുന്നു?'],
        ['id' => 'q14', 'section' => 'lookback',   'required' => true,  'label_en' => 'What would you most like people to remember about your contribution?', 'label_ml' => 'നിങ്ങളുടെ സംഭാവനയെക്കുറിച്ച് ആളുകൾ ഏതിനെക്കുറിച്ച് നിങ്ങൾ ഓർമ്മിക്കണമെന്ന് നിങ്ങൾ ഏറ്റവും കൂടുതൽ ആഗ്രഹിക്കുന്നു?'],

        ['id' => 'closing_other', 'section' => 'closing', 'required' => false, 'label_en' => 'Anything else you would like to add?', 'label_ml' => 'ചേർക്കാൻ നിങ്ങൾക്ക് മറ്റെന്തെങ്കിലും ഉണ്ടോ?'],
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
        'disk'                   => 'private_uploads',
        'storage_prefix'         => 'source-materials/applications',
        'sanitize_original_name' => true,
    ],

    'submission' => [
        'prevent_duplicate_within_seconds' => 30,
        'valid_source_methods_for_interview' => ['online_interview', 'admin_test_demo'],
        'valid_source_methods_for_direct'    => ['direct_submission', 'admin_test_demo'],
    ],
];
