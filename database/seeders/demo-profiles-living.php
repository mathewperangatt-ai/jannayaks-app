<?php

/*
| Demonstration-profile content — full-length living demos for the Standard
| Profile presentation (corrected demonstration corpus).
|
| 7 living demonstrations: the Distinguished-depth profiles that remain as
| the public Standard Profile examples. Recognised and Acclaimed short-
| profile demonstrations were retired from the public demo set.
| All are fictional demonstration personas.
|
| Each entry: name, tier (internal key), profession (the corpus
| "Role / theme" line), EN + ML title/summary/body, and the approved
| portrait filename in database/seeders/demo-media/. The summary is the
| first paragraph of the supplied narrative; the body is the complete
| supplied narrative (headings, metadata lines and the closing
| demonstration footer are omitted — the page shows its own notice).
|
| The three revised Distinguished entries (t.gopalakrishnan, p.sreedharan,
| v.suresh.babu) are editorial revisions of their corpus text, not
| verbatim copies. They use only facts from each person's corpus file;
| T. Gopalakrishnan also draws on the recovered text for the same
| identity in the earlier package (SNCDC's founding purpose, the
| membership/volunteer-resource balance, the age remark on succession).
|
| The four young Distinguished entries (arjun.menon, nanditha.das,
| fahim.yousuf, meera.krishnan) come from
| Jannayaks_4_Young_Distinguished_Demo_Upload_Package.zip with typographic
| normalisation only (curly apostrophes, real paragraph breaks), plus two
| Malayalam corrections: Fahim's name (the package spelled it "ഫഹദ്") and
| "കടലാസിൽ" for the non-Malayalam "കാഗിതത്തിൽ". Their portraits are cropped
| to remove a baked-in tier label; the watermark is untouched.
*/

return [
    't.gopalakrishnan' => [
        'name' => 'T. Gopalakrishnan',
        'tier' => 'distinguished',
        'profession' => 'Social Educator and Community Leader',
        'portrait' => 't_gopalakrishnan.jpg',
        'en' => [
            'title' => 'T. Gopalakrishnan',
            'summary' => 'T. Gopalakrishnan first came to the fictional Sree Narayana Community Development Council (SNCDC) as a volunteer at the local level. He would later be elected its president. Between those two points lies the story of an organisation that grew from a small community initiative into a body working across education, social welfare and community development — and of a man who, in recent years, has turned his attention to who will carry that work forward.',
            'body' => 'T. Gopalakrishnan first came to the fictional Sree Narayana Community Development Council (SNCDC) as a volunteer at the local level. He would later be elected its president. Between those two points lies the story of an organisation that grew from a small community initiative into a body working across education, social welfare and community development — and of a man who, in recent years, has turned his attention to who will carry that work forward.

He was born in Alappuzha and studied history at the University of Kerala. His working life began not in community work but in the cooperative sector, where he spent several years in banking. His involvement with community organisations came later, and it began modestly, with volunteer work supporting educational programmes for young people.

The SNCDC had originally been established to support educational and social development programmes among members of the community. Gopalakrishnan’s association with it began at the local level, and with a practical task: helping to organise scholarship assistance for students from economically weaker families.

What began as a volunteer activity gradually became a larger responsibility. He served in turn as branch secretary, district coordinator and state education convenor, each role widening the part of the organisation’s work for which he was answerable, before being elected president of the SNCDC. As president, he oversaw the expansion of existing programmes and the introduction of new initiatives in vocational education, women’s self-help groups and youth development.

Education has remained one of his principal interests, and the scholarship programme offers perhaps the clearest measure of how the work grew. When it began, it assisted fewer than fifty students; it eventually reached several hundred. Gopalakrishnan also supported the creation of a vocational training centre, intended to give practical skills to young people who had not pursued conventional higher education.

Among the initiatives of those years, one he considers particularly meaningful is a women’s micro-enterprise programme. Small groups of women received training and modest financial assistance to establish home-based businesses, and over time the programme developed into a network of women’s self-help groups operating in several districts.

The programme also reflects what he believes community work is for. For Gopalakrishnan, its purpose is not simply to provide assistance. He believes that people who receive support should eventually become participants in creating opportunities for others, and that a community organisation becomes sustainable only when that happens.

The same conviction has shaped the organisation’s youth programmes. Young volunteers are given responsibility for educational camps, cultural programmes and community service activities, while senior office-bearers act increasingly as mentors rather than permanent organisers. The arrangement asks experienced members to step back as much as it asks younger ones to step forward.

Leadership has also called for balance. His approach has sometimes required him to weigh the expectations of a large membership against the practical limitations of an organisation dependent on volunteers and donations. His work has meant dealing, too, with people holding different public viewpoints. He has maintained that the SNCDC’s community programmes should remain accessible to people of different affiliations and backgrounds, while he has himself spoken publicly, from time to time, on issues involving education, social mobility, representation and access to public institutions.

Over the years his responsibilities have included the presidency of the SNCDC, the chairmanship of its Education Committee, the convenorship of its Community Development Programme and membership of the governing board of the organisation’s educational trust. He has also served on advisory committees connected with vocational education and community development.

Away from the organisation, Gopalakrishnan leads a comparatively ordinary family life. His wife, Radha, is a retired teacher, and their two children work in medicine and education. He remains interested in books and classical Malayalam literature, and in conversations with young people entering professional life — an interest that sits naturally beside his work with the organisation’s young volunteers.

At an age when many people begin to reduce their responsibilities, he has instead become increasingly interested in succession. He wants the SNCDC to become less dependent on individual leaders and more capable of producing its next generation of volunteers, educators and community organisers.

He sees a continuity between where he began and where he has arrived. He started by helping a small number of students obtain educational assistance; he later found himself responsible for an institution working across several areas of community life. To him, the two stages belong together. The purpose of an organisation, he believes, is not merely to solve today’s problems, but to leave behind people capable of solving tomorrow’s.',
        ],
        'ml' => [
            'title' => 'T. Gopalakrishnan',
            'summary' => 'പ്രാദേശിക തലത്തിലെ ഒരു സന്നദ്ധപ്രവർത്തകനായാണ് ടി. ഗോപാലകൃഷ്ണൻ സാങ്കൽപ്പിക ശ്രീനാരായണ കമ്മ്യൂണിറ്റി ഡെവലപ്മെന്റ് കൗൺസിലിൽ (SNCDC) എത്തിയത്. പിന്നീട് അദ്ദേഹം അതിന്റെ പ്രസിഡന്റായി തിരഞ്ഞെടുക്കപ്പെട്ടു. ഈ രണ്ടു ഘട്ടങ്ങൾക്കിടയിലുള്ളത്, ചെറിയൊരു സാമൂഹിക സംരംഭമായി തുടങ്ങി വിദ്യാഭ്യാസം, സാമൂഹികക്ഷേമം, സമൂഹവികസനം എന്നീ മേഖലകളിലേക്ക് വളർന്ന ഒരു സംഘടനയുടെ കഥയാണ്; ഒപ്പം, സമീപകാലത്ത് ആ പ്രവർത്തനം ആര് മുന്നോട്ടുകൊണ്ടുപോകും എന്നതിലേക്ക് ശ്രദ്ധ തിരിച്ച ഒരു വ്യക്തിയുടെയും.',
            'body' => 'പ്രാദേശിക തലത്തിലെ ഒരു സന്നദ്ധപ്രവർത്തകനായാണ് ടി. ഗോപാലകൃഷ്ണൻ സാങ്കൽപ്പിക ശ്രീനാരായണ കമ്മ്യൂണിറ്റി ഡെവലപ്മെന്റ് കൗൺസിലിൽ (SNCDC) എത്തിയത്. പിന്നീട് അദ്ദേഹം അതിന്റെ പ്രസിഡന്റായി തിരഞ്ഞെടുക്കപ്പെട്ടു. ഈ രണ്ടു ഘട്ടങ്ങൾക്കിടയിലുള്ളത്, ചെറിയൊരു സാമൂഹിക സംരംഭമായി തുടങ്ങി വിദ്യാഭ്യാസം, സാമൂഹികക്ഷേമം, സമൂഹവികസനം എന്നീ മേഖലകളിലേക്ക് വളർന്ന ഒരു സംഘടനയുടെ കഥയാണ്; ഒപ്പം, സമീപകാലത്ത് ആ പ്രവർത്തനം ആര് മുന്നോട്ടുകൊണ്ടുപോകും എന്നതിലേക്ക് ശ്രദ്ധ തിരിച്ച ഒരു വ്യക്തിയുടെയും.

ആലപ്പുഴയിൽ ജനിച്ച ഗോപാലകൃഷ്ണൻ കേരള സർവകലാശാലയിൽ ചരിത്രം പഠിച്ചു. സമൂഹപ്രവർത്തനത്തിലല്ല, സഹകരണ മേഖലയിലാണ് അദ്ദേഹത്തിന്റെ തൊഴിൽജീവിതം ആരംഭിച്ചത്; ഏതാനും വർഷം ബാങ്കിംഗ് രംഗത്ത് പ്രവർത്തിച്ചു. സമൂഹസംഘടനകളുമായുള്ള ബന്ധം പിന്നീടാണ് ഉണ്ടായത്. അതിന്റെ തുടക്കവും ലളിതമായിരുന്നു — യുവാക്കൾക്കായുള്ള വിദ്യാഭ്യാസ പരിപാടികൾക്ക് പിന്തുണ നൽകുന്ന സന്നദ്ധപ്രവർത്തനത്തിലൂടെ.

സമൂഹാംഗങ്ങൾക്കിടയിൽ വിദ്യാഭ്യാസ-സാമൂഹിക വികസന പരിപാടികൾക്ക് പിന്തുണ നൽകാനാണ് SNCDC ആദ്യം സ്ഥാപിക്കപ്പെട്ടത്. പ്രാദേശിക തലത്തിൽ തുടങ്ങിയ ഗോപാലകൃഷ്ണന്റെ ബന്ധം ഒരു പ്രായോഗിക ചുമതലയോടെയായിരുന്നു: സാമ്പത്തികമായി പിന്നാക്കം നിൽക്കുന്ന കുടുംബങ്ങളിലെ വിദ്യാർത്ഥികൾക്ക് സ്കോളർഷിപ്പ് സഹായം സംഘടിപ്പിക്കുക.

സന്നദ്ധപ്രവർത്തനമായി തുടങ്ങിയത് ക്രമേണ വലിയ ഉത്തരവാദിത്തമായി മാറി. ബ്രാഞ്ച് സെക്രട്ടറി, ജില്ലാ കോഓർഡിനേറ്റർ, സംസ്ഥാന വിദ്യാഭ്യാസ കൺവീനർ എന്നീ ചുമതലകൾ അദ്ദേഹം ഒന്നിനുപിറകെ ഒന്നായി വഹിച്ചു; ഓരോന്നും അദ്ദേഹം ഉത്തരം പറയേണ്ട പ്രവർത്തനമേഖലയുടെ പരിധി വിപുലമാക്കി. തുടർന്നാണ് SNCDCയുടെ പ്രസിഡന്റായി തിരഞ്ഞെടുക്കപ്പെട്ടത്. പ്രസിഡന്റെന്ന നിലയിൽ നിലവിലുള്ള പരിപാടികളുടെ വിപുലീകരണത്തിനും വൊക്കേഷണൽ വിദ്യാഭ്യാസം, വനിതാ സ്വയംസഹായ സംഘങ്ങൾ, യുവജനവികസനം എന്നീ മേഖലകളിലെ പുതിയ സംരംഭങ്ങൾക്കും അദ്ദേഹം മേൽനോട്ടം വഹിച്ചു.

വിദ്യാഭ്യാസം അദ്ദേഹത്തിന്റെ പ്രധാന താൽപര്യങ്ങളിലൊന്നായി തുടർന്നു. പ്രവർത്തനം എത്രത്തോളം വളർന്നു എന്നതിന്റെ ഏറ്റവും വ്യക്തമായ അളവുകോൽ ഒരുപക്ഷേ സ്കോളർഷിപ്പ് പദ്ധതിയാണ്. തുടക്കത്തിൽ അമ്പതിൽ താഴെ വിദ്യാർത്ഥികൾക്കാണ് സഹായം ലഭിച്ചിരുന്നത്; പിന്നീട് അത് നൂറുകണക്കിന് പേരിലേക്ക് എത്തി. പരമ്പരാഗത ഉന്നതവിദ്യാഭ്യാസത്തിലേക്ക് കടക്കാത്ത യുവാക്കൾക്ക് പ്രായോഗിക കഴിവുകൾ നൽകാനായി ഒരു വൊക്കേഷണൽ പരിശീലന കേന്ദ്രം സ്ഥാപിക്കുന്നതിനും ഗോപാലകൃഷ്ണൻ പിന്തുണ നൽകി.

ആ കാലത്തെ സംരംഭങ്ങളിൽ അദ്ദേഹം പ്രത്യേകിച്ച് അർത്ഥവത്തായി കാണുന്ന ഒന്ന് വനിതകൾക്കായുള്ള ചെറുകിട സംരംഭ പദ്ധതിയാണ്. ചെറിയ വനിതാ കൂട്ടായ്മകൾക്ക് പരിശീലനവും പരിമിതമായ സാമ്പത്തിക സഹായവും നൽകി വീട്ടിൽ നിന്നുതന്നെ നടത്താവുന്ന സംരംഭങ്ങൾ തുടങ്ങാൻ അവസരമൊരുക്കി. കാലക്രമേണ ഈ പദ്ധതി വിവിധ ജില്ലകളിലായി പ്രവർത്തിക്കുന്ന വനിതാ സ്വയംസഹായ സംഘങ്ങളുടെ ശൃംഖലയായി വളർന്നു.

സമൂഹപ്രവർത്തനം എന്തിനുവേണ്ടിയാണ് എന്ന അദ്ദേഹത്തിന്റെ കാഴ്ചപ്പാടും ഈ പദ്ധതിയിൽ പ്രതിഫലിക്കുന്നു. സഹായം നൽകുക മാത്രമല്ല അതിന്റെ ലക്ഷ്യമെന്ന് ഗോപാലകൃഷ്ണൻ കരുതുന്നു. സഹായം ലഭിക്കുന്നവർ പിന്നീട് മറ്റുള്ളവർക്ക് അവസരങ്ങൾ സൃഷ്ടിക്കുന്നതിൽ പങ്കാളികളാകണം; അങ്ങനെ സംഭവിക്കുമ്പോൾ മാത്രമാണ് ഒരു സമൂഹസംഘടന സുസ്ഥിരമാകുന്നത് എന്നാണ് അദ്ദേഹത്തിന്റെ വിശ്വാസം.

സംഘടനയുടെ യുവജന പരിപാടികളെയും ഇതേ ബോധ്യമാണ് രൂപപ്പെടുത്തിയത്. വിദ്യാഭ്യാസ ക്യാമ്പുകൾ, സാംസ്കാരിക പരിപാടികൾ, സമൂഹസേവന പ്രവർത്തനങ്ങൾ എന്നിവയുടെ ഉത്തരവാദിത്തം യുവ സന്നദ്ധപ്രവർത്തകർക്കാണ്; മുതിർന്ന ഭാരവാഹികൾ സ്ഥിരം സംഘാടകരായി തുടരുന്നതിന് പകരം കൂടുതലായി മെന്റർമാരുടെ പങ്ക് ഏറ്റെടുക്കുന്നു. പുതിയ തലമുറ മുന്നോട്ടുവരണമെന്ന് ആവശ്യപ്പെടുന്നതുപോലെ തന്നെ, പരിചയസമ്പന്നർ ഒരു പടി പിന്നോട്ട് മാറണമെന്നും ഈ ക്രമീകരണം ആവശ്യപ്പെടുന്നു.

നേതൃത്വം സന്തുലനവും ആവശ്യപ്പെട്ടു. വലിയൊരു അംഗസമൂഹത്തിന്റെ പ്രതീക്ഷകളും, സന്നദ്ധപ്രവർത്തകരെയും സംഭാവനകളെയും ആശ്രയിക്കുന്ന ഒരു സംഘടനയുടെ പ്രായോഗിക പരിമിതികളും തമ്മിൽ സന്തുലനം കണ്ടെത്തേണ്ട സാഹചര്യങ്ങൾ അദ്ദേഹത്തിന് ചിലപ്പോൾ നേരിടേണ്ടിവന്നു. വ്യത്യസ്ത പൊതുനിലപാടുകളുള്ള ആളുകളുമായി ഇടപെടേണ്ടതും അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ ഭാഗമായിരുന്നു. SNCDCയുടെ സമൂഹപരിപാടികൾ വ്യത്യസ്ത നിലപാടുകളിലും പശ്ചാത്തലങ്ങളിലുമുള്ള ആളുകൾക്ക് പ്രാപ്യമായിരിക്കണമെന്ന് അദ്ദേഹം നിലനിർത്തി. അതേസമയം വിദ്യാഭ്യാസം, സാമൂഹിക മുന്നേറ്റം, പ്രതിനിധാനം, പൊതുസ്ഥാപനങ്ങളിലേക്കുള്ള പ്രവേശനം തുടങ്ങിയ വിഷയങ്ങളിൽ അദ്ദേഹം ഇടയ്ക്ക് പൊതുവായി അഭിപ്രായം പറഞ്ഞിട്ടുമുണ്ട്.

SNCDCയുടെ പ്രസിഡന്റ്, വിദ്യാഭ്യാസ സമിതി ചെയർമാൻ, കമ്മ്യൂണിറ്റി ഡെവലപ്മെന്റ് പ്രോഗ്രാം കൺവീനർ, സംഘടനയുടെ വിദ്യാഭ്യാസ ട്രസ്റ്റിന്റെ ഭരണസമിതി അംഗം എന്നീ ചുമതലകൾ വർഷങ്ങൾക്കിടെ അദ്ദേഹം വഹിച്ചു. വൊക്കേഷണൽ വിദ്യാഭ്യാസവും സമൂഹവികസനവും സംബന്ധിച്ച ഉപദേശക സമിതികളിലും അദ്ദേഹം പ്രവർത്തിച്ചിട്ടുണ്ട്.

സംഘടനയ്ക്ക് പുറത്ത് താരതമ്യേന സാധാരണമായ കുടുംബജീവിതമാണ് ഗോപാലകൃഷ്ണന്റേത്. ഭാര്യ രാധ വിരമിച്ച അധ്യാപികയാണ്; രണ്ടു മക്കൾ വൈദ്യശാസ്ത്ര-വിദ്യാഭ്യാസ മേഖലകളിൽ പ്രവർത്തിക്കുന്നു. പുസ്തകങ്ങളിലും ക്ലാസിക്കൽ മലയാള സാഹിത്യത്തിലും, തൊഴിൽജീവിതത്തിലേക്ക് കടക്കുന്ന യുവാക്കളുമായുള്ള സംഭാഷണങ്ങളിലും അദ്ദേഹത്തിന് ഇന്നും താൽപര്യമുണ്ട് — സംഘടനയിലെ യുവ സന്നദ്ധപ്രവർത്തകരുമായുള്ള പ്രവർത്തനത്തോട് സ്വാഭാവികമായി ചേർന്നുനിൽക്കുന്ന ഒരു താൽപര്യം.

പലരും ഉത്തരവാദിത്തങ്ങൾ കുറച്ചുതുടങ്ങുന്ന പ്രായത്തിൽ, നേതൃത്വ കൈമാറ്റത്തിലാണ് അദ്ദേഹത്തിന്റെ താൽപര്യം കൂടുതലായി വളർന്നത്. SNCDC വ്യക്തിഗത നേതാക്കളെ കുറച്ചുമാത്രം ആശ്രയിക്കുന്നതും അടുത്ത തലമുറയിലെ സന്നദ്ധപ്രവർത്തകരെയും വിദ്യാഭ്യാസ പ്രവർത്തകരെയും സമൂഹസംഘാടകരെയും വളർത്താൻ കൂടുതൽ കഴിവുള്ളതുമായ സംഘടനയാകണം എന്നാണ് അദ്ദേഹത്തിന്റെ ആഗ്രഹം.

താൻ തുടങ്ങിയ ഇടവും ഇന്ന് എത്തിനിൽക്കുന്ന ഇടവും തമ്മിൽ അദ്ദേഹം ഒരു തുടർച്ച കാണുന്നു. കുറച്ച് വിദ്യാർത്ഥികൾക്ക് വിദ്യാഭ്യാസസഹായം ലഭ്യമാക്കാൻ സഹായിച്ചുകൊണ്ടാണ് അദ്ദേഹം തുടങ്ങിയത്; പിന്നീട് സമൂഹജീവിതത്തിന്റെ പല മേഖലകളിലും പ്രവർത്തിക്കുന്ന ഒരു സ്ഥാപനത്തിന്റെ ഉത്തരവാദിത്തം അദ്ദേഹത്തിൽ വന്നുചേർന്നു. അദ്ദേഹത്തിന് ഈ രണ്ടു ഘട്ടങ്ങളും ഒന്നിച്ചുനിൽക്കുന്നവയാണ്. ഒരു സംഘടനയുടെ ലക്ഷ്യം ഇന്നത്തെ പ്രശ്നങ്ങൾ പരിഹരിക്കുക മാത്രമല്ല; നാളത്തെ പ്രശ്നങ്ങൾ പരിഹരിക്കാൻ കഴിവുള്ള ആളുകളെ ബാക്കിവെക്കുക കൂടിയാണെന്ന് അദ്ദേഹം വിശ്വസിക്കുന്നു.',
        ],
    ],

    'p.sreedharan' => [
        'name' => 'P. Sreedharan',
        'tier' => 'distinguished',
        'profession' => 'Community Organisation Leader and Social Representation Advocate',
        'portrait' => 'p_sreedharan.jpg',
        'en' => [
            'title' => 'P. Sreedharan',
            'summary' => 'P. Sreedharan’s public life has taken shape over several decades through a community organisation that began with mutual assistance and gradually expanded into education, youth development and social representation. His work has rested on a belief that community organisations are most effective when they create institutions and opportunities that remain useful beyond the tenure of any one office-bearer — and he has put that belief into practice less through single dramatic gestures than through systems, records and the preparation of those who will come after.',
            'body' => 'P. Sreedharan’s public life has taken shape over several decades through a community organisation that began with mutual assistance and gradually expanded into education, youth development and social representation. His work has rested on a belief that community organisations are most effective when they create institutions and opportunities that remain useful beyond the tenure of any one office-bearer — and he has put that belief into practice less through single dramatic gestures than through systems, records and the preparation of those who will come after.

He became involved in community activities at a time when many families in his area were seeking better access to education and stable employment. The effort began modestly, as a small scholarship and assistance programme, and over time it developed into a wider network of volunteers. Sreedharan’s part was to give that growth a structure. He helped establish systems for identifying the students who required support, for maintaining transparent records and for involving local donors in a structured way.

Education remained central to the organisation’s development, and its scope widened over the years. Scholarships were later supplemented by career guidance, study materials and orientation programmes for young people who were the first in their families to pursue higher education. Sreedharan encouraged a gradual change of emphasis, from providing assistance to building confidence and capacity among those who received it.

Community representation became the other major strand of his work. He has participated in consultations concerning access to public institutions, local development, welfare services and the concerns of communities that may not always have an organised channel through which to present their views. His approach has generally followed the same pattern: prepare a clear representation, seek dialogue, and maintain a record of what was promised and what was actually implemented. The last step is easily overlooked, yet it is what allows a promise to be revisited later.

Over the years he also observed a problem common to many voluntary organisations: institutions can become overly dependent on a small group of experienced individuals. Partly in response, he encouraged younger members to take on organisational responsibilities. Committees were created around education, welfare, youth activities and community development, with senior office-bearers acting increasingly as mentors.

Succession has therefore received unusual emphasis in his work. He believes that a community organisation should be capable of producing new organisers who understand both the history of the institution and the changing needs of the people it serves. He has supported leadership training, the documentation of organisational decisions and greater participation by younger members and women. Documentation, in fact, runs through both strands of his public life: a record of what was promised to the community, and a record of what the organisation itself decided.

His contribution lies not in a single campaign or project but in the gradual strengthening of a community institution. For Sreedharan, representation becomes meaningful when it leads to participation, and participation becomes durable when people acquire the confidence and organisational knowledge to continue the work themselves.',
        ],
        'ml' => [
            'title' => 'P. Sreedharan',
            'summary' => 'പരസ്പരസഹായത്തിൽ തുടങ്ങി ക്രമേണ വിദ്യാഭ്യാസം, യുവജനവികസനം, സാമൂഹിക പ്രതിനിധാനം എന്നീ മേഖലകളിലേക്ക് വളർന്ന ഒരു സമൂഹസംഘടനയിലൂടെയാണ് പി. ശ്രീധരന്റെ പൊതുജീവിതം നിരവധി ദശാബ്ദങ്ങളിലായി രൂപപ്പെട്ടത്. ഏതെങ്കിലും ഒരു ഭാരവാഹിയുടെ കാലാവധിക്കപ്പുറവും ഉപകാരപ്പെടുന്ന സ്ഥാപനങ്ങളും അവസരങ്ങളും സൃഷ്ടിക്കുമ്പോഴാണ് സമൂഹസംഘടനകൾ ഏറ്റവും ഫലപ്രദമാകുന്നത് എന്ന വിശ്വാസമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ അടിത്തറ. ഒറ്റപ്പെട്ട നാടകീയ നീക്കങ്ങളേക്കാൾ, ക്രമമായ സംവിധാനങ്ങളിലൂടെയും രേഖകളിലൂടെയും പിന്നാലെ വരുന്നവരെ ഒരുക്കുന്നതിലൂടെയുമാണ് അദ്ദേഹം ആ വിശ്വാസം പ്രാവർത്തികമാക്കിയത്.',
            'body' => 'പരസ്പരസഹായത്തിൽ തുടങ്ങി ക്രമേണ വിദ്യാഭ്യാസം, യുവജനവികസനം, സാമൂഹിക പ്രതിനിധാനം എന്നീ മേഖലകളിലേക്ക് വളർന്ന ഒരു സമൂഹസംഘടനയിലൂടെയാണ് പി. ശ്രീധരന്റെ പൊതുജീവിതം നിരവധി ദശാബ്ദങ്ങളിലായി രൂപപ്പെട്ടത്. ഏതെങ്കിലും ഒരു ഭാരവാഹിയുടെ കാലാവധിക്കപ്പുറവും ഉപകാരപ്പെടുന്ന സ്ഥാപനങ്ങളും അവസരങ്ങളും സൃഷ്ടിക്കുമ്പോഴാണ് സമൂഹസംഘടനകൾ ഏറ്റവും ഫലപ്രദമാകുന്നത് എന്ന വിശ്വാസമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ അടിത്തറ. ഒറ്റപ്പെട്ട നാടകീയ നീക്കങ്ങളേക്കാൾ, ക്രമമായ സംവിധാനങ്ങളിലൂടെയും രേഖകളിലൂടെയും പിന്നാലെ വരുന്നവരെ ഒരുക്കുന്നതിലൂടെയുമാണ് അദ്ദേഹം ആ വിശ്വാസം പ്രാവർത്തികമാക്കിയത്.

അദ്ദേഹത്തിന്റെ പ്രദേശത്തെ നിരവധി കുടുംബങ്ങൾ വിദ്യാഭ്യാസത്തിനും സ്ഥിരതയുള്ള തൊഴിലിനും മെച്ചപ്പെട്ട അവസരങ്ങൾ തേടിയിരുന്ന കാലത്താണ് ശ്രീധരൻ സമൂഹപ്രവർത്തനങ്ങളിൽ സജീവമായത്. ചെറിയൊരു സ്കോളർഷിപ്പ്-സഹായ പരിപാടിയായാണ് ആ ശ്രമം തുടങ്ങിയത്; കാലക്രമേണ അത് സന്നദ്ധപ്രവർത്തകരുടെ വിശാലമായ ശൃംഖലയായി വളർന്നു. ആ വളർച്ചയ്ക്ക് ഒരു ചട്ടക്കൂട് നൽകുകയായിരുന്നു ശ്രീധരന്റെ പങ്ക്. സഹായം ആവശ്യമുള്ള വിദ്യാർത്ഥികളെ കണ്ടെത്താനും രേഖകൾ സുതാര്യമായി സൂക്ഷിക്കാനും പ്രാദേശിക സഹായദാതാക്കളെ ക്രമബദ്ധമായി പങ്കാളികളാക്കാനുമുള്ള സംവിധാനങ്ങൾ രൂപപ്പെടുത്തുന്നതിൽ അദ്ദേഹം പങ്കുവഹിച്ചു.

സംഘടനയുടെ വളർച്ചയിൽ വിദ്യാഭ്യാസം എന്നും കേന്ദ്രസ്ഥാനത്ത് നിന്നു; വർഷങ്ങൾ കഴിയുന്തോറും അതിന്റെ വ്യാപ്തിയും കൂടി. സ്കോളർഷിപ്പുകൾക്കൊപ്പം തൊഴിൽ മാർഗനിർദ്ദേശം, പഠനസാമഗ്രികൾ, കുടുംബത്തിൽ ആദ്യമായി ഉന്നതവിദ്യാഭ്യാസത്തിലേക്ക് കടക്കുന്ന യുവാക്കൾക്കായുള്ള ഓറിയന്റേഷൻ പരിപാടികൾ എന്നിവയും പിന്നീട് ആരംഭിച്ചു. സഹായം നൽകുന്നതിൽ നിന്ന്, സഹായം ലഭിക്കുന്നവരുടെ ആത്മവിശ്വാസവും കഴിവും വളർത്തുന്നതിലേക്ക് ഊന്നൽ ക്രമേണ മാറണമെന്ന് ശ്രീധരൻ പ്രോത്സാഹിപ്പിച്ചു.

സമൂഹ പ്രതിനിധാനമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിലെ മറ്റൊരു പ്രധാന ധാര. പൊതുസ്ഥാപനങ്ങളിലേക്കുള്ള പ്രവേശനം, പ്രാദേശിക വികസനം, ക്ഷേമസേവനങ്ങൾ, തങ്ങളുടെ അഭിപ്രായങ്ങൾ അവതരിപ്പിക്കാൻ എല്ലായ്പ്പോഴും സംഘടിതമായ വഴിയില്ലാത്ത സമൂഹങ്ങളുടെ ആശങ്കകൾ എന്നിവയുമായി ബന്ധപ്പെട്ട ചർച്ചകളിൽ അദ്ദേഹം പങ്കെടുത്തിട്ടുണ്ട്. പൊതുവെ ഒരേ രീതിയാണ് അദ്ദേഹം പിന്തുടർന്നത്: വ്യക്തമായ നിവേദനം തയ്യാറാക്കുക, സംവാദത്തിന് ശ്രമിക്കുക, വാഗ്ദാനം ചെയ്തതും യഥാർത്ഥത്തിൽ നടപ്പാക്കിയതും രേഖപ്പെടുത്തുക. അവസാനത്തെ ഘട്ടം എളുപ്പം ശ്രദ്ധിക്കപ്പെടാതെ പോകാം; എന്നാൽ ഒരു വാഗ്ദാനം പിന്നീട് വീണ്ടും ഉന്നയിക്കാൻ സാധ്യമാക്കുന്നത് അതാണ്.

പല സന്നദ്ധസംഘടനകളിലും അദ്ദേഹം ഒരു പ്രശ്നം ശ്രദ്ധിച്ചിരുന്നു: പരിചയസമ്പന്നരായ ചുരുക്കം ചിലരെ സ്ഥാപനങ്ങൾ അമിതമായി ആശ്രയിക്കാൻ തുടങ്ങും. ഭാഗികമായി അതിനുള്ള മറുപടിയെന്ന നിലയിൽ, സംഘടനാ ഉത്തരവാദിത്തങ്ങൾ ഏറ്റെടുക്കാൻ യുവ അംഗങ്ങളെ അദ്ദേഹം പ്രോത്സാഹിപ്പിച്ചു. വിദ്യാഭ്യാസം, ക്ഷേമം, യുവജന പ്രവർത്തനം, സമൂഹവികസനം എന്നീ മേഖലകൾക്കായി കമ്മിറ്റികൾ രൂപീകരിക്കപ്പെട്ടു; മുതിർന്ന ഭാരവാഹികൾ ക്രമേണ മെന്റർമാരുടെ പങ്കിലേക്ക് മാറി.

അതുകൊണ്ടുതന്നെ നേതൃത്വ കൈമാറ്റത്തിന് അദ്ദേഹം പ്രത്യേക പ്രാധാന്യം നൽകി. സ്ഥാപനത്തിന്റെ ചരിത്രവും അത് സേവിക്കുന്ന ആളുകളുടെ മാറുന്ന ആവശ്യങ്ങളും ഒരുപോലെ മനസ്സിലാക്കുന്ന പുതിയ സംഘാടകരെ വളർത്താൻ ഒരു സമൂഹസംഘടനയ്ക്ക് കഴിയണം എന്നാണ് അദ്ദേഹത്തിന്റെ വിശ്വാസം. നേതൃപരിശീലനം, സംഘടനാ തീരുമാനങ്ങളുടെ രേഖപ്പെടുത്തൽ, യുവാക്കളുടെയും സ്ത്രീകളുടെയും കൂടുതൽ പങ്കാളിത്തം എന്നിവയ്ക്ക് അദ്ദേഹം പിന്തുണ നൽകി. രേഖപ്പെടുത്തൽ അദ്ദേഹത്തിന്റെ പൊതുജീവിതത്തിന്റെ രണ്ടു ധാരകളിലും കാണാം — സമൂഹത്തിന് നൽകിയ വാഗ്ദാനങ്ങളുടെ രേഖയും, സംഘടന സ്വയം എടുത്ത തീരുമാനങ്ങളുടെ രേഖയും.

ശ്രീധരന്റെ സംഭാവന ഏതെങ്കിലും ഒരു പ്രചാരണത്തിലോ പദ്ധതിയിലോ ഒതുങ്ങുന്നില്ല; ഒരു സമൂഹസ്ഥാപനത്തെ ക്രമേണ ശക്തിപ്പെടുത്തിയതിലാണ് അത്. പ്രതിനിധാനം ജനപങ്കാളിത്തത്തിലേക്ക് നയിക്കുമ്പോഴാണ് അതിന് അർത്ഥമുണ്ടാകുന്നത്; ആളുകൾക്ക് സ്വയം പ്രവർത്തനം തുടരാനുള്ള ആത്മവിശ്വാസവും സംഘടനാ അറിവും ലഭിക്കുമ്പോഴാണ് ആ പങ്കാളിത്തം നിലനിൽക്കുന്നതെന്നും ശ്രീധരൻ വിശ്വസിക്കുന്നു.',
        ],
    ],

    'v.suresh.babu' => [
        'name' => 'V. Suresh Babu',
        'tier' => 'distinguished',
        'profession' => 'Workers’ Movement and Community Development Leader',
        'portrait' => 'v_suresh_babu.jpg',
        'en' => [
            'title' => 'V. Suresh Babu',
            'summary' => 'V. Suresh Babu’s public life has been shaped by a conviction that the wellbeing of working families cannot be separated from the quality of the places in which they live. Over a long period of grassroots work, that conviction has kept him moving between workplace concerns and wider community issues, at the meeting point of workers’ organisation, neighbourhood development and community welfare.',
            'body' => 'V. Suresh Babu’s public life has been shaped by a conviction that the wellbeing of working families cannot be separated from the quality of the places in which they live. Over a long period of grassroots work, that conviction has kept him moving between workplace concerns and wider community issues, at the meeting point of workers’ organisation, neighbourhood development and community welfare.

His organisational life began in workers’ meetings and local welfare activities. He gradually took responsibility for coordinating members across several workplaces, and later became involved in community programmes concerning housing, education, public services and emergency assistance. That experience led him to favour practical organisation over highly centralised decision-making.

One of his important initiatives was the creation of a community assistance network that brought together workers, retired employees and local volunteers. The network helped families through periods of sudden financial difficulty and supported the educational needs of children. Suresh insisted, however, that help should not arrive on its own. Assistance was to be accompanied by information and guidance, so that families could understand what other institutional support might be available to them.

The same practical outlook informed his involvement in discussions around housing and basic services in working-class neighbourhoods. Instead of treating each problem as an isolated complaint, he encouraged residents to document common issues, identify the responsible agencies and work collectively. The approach sometimes required patience, because improvements in public infrastructure rarely occur quickly.

As his responsibilities grew, Suresh became increasingly concerned with institutional continuity: with whether the organisation could carry on beyond the people leading it at any one time. He supported training programmes for younger organisers and encouraged women members to take more active roles in committees and community initiatives. He believes that an organisation becomes stronger when leadership is distributed, and when ordinary members understand both the purpose of the institution and its limits.

His years of work have also exposed him to disagreements within organisations. He regards disagreement as a normal part of collective life and prefers structured discussion to personal confrontation. Listening to different views, recording decisions and returning to an agreed objective are among the principles he has tried to reinforce. As in his neighbourhood work, the written record matters here too.

Suresh’s contribution represents a form of public leadership built through sustained organisation rather than a single visible moment. His emphasis remains on dignity at work, on stronger communities, and on developing people capable of carrying collective institutions forward.',
        ],
        'ml' => [
            'title' => 'V. Suresh Babu',
            'summary' => 'ജോലി ചെയ്യുന്ന കുടുംബങ്ങളുടെ ക്ഷേമം അവർ ജീവിക്കുന്ന പ്രദേശങ്ങളുടെ നിലവാരത്തിൽ നിന്ന് വേർതിരിക്കാനാവില്ല എന്ന ബോധ്യമാണ് വി. സുരേഷ് ബാബുവിന്റെ പൊതുജീവിതത്തെ രൂപപ്പെടുത്തിയത്. ദീർഘകാലത്തെ അടിത്തട്ടിലെ പ്രവർത്തനത്തിനിടെ ആ ബോധ്യം അദ്ദേഹത്തെ ജോലിസ്ഥലത്തെ പ്രശ്നങ്ങൾക്കും വിശാലമായ സാമൂഹിക പ്രശ്നങ്ങൾക്കും ഇടയിൽ നിരന്തരം സഞ്ചരിപ്പിച്ചു — തൊഴിലാളി സംഘടനാ പ്രവർത്തനവും പ്രദേശവികസനവും സമൂഹക്ഷേമവും സംഗമിക്കുന്ന ഇടത്ത്.',
            'body' => 'ജോലി ചെയ്യുന്ന കുടുംബങ്ങളുടെ ക്ഷേമം അവർ ജീവിക്കുന്ന പ്രദേശങ്ങളുടെ നിലവാരത്തിൽ നിന്ന് വേർതിരിക്കാനാവില്ല എന്ന ബോധ്യമാണ് വി. സുരേഷ് ബാബുവിന്റെ പൊതുജീവിതത്തെ രൂപപ്പെടുത്തിയത്. ദീർഘകാലത്തെ അടിത്തട്ടിലെ പ്രവർത്തനത്തിനിടെ ആ ബോധ്യം അദ്ദേഹത്തെ ജോലിസ്ഥലത്തെ പ്രശ്നങ്ങൾക്കും വിശാലമായ സാമൂഹിക പ്രശ്നങ്ങൾക്കും ഇടയിൽ നിരന്തരം സഞ്ചരിപ്പിച്ചു — തൊഴിലാളി സംഘടനാ പ്രവർത്തനവും പ്രദേശവികസനവും സമൂഹക്ഷേമവും സംഗമിക്കുന്ന ഇടത്ത്.

തൊഴിലാളി യോഗങ്ങളിലൂടെയും പ്രാദേശിക ക്ഷേമ പ്രവർത്തനങ്ങളിലൂടെയുമാണ് അദ്ദേഹത്തിന്റെ സംഘടനാ ജീവിതം ആരംഭിച്ചത്. ക്രമേണ പല ജോലിസ്ഥലങ്ങളിലെ അംഗങ്ങളെ ഏകോപിപ്പിക്കുന്ന ചുമതല അദ്ദേഹം ഏറ്റെടുത്തു; പിന്നീട് പാർപ്പിടം, വിദ്യാഭ്യാസം, പൊതുസേവനങ്ങൾ, അടിയന്തര സഹായം എന്നിവയുമായി ബന്ധപ്പെട്ട സമൂഹപരിപാടികളിലും സജീവമായി. അമിതമായി കേന്ദ്രീകൃതമായ തീരുമാനമെടുക്കലിനേക്കാൾ പ്രായോഗികമായ സംഘടനാ പ്രവർത്തനത്തിന് മുൻഗണന നൽകാൻ ഈ അനുഭവം അദ്ദേഹത്തെ പ്രേരിപ്പിച്ചു.

തൊഴിലാളികളെയും വിരമിച്ച ജീവനക്കാരെയും പ്രാദേശിക സന്നദ്ധപ്രവർത്തകരെയും ഒരുമിപ്പിച്ച ഒരു സമൂഹസഹായ ശൃംഖലയുടെ രൂപീകരണം അദ്ദേഹത്തിന്റെ പ്രധാന സംരംഭങ്ങളിലൊന്നാണ്. അപ്രതീക്ഷിതമായ സാമ്പത്തിക ബുദ്ധിമുട്ടുകളുടെ കാലത്ത് ഈ ശൃംഖല കുടുംബങ്ങളെ സഹായിച്ചു; കുട്ടികളുടെ വിദ്യാഭ്യാസ ആവശ്യങ്ങൾക്കും പിന്തുണ നൽകി. എന്നാൽ സഹായം മാത്രമായി എത്തരുതെന്ന് സുരേഷ് നിർബന്ധം പിടിച്ചു. ലഭ്യമായേക്കാവുന്ന മറ്റ് സ്ഥാപനപരമായ പിന്തുണകളെക്കുറിച്ച് കുടുംബങ്ങൾക്ക് മനസ്സിലാക്കാൻ കഴിയുംവിധം, സഹായത്തോടൊപ്പം വിവരവും മാർഗനിർദ്ദേശവും ലഭിക്കണമായിരുന്നു.

തൊഴിലാളികൾ താമസിക്കുന്ന പ്രദേശങ്ങളിലെ പാർപ്പിടവും അടിസ്ഥാനസൗകര്യങ്ങളും സംബന്ധിച്ച ചർച്ചകളിലെ അദ്ദേഹത്തിന്റെ ഇടപെടലിലും ഇതേ പ്രായോഗിക സമീപനം കാണാം. ഓരോ പ്രശ്നത്തെയും ഒറ്റപ്പെട്ട പരാതിയായി കാണുന്നതിന് പകരം, പൊതുവായ പ്രശ്നങ്ങൾ രേഖപ്പെടുത്താനും ഉത്തരവാദിത്തമുള്ള ഏജൻസികളെ കണ്ടെത്താനും കൂട്ടായി പ്രവർത്തിക്കാനും അദ്ദേഹം നാട്ടുകാരെ പ്രോത്സാഹിപ്പിച്ചു. പൊതു അടിസ്ഥാനസൗകര്യങ്ങളിലെ മെച്ചപ്പെടുത്തലുകൾ വേഗത്തിൽ ഉണ്ടാകുന്നത് അപൂർവമായതിനാൽ, ഈ രീതിക്ക് ചിലപ്പോൾ ക്ഷമ ആവശ്യമായി വന്നു.

ഉത്തരവാദിത്തങ്ങൾ വർധിച്ചതോടെ സ്ഥാപനത്തിന്റെ തുടർച്ചയെക്കുറിച്ച് — ഏതെങ്കിലും ഒരു ഘട്ടത്തിൽ നയിക്കുന്നവർക്കപ്പുറവും സംഘടന മുന്നോട്ടുപോകുമോ എന്നതിനെക്കുറിച്ച് — സുരേഷ് കൂടുതൽ ശ്രദ്ധാലുവായി. യുവ സംഘാടകർക്കുള്ള പരിശീലന പരിപാടികളെ അദ്ദേഹം പിന്തുണച്ചു; കമ്മിറ്റികളിലും സമൂഹസംരംഭങ്ങളിലും കൂടുതൽ സജീവമായ പങ്ക് വഹിക്കാൻ വനിതാ അംഗങ്ങളെ പ്രോത്സാഹിപ്പിച്ചു. നേതൃത്വം പങ്കിട്ടിരിക്കുമ്പോഴും, സാധാരണ അംഗങ്ങൾക്ക് സ്ഥാപനത്തിന്റെ ലക്ഷ്യവും പരിധികളും ഒരുപോലെ മനസ്സിലാകുമ്പോഴുമാണ് സംഘടന കൂടുതൽ ശക്തമാകുന്നതെന്ന് അദ്ദേഹം വിശ്വസിക്കുന്നു.

വർഷങ്ങൾ നീണ്ട പ്രവർത്തനം സംഘടനകൾക്കുള്ളിലെ അഭിപ്രായവ്യത്യാസങ്ങളും അദ്ദേഹത്തിന് പരിചിതമാക്കി. കൂട്ടായ ജീവിതത്തിൽ അഭിപ്രായവ്യത്യാസം സ്വാഭാവികമാണെന്ന് അദ്ദേഹം കരുതുന്നു; വ്യക്തിപരമായ ഏറ്റുമുട്ടലിനേക്കാൾ ക്രമബദ്ധമായ ചർച്ചയാണ് അദ്ദേഹത്തിന് താൽപര്യം. വ്യത്യസ്ത അഭിപ്രായങ്ങൾ കേൾക്കുക, തീരുമാനങ്ങൾ രേഖപ്പെടുത്തുക, അംഗീകരിച്ച ലക്ഷ്യത്തിലേക്ക് വീണ്ടും മടങ്ങുക — ഇവയാണ് അദ്ദേഹം ഉറപ്പിക്കാൻ ശ്രമിച്ച തത്വങ്ങളിൽ ചിലത്. പ്രദേശത്തെ പ്രവർത്തനത്തിലെന്നപോലെ ഇവിടെയും രേഖപ്പെടുത്തലിന് പ്രാധാന്യമുണ്ട്.

ഒരു ശ്രദ്ധേയ നിമിഷത്തിലൂടെയല്ല, തുടർച്ചയായ സംഘടനാ പ്രവർത്തനത്തിലൂടെ രൂപപ്പെട്ട പൊതുനേതൃത്വത്തിന്റെ മാതൃകയാണ് സുരേഷ് ബാബുവിന്റെ സംഭാവന. ജോലിസ്ഥലത്തെ മാന്യത, ശക്തമായ സമൂഹങ്ങൾ, കൂട്ടായ സ്ഥാപനങ്ങളെ മുന്നോട്ടുകൊണ്ടുപോകാൻ കഴിവുള്ള ആളുകളുടെ വളർച്ച — ഇവയിൽ തന്നെയാണ് അദ്ദേഹത്തിന്റെ ഊന്നൽ ഇന്നും.',
        ],
    ],

    'arjun.menon' => [
        'name' => 'Arjun Menon',
        'tier' => 'distinguished',
        'profession' => 'Panchayat Leader and Community Development Organiser',
        'portrait' => 'arjun_menon.jpg',
        'en' => [
            'title' => 'Arjun Menon',
            'summary' => 'Arjun Menon was still in his twenties when he began paying closer attention to the gap between the plans discussed at local meetings and the way those plans were experienced by people in his panchayat. His first involvement was not a search for public office. It grew from neighbourhood discussions about drainage, waste management, footpaths and the condition of small public spaces.',
            'body' => 'Arjun Menon was still in his twenties when he began paying closer attention to the gap between the plans discussed at local meetings and the way those plans were experienced by people in his panchayat. His first involvement was not a search for public office. It grew from neighbourhood discussions about drainage, waste management, footpaths and the condition of small public spaces.

A recurring theme in his early work was that younger residents were willing to participate when they were given a practical responsibility. Arjun helped organise small volunteer groups to map local problem spots and record what residents were reporting. The experience changed his understanding of local governance: a complaint became more useful when it could be described clearly, located precisely and followed up.

His entry into formal panchayat responsibilities developed from this community involvement. He became particularly interested in projects where environmental improvement and everyday convenience could be addressed together. Small drainage improvements, waste-awareness work and efforts to make public spaces more usable became part of his local priorities.

One turning point came when a community proposal generated considerable enthusiasm but little follow-through. Arjun realised that agreement at a meeting did not automatically create ownership. He began breaking projects into smaller responsibilities and asking participants to identify what they could actually undertake. That experience has influenced his approach to leadership ever since.

Away from public responsibilities, he enjoys walking, following local football and spending time outdoors. He says these ordinary interests help him remain connected to the same everyday environment about which he is expected to make decisions.

At 28, his public journey is still developing. What distinguishes it is his effort to make local governance feel less distant to younger residents. His ambition is not simply to complete projects, but to help create a local culture in which people see the panchayat as a space they can participate in rather than an institution they approach only when something goes wrong.',
        ],
        'ml' => [
            'title' => 'Arjun Menon',
            'summary' => 'അർജുൻ മേനോൻ ഇരുപതുകളുടെ അവസാനത്തിലേക്കെത്തുമ്പോഴേക്കും പഞ്ചായത്ത് തല യോഗങ്ങളിൽ ചർച്ച ചെയ്യുന്ന പദ്ധതികളും അവ ജനങ്ങളുടെ ദൈനംദിന ജീവിതത്തിൽ അനുഭവപ്പെടുന്ന രീതിയും തമ്മിലുള്ള വ്യത്യാസം ശ്രദ്ധിക്കാൻ തുടങ്ങി. പൊതുപ്രവർത്തനത്തിലേക്കുള്ള അദ്ദേഹത്തിന്റെ പ്രവേശനം ഒരു പദവിക്കായുള്ള ശ്രമമായിരുന്നില്ല. വെള്ളക്കെട്ട്, മാലിന്യസംസ്‌കരണം, നടപ്പാതകൾ, ചെറിയ പൊതുസ്ഥലങ്ങളുടെ പരിപാലനം തുടങ്ങിയ നാട്ടിലെ പ്രശ്നങ്ങളെക്കുറിച്ചുള്ള ചർച്ചകളിലൂടെയാണ് അത് വളർന്നത്.',
            'body' => 'അർജുൻ മേനോൻ ഇരുപതുകളുടെ അവസാനത്തിലേക്കെത്തുമ്പോഴേക്കും പഞ്ചായത്ത് തല യോഗങ്ങളിൽ ചർച്ച ചെയ്യുന്ന പദ്ധതികളും അവ ജനങ്ങളുടെ ദൈനംദിന ജീവിതത്തിൽ അനുഭവപ്പെടുന്ന രീതിയും തമ്മിലുള്ള വ്യത്യാസം ശ്രദ്ധിക്കാൻ തുടങ്ങി. പൊതുപ്രവർത്തനത്തിലേക്കുള്ള അദ്ദേഹത്തിന്റെ പ്രവേശനം ഒരു പദവിക്കായുള്ള ശ്രമമായിരുന്നില്ല. വെള്ളക്കെട്ട്, മാലിന്യസംസ്‌കരണം, നടപ്പാതകൾ, ചെറിയ പൊതുസ്ഥലങ്ങളുടെ പരിപാലനം തുടങ്ങിയ നാട്ടിലെ പ്രശ്നങ്ങളെക്കുറിച്ചുള്ള ചർച്ചകളിലൂടെയാണ് അത് വളർന്നത്.

യുവാക്കൾക്ക് പ്രായോഗികമായ ഉത്തരവാദിത്വം നൽകുമ്പോൾ അവർ കൂടുതൽ സജീവമായി പങ്കെടുക്കുമെന്ന് അർജുൻ മനസ്സിലാക്കി. നാട്ടിലെ പ്രശ്നബാധിത പ്രദേശങ്ങൾ രേഖപ്പെടുത്താനും ജനങ്ങൾ ഉന്നയിക്കുന്ന വിഷയങ്ങൾ ക്രമപ്പെടുത്തി അവതരിപ്പിക്കാനും അദ്ദേഹം ചെറിയ സന്നദ്ധ സംഘങ്ങളെ സഹായിച്ചു. ഒരു പരാതി വ്യക്തമായി വിവരിക്കുകയും സ്ഥലവും സാഹചര്യവും രേഖപ്പെടുത്തുകയും തുടർന്ന് അതിന്റെ പുരോഗതി പിന്തുടരുകയും ചെയ്യുമ്പോൾ അത് കൂടുതൽ ഫലപ്രദമാകുമെന്ന് ഈ അനുഭവം അദ്ദേഹത്തെ പഠിപ്പിച്ചു.

ഈ സമൂഹപങ്കാളിത്തമാണ് പിന്നീട് പഞ്ചായത്തുതല ഉത്തരവാദിത്വങ്ങളിലേക്ക് അദ്ദേഹത്തെ നയിച്ചത്. പരിസ്ഥിതി സംരക്ഷണവും ജനങ്ങളുടെ ദൈനംദിന സൗകര്യങ്ങളും ഒരുമിച്ച് പരിഗണിക്കാവുന്ന പ്രവർത്തനങ്ങളിലാണ് അദ്ദേഹത്തിന് പ്രത്യേക താൽപര്യം. ചെറിയ ഡ്രെയിനേജ് മെച്ചപ്പെടുത്തലുകൾ, മാലിന്യസംസ്‌കരണ ബോധവത്കരണം, പൊതുസ്ഥലങ്ങളെ കൂടുതൽ ഉപയോഗപ്രദമാക്കാനുള്ള ശ്രമങ്ങൾ എന്നിവ അദ്ദേഹത്തിന്റെ പ്രാദേശിക മുൻഗണനകളായി.

ഒരു സമൂഹപദ്ധതിക്ക് വലിയ ആവേശം ലഭിച്ചെങ്കിലും പിന്നീട് അതിന്റെ പ്രവർത്തനം മുന്നോട്ട് പോകാതെ വന്നത് ഒരു വഴിത്തിരിവായി. ഒരു യോഗത്തിലെ സമ്മതം മാത്രം ഒരു പദ്ധതിയുടെ ഉത്തരവാദിത്വം ഉറപ്പാക്കുന്നില്ലെന്ന് അർജുൻ തിരിച്ചറിഞ്ഞു. അതിനുശേഷം പ്രവർത്തനങ്ങളെ ചെറിയ ഉത്തരവാദിത്വങ്ങളാക്കി വിഭജിക്കുകയും ഓരോരുത്തർക്കും യഥാർത്ഥത്തിൽ ഏറ്റെടുക്കാനാകുന്ന കാര്യങ്ങൾ കണ്ടെത്തുകയും ചെയ്തു.

പൊതുപ്രവർത്തനത്തിന് പുറത്തുള്ള സമയത്ത് നടക്കാനും പ്രാദേശിക ഫുട്ബോൾ പിന്തുടരാനും പുറത്തുള്ള പ്രകൃതിയോട് സമയം ചെലവഴിക്കാനും അദ്ദേഹം ഇഷ്ടപ്പെടുന്നു. താൻ പ്രവർത്തിക്കുന്ന സമൂഹത്തിന്റെ ദൈനംദിന ജീവിതവുമായി ബന്ധം നിലനിർത്താൻ ഈ സാധാരണ താൽപര്യങ്ങൾ സഹായിക്കുന്നുവെന്ന് അദ്ദേഹം കരുതുന്നു.

28-ാം വയസ്സിൽ അർജുന്റെ പൊതുയാത്ര ഇപ്പോഴും വളർന്നുകൊണ്ടിരിക്കുകയാണ്. യുവാക്കൾക്ക് പ്രാദേശിക ഭരണകൂടം അകന്നൊരു സ്ഥാപനമല്ലെന്ന ബോധം നൽകാനുള്ള ശ്രമമാണ് അതിന്റെ പ്രത്യേകത. ഒരു പ്രശ്നമുണ്ടാകുമ്പോൾ മാത്രം സമീപിക്കുന്ന സ്ഥാപനമല്ല, ആളുകൾക്ക് പങ്കാളികളാകാൻ കഴിയുന്ന സ്വന്തം ഇടമാണ് പഞ്ചായത്ത് എന്ന ധാരണ വളർത്തുകയാണ് അദ്ദേഹത്തിന്റെ ലക്ഷ്യം.',
        ],
    ],

    'nanditha.das' => [
        'name' => 'Nanditha Das',
        'tier' => 'distinguished',
        'profession' => 'Community Organiser and Youth Development Advocate',
        'portrait' => 'nanditha_das.jpg',
        'en' => [
            'title' => 'Nanditha Das',
            'summary' => 'Nanditha Das’s public work grew from an observation she made while volunteering with a community learning programme: many young people had ability and ambition, but lacked someone who could help them see what their next step might be. Her early involvement focused on informal learning and mentoring, particularly for young women who were uncertain about returning to education or beginning work.',
            'body' => 'Nanditha Das’s public work grew from an observation she made while volunteering with a community learning programme: many young people had ability and ambition, but lacked someone who could help them see what their next step might be. Her early involvement focused on informal learning and mentoring, particularly for young women who were uncertain about returning to education or beginning work.

She helped organise small study and skills groups in which participants could ask practical questions about education, training and early employment. Over time, the work expanded beyond tutoring. Nanditha became interested in the circumstances that determine whether an opportunity is actually usable — travel, family responsibilities, confidence, access to information and the ability to ask for help.

A turning point came when a programme that appeared successful on paper began losing participants. Rather than treating the drop as lack of interest, she spoke to the young people and their families. The reasons were ordinary but important: timings, transport and uncertainty about what would happen after the initial programme. The experience changed the way she designed community initiatives.

Her work now connects young people, women’s groups and local community organisations around education and participation. She particularly encourages young women to take responsibility for small projects rather than waiting until they feel completely prepared.

Nanditha’s own interests include reading, mentoring younger students and spending time with community groups outside formal programme settings. She values conversations in which people are able to speak about uncertainty without feeling that they have failed.

At 32, she sees her work less as creating a single flagship initiative and more as building confidence and continuity. Her larger hope is that young people who receive support today will become the people who create opportunities for others tomorrow.',
        ],
        'ml' => [
            'title' => 'Nanditha Das',
            'summary' => 'നന്ദിത ദാസിന്റെ പൊതുപ്രവർത്തനം ഒരു സമൂഹപഠന പദ്ധതിയിൽ സന്നദ്ധ പ്രവർത്തകയായി പ്രവർത്തിക്കുമ്പോഴുണ്ടായ ഒരു നിരീക്ഷണത്തിൽ നിന്നാണ് വളർന്നത്. കഴിവും ആഗ്രഹവുമുള്ള നിരവധി യുവാക്കൾക്ക് അടുത്ത ചുവട് എന്താകണമെന്ന് മനസ്സിലാക്കാൻ സഹായിക്കുന്ന ഒരാൾ പലപ്പോഴും ഇല്ലായിരുന്നു. പ്രത്യേകിച്ച് പഠനം തുടരണമോ ജോലി തുടങ്ങണമോ എന്ന കാര്യത്തിൽ ആശയക്കുഴപ്പത്തിലായിരുന്ന യുവതികളുമായി അനൗപചാരിക പഠന-മാർഗനിർദ്ദേശ പ്രവർത്തനങ്ങളിലൂടെയായിരുന്നു തുടക്കം.',
            'body' => 'നന്ദിത ദാസിന്റെ പൊതുപ്രവർത്തനം ഒരു സമൂഹപഠന പദ്ധതിയിൽ സന്നദ്ധ പ്രവർത്തകയായി പ്രവർത്തിക്കുമ്പോഴുണ്ടായ ഒരു നിരീക്ഷണത്തിൽ നിന്നാണ് വളർന്നത്. കഴിവും ആഗ്രഹവുമുള്ള നിരവധി യുവാക്കൾക്ക് അടുത്ത ചുവട് എന്താകണമെന്ന് മനസ്സിലാക്കാൻ സഹായിക്കുന്ന ഒരാൾ പലപ്പോഴും ഇല്ലായിരുന്നു. പ്രത്യേകിച്ച് പഠനം തുടരണമോ ജോലി തുടങ്ങണമോ എന്ന കാര്യത്തിൽ ആശയക്കുഴപ്പത്തിലായിരുന്ന യുവതികളുമായി അനൗപചാരിക പഠന-മാർഗനിർദ്ദേശ പ്രവർത്തനങ്ങളിലൂടെയായിരുന്നു തുടക്കം.

വിദ്യാഭ്യാസം, പരിശീലനം, ആദ്യകാല തൊഴിൽ എന്നിവയെക്കുറിച്ച് പ്രായോഗികമായി സംസാരിക്കാനാകുന്ന ചെറിയ പഠന-കഴിവ് കൂട്ടായ്മകൾ അവർ സംഘടിപ്പിച്ചു. പിന്നീട് ഇത് ട്യൂഷൻ എന്ന പരിധി കടന്നു. യാത്രാസൗകര്യം, കുടുംബ ഉത്തരവാദിത്വങ്ങൾ, ആത്മവിശ്വാസം, വിവരലഭ്യത, സഹായം ചോദിക്കാനുള്ള മനസ്സുറപ്പ് തുടങ്ങിയ കാര്യങ്ങളാണ് ഒരു അവസരം യഥാർത്ഥത്തിൽ ഉപയോഗപ്പെടുത്താനാകുമോ എന്ന് നിർണയിക്കുന്നതെന്ന് നന്ദിത തിരിച്ചറിഞ്ഞു.

കടലാസിൽ വിജയകരമായി തോന്നിയ ഒരു പരിപാടിയിൽ പങ്കാളിത്തം കുറഞ്ഞത് ഒരു വഴിത്തിരിവായി. അതിനെ താൽപര്യമില്ലായ്മയായി കാണാതെ യുവാക്കളോടും അവരുടെ കുടുംബങ്ങളോടും അവർ സംസാരിച്ചു. സമയക്രമം, യാത്ര, പരിപാടിക്ക് ശേഷം എന്ത് സംഭവിക്കും എന്നതിലുള്ള അനിശ്ചിതത്വം എന്നിവയായിരുന്നു പ്രധാന കാരണങ്ങൾ. സമൂഹപദ്ധതികൾ രൂപകൽപ്പന ചെയ്യുന്ന രീതിയെ ഈ അനുഭവം മാറ്റി.

ഇന്ന് വിദ്യാഭ്യാസവും പങ്കാളിത്തവും കേന്ദ്രീകരിച്ച് യുവാക്കളെയും വനിതാ കൂട്ടായ്മകളെയും പ്രാദേശിക സംഘടനകളെയും ബന്ധിപ്പിക്കുന്ന പ്രവർത്തനങ്ങളിലാണ് നന്ദിത. എല്ലാം പൂർണ്ണമായി തയ്യാറായതിന് ശേഷം മാത്രമേ മുന്നോട്ട് വരൂ എന്നതിനു പകരം ചെറിയ ഉത്തരവാദിത്വങ്ങൾ ഏറ്റെടുക്കാൻ യുവതികളെ അവർ പ്രോത്സാഹിപ്പിക്കുന്നു.

വായന, ഇളയ വിദ്യാർത്ഥികൾക്ക് മാർഗനിർദ്ദേശം നൽകൽ, ഔപചാരിക പരിപാടികൾക്കു പുറത്തുള്ള സമൂഹസംഘങ്ങളോടൊപ്പം സമയം ചെലവഴിക്കൽ എന്നിവയാണ് നന്ദിതയുടെ താൽപര്യങ്ങൾ. പരാജയപ്പെട്ടുവെന്ന തോന്നലില്ലാതെ സ്വന്തം ആശയക്കുഴപ്പങ്ങളെക്കുറിച്ച് സംസാരിക്കാൻ കഴിയുന്ന സംഭാഷണങ്ങളെയാണ് അവർ വിലമതിക്കുന്നത്.

32-ാം വയസ്സിൽ ഒരു വലിയ ഒറ്റ പദ്ധതിയേക്കാൾ ആത്മവിശ്വാസവും തുടർച്ചയും വളർത്തുന്നതിലാണ് നന്ദിതയുടെ ശ്രദ്ധ. ഇന്ന് പിന്തുണ ലഭിക്കുന്ന യുവാക്കൾ നാളെ മറ്റുള്ളവർക്ക് അവസരങ്ങൾ സൃഷ്ടിക്കുന്നവരാകണമെന്നാണ് അവരുടെ പ്രതീക്ഷ.',
        ],
    ],

    'fahim.yousuf' => [
        'name' => 'Fahim Yousuf',
        'tier' => 'distinguished',
        'profession' => 'Workers’ and Cooperative Development Organiser',
        'portrait' => 'fahim_yousuf.jpg',
        'en' => [
            'title' => 'Fahim Yousuf',
            'summary' => 'Fahim Yousuf’s public involvement began through conversations with workers and small livelihood groups who were dealing with problems that were difficult to solve individually. He became interested in the role of cooperatives because they offered a way for people to combine information, bargaining strength and responsibility without losing local ownership.',
            'body' => 'Fahim Yousuf’s public involvement began through conversations with workers and small livelihood groups who were dealing with problems that were difficult to solve individually. He became interested in the role of cooperatives because they offered a way for people to combine information, bargaining strength and responsibility without losing local ownership.

His early work brought him into contact with workers’ groups and fisherfolk communities. He helped members understand procedures, keep basic records and raise common concerns through the appropriate organisations. What interested him was not only the immediate issue but the question of why the same difficulty kept returning.

A memorable experience involved a group of young workers who had reached a point where they were unsure whether to continue working individually or join a cooperative initiative. The discussions were slow and not everyone agreed. Fahim learned that building a collective organisation requires patience because people are not simply deciding on a structure; they are deciding whether they can trust one another enough to share responsibility.

That experience shaped his later work. He has supported cooperative discussions around livelihoods, access to services and practical training, while encouraging younger members to take on organisational responsibilities themselves.

Fahim is also interested in the changing nature of work and the pressure younger workers face when traditional livelihood structures are under strain. He believes local institutions need to adapt without losing the relationships that make them useful.

At 29, his public journey is still young, but it already reflects a clear preference for collective solutions. His contribution is grounded in the idea that stronger communities are built when people understand both their rights and their responsibilities to one another.',
        ],
        'ml' => [
            'title' => 'Fahim Yousuf',
            'summary' => 'തനിച്ച് പരിഹരിക്കാൻ ബുദ്ധിമുട്ടുള്ള പ്രശ്നങ്ങൾ നേരിടുന്ന തൊഴിലാളികളുമായും ചെറുകിട ഉപജീവന കൂട്ടായ്മകളുമായും നടത്തിയ സംഭാഷണങ്ങളിലൂടെയാണ് ഫഹീം യൂസഫിന്റെ പൊതുപ്രവർത്തനം ആരംഭിച്ചത്. വിവരവും കൂട്ടായ ചർച്ചാശേഷിയും ഉത്തരവാദിത്വവും ഒരുമിച്ച് ഉപയോഗിക്കാനും പ്രാദേശിക ഉടമസ്ഥത നിലനിർത്താനും സഹകരണ സംഘങ്ങൾക്ക് കഴിയുമെന്നതാണ് അദ്ദേഹത്തെ ആകർഷിച്ചത്.',
            'body' => 'തനിച്ച് പരിഹരിക്കാൻ ബുദ്ധിമുട്ടുള്ള പ്രശ്നങ്ങൾ നേരിടുന്ന തൊഴിലാളികളുമായും ചെറുകിട ഉപജീവന കൂട്ടായ്മകളുമായും നടത്തിയ സംഭാഷണങ്ങളിലൂടെയാണ് ഫഹീം യൂസഫിന്റെ പൊതുപ്രവർത്തനം ആരംഭിച്ചത്. വിവരവും കൂട്ടായ ചർച്ചാശേഷിയും ഉത്തരവാദിത്വവും ഒരുമിച്ച് ഉപയോഗിക്കാനും പ്രാദേശിക ഉടമസ്ഥത നിലനിർത്താനും സഹകരണ സംഘങ്ങൾക്ക് കഴിയുമെന്നതാണ് അദ്ദേഹത്തെ ആകർഷിച്ചത്.

തൊഴിലാളി കൂട്ടായ്മകളുമായും മത്സ്യത്തൊഴിലാളി സമൂഹങ്ങളുമായും പ്രവർത്തിച്ചുകൊണ്ടാണ് അദ്ദേഹത്തിന്റെ ആദ്യകാല അനുഭവങ്ങൾ രൂപപ്പെട്ടത്. നടപടിക്രമങ്ങൾ മനസ്സിലാക്കാനും അടിസ്ഥാന രേഖകൾ സൂക്ഷിക്കാനും പൊതുവായ പ്രശ്നങ്ങൾ ബന്ധപ്പെട്ട സ്ഥാപനങ്ങളിലൂടെ ഉന്നയിക്കാനും അദ്ദേഹം അംഗങ്ങളെ സഹായിച്ചു. ഒരു പ്രശ്നം ഉടൻ എങ്ങനെ പരിഹരിക്കാം എന്നതിലുപരി അതേ ബുദ്ധിമുട്ട് വീണ്ടും വീണ്ടും ഉണ്ടാകുന്നതെന്തുകൊണ്ടാണെന്ന ചോദ്യമാണ് അദ്ദേഹത്തെ കൂടുതൽ ആകർഷിച്ചത്.

സ്വതന്ത്രമായി ജോലി തുടരണമോ സഹകരണ സംരംഭത്തിന്റെ ഭാഗമാകണമോ എന്ന കാര്യത്തിൽ ആശയക്കുഴപ്പത്തിലായിരുന്ന ഒരു കൂട്ടം യുവ തൊഴിലാളികളുമായുള്ള അനുഭവം അദ്ദേഹത്തിന് പ്രധാനപ്പെട്ടതായി. ചർച്ചകൾ പതുക്കെയായിരുന്നു; എല്ലാവരും ഒരേ അഭിപ്രായത്തിലായിരുന്നില്ല. ഒരു കൂട്ടായ്മ സൃഷ്ടിക്കുന്നത് ഒരു ഘടന തിരഞ്ഞെടുക്കുന്നതു മാത്രമല്ല, പരസ്പരം വിശ്വസിച്ച് ഉത്തരവാദിത്വം പങ്കിടാൻ കഴിയുമോ എന്ന തീരുമാനവുമാണെന്ന് ഫഹീം തിരിച്ചറിഞ്ഞു.

ഈ അനുഭവം പിന്നീട് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തെ സ്വാധീനിച്ചു. ഉപജീവനം, സേവനങ്ങളിലേക്കുള്ള പ്രവേശനം, പ്രായോഗിക പരിശീലനം തുടങ്ങിയ വിഷയങ്ങളിൽ സഹകരണ ചർച്ചകൾക്ക് അദ്ദേഹം പിന്തുണ നൽകി. അതോടൊപ്പം യുവ അംഗങ്ങൾ തന്നെ സംഘടനാപരമായ ഉത്തരവാദിത്വങ്ങൾ ഏറ്റെടുക്കണമെന്നും അദ്ദേഹം പ്രോത്സാഹിപ്പിച്ചു.

പരമ്പരാഗത ഉപജീവനരീതികൾ സമ്മർദ്ദം നേരിടുന്ന സാഹചര്യത്തിൽ ജോലി ചെയ്യുന്ന യുവാക്കളുടെ മാറുന്ന ആവശ്യങ്ങളിലും ഫഹീമിന് താൽപര്യമുണ്ട്. ജനങ്ങളുമായുള്ള ബന്ധമാണ് പ്രാദേശിക സ്ഥാപനങ്ങളുടെ ശക്തിയെങ്കിൽ, ആ ബന്ധം നഷ്ടപ്പെടുത്താതെ സ്ഥാപനങ്ങൾ മാറേണ്ടതുണ്ടെന്നാണ് അദ്ദേഹത്തിന്റെ നിലപാട്.

29-ാം വയസ്സിൽ ഫഹീമിന്റെ പൊതുയാത്ര ഇപ്പോഴും തുടക്കഘട്ടത്തിലാണ്. എന്നാൽ കൂട്ടായ പരിഹാരങ്ങളോടുള്ള അദ്ദേഹത്തിന്റെ സമീപനം ഇതിനകം വ്യക്തമാണ്. അവകാശങ്ങളെക്കുറിച്ചുള്ള ബോധത്തോടൊപ്പം പരസ്പര ഉത്തരവാദിത്വത്തെക്കുറിച്ചുള്ള ബോധവും ശക്തമായ സമൂഹങ്ങൾക്ക് ആവശ്യമാണെന്ന ആശയത്തിലാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനം.',
        ],
    ],

    'meera.krishnan' => [
        'name' => 'Meera Krishnan',
        'tier' => 'distinguished',
        'profession' => 'Community Arts Organiser and Cultural Participation Advocate',
        'portrait' => 'meera_krishnan.jpg',
        'en' => [
            'title' => 'Meera Krishnan',
            'summary' => 'Meera Krishnan entered public life through the arts. As a young participant in community cultural programmes, she became increasingly interested in the people who wanted to take part but did not feel that existing spaces were meant for them. Her work gradually moved from performance and event participation towards community organising.',
            'body' => 'Meera Krishnan entered public life through the arts. As a young participant in community cultural programmes, she became increasingly interested in the people who wanted to take part but did not feel that existing spaces were meant for them. Her work gradually moved from performance and event participation towards community organising.

She began helping local groups create small arts programmes for young people, including workshops and informal public events. The experience taught her that cultural participation depends on practical details: who feels welcome, who can afford to attend, whether the timing works for students and workers, and whether people are given a role beyond being an audience.

One turning point came after a programme attracted a diverse group of young participants but the group quickly became fragmented. Meera chose to speak with them rather than simply plan another event. Some wanted performance opportunities, others wanted to learn production skills, and some were looking for a social space where they could meet people outside their usual circles. The experience led her to think of cultural programmes as community infrastructure rather than one-off events.

Her work now brings together young artists, volunteers and neighbourhood organisations around arts, culture and community engagement. She is particularly interested in giving young people responsibility for organising, documenting and presenting activities, not only appearing on stage.

Away from organised programmes, Meera enjoys photography, contemporary music and exploring local cultural spaces. She sees these interests as part of the same curiosity that drew her into community work.

At 34, her public contribution is rooted in a simple idea: art can give people a reason to meet, but good community spaces can help them remain connected afterwards. Her ambition is to build more such spaces where younger people feel both welcome and capable of shaping what happens around them.',
        ],
        'ml' => [
            'title' => 'Meera Krishnan',
            'summary' => 'മീര കൃഷ്ണൻ കലാരംഗത്തിലൂടെയാണ് പൊതുജീവിതത്തിലേക്ക് എത്തിയത്. ചെറുപ്പത്തിൽ സമൂഹാധിഷ്ഠിത സാംസ്കാരിക പരിപാടികളിൽ പങ്കെടുത്തിരുന്നപ്പോൾ, പങ്കെടുക്കാൻ ആഗ്രഹിച്ചിട്ടും നിലവിലുള്ള ഇടങ്ങൾ തങ്ങൾക്ക് വേണ്ടിയുള്ളതല്ലെന്ന് തോന്നുന്ന നിരവധി ആളുകളെ അവർ ശ്രദ്ധിച്ചു. പരിപാടികളിലെ പങ്കാളിത്തത്തിൽ നിന്ന് സമൂഹസംഘടനയിലേക്കാണ് അവരുടെ പ്രവർത്തനം പിന്നീട് വളർന്നത്.',
            'body' => 'മീര കൃഷ്ണൻ കലാരംഗത്തിലൂടെയാണ് പൊതുജീവിതത്തിലേക്ക് എത്തിയത്. ചെറുപ്പത്തിൽ സമൂഹാധിഷ്ഠിത സാംസ്കാരിക പരിപാടികളിൽ പങ്കെടുത്തിരുന്നപ്പോൾ, പങ്കെടുക്കാൻ ആഗ്രഹിച്ചിട്ടും നിലവിലുള്ള ഇടങ്ങൾ തങ്ങൾക്ക് വേണ്ടിയുള്ളതല്ലെന്ന് തോന്നുന്ന നിരവധി ആളുകളെ അവർ ശ്രദ്ധിച്ചു. പരിപാടികളിലെ പങ്കാളിത്തത്തിൽ നിന്ന് സമൂഹസംഘടനയിലേക്കാണ് അവരുടെ പ്രവർത്തനം പിന്നീട് വളർന്നത്.

യുവാക്കൾക്കായി ചെറിയ കലാപരിപാടികളും വർക്ക്‌ഷോപ്പുകളും അനൗപചാരിക പൊതുപരിപാടികളും സംഘടിപ്പിക്കാൻ അവർ പ്രാദേശിക കൂട്ടായ്മകളെ സഹായിച്ചു. ഒരു സാംസ്കാരിക പരിപാടിയിൽ പങ്കെടുക്കാൻ ആളുകൾക്ക് യഥാർത്ഥത്തിൽ കഴിയുമോ എന്നത് പരിപാടിയുടെ ഉള്ളടക്കത്തിന് പുറമേ പല കാര്യങ്ങളെയും ആശ്രയിക്കുന്നുവെന്ന് ഈ അനുഭവം പഠിപ്പിച്ചു — ആരാണ് സ്വാഗതം ചെയ്യപ്പെടുന്നത്, ചെലവ് വഹിക്കാനാകുമോ, സമയം വിദ്യാർത്ഥികൾക്കും തൊഴിലാളികൾക്കും അനുയോജ്യമാണോ, പ്രേക്ഷകരാകുന്നതിന് പുറമെ ആളുകൾക്ക് ഒരു പങ്ക് ലഭിക്കുന്നുണ്ടോ തുടങ്ങിയവ.

വിവിധ പശ്ചാത്തലങ്ങളിൽ നിന്നുള്ള യുവാക്കൾ പങ്കെടുത്ത ഒരു പരിപാടിക്ക് ശേഷം സംഘം പെട്ടെന്ന് ചിതറിപ്പോയത് ഒരു വഴിത്തിരിവായി. മറ്റൊരു പരിപാടി ആസൂത്രണം ചെയ്യുന്നതിനു പകരം മീര അവരുമായി സംസാരിച്ചു. ചിലർക്ക് വേദി വേണമെന്നുണ്ടായിരുന്നു; ചിലർക്ക് പരിപാടി സംഘടിപ്പിക്കുന്നതിന്റെ പിന്നാമ്പുറ കഴിവുകൾ പഠിക്കണമെന്നായിരുന്നു ആഗ്രഹം; മറ്റുചിലർ പതിവ് സാമൂഹിക വലയത്തിന് പുറത്തുള്ള ആളുകളെ കാണാൻ ഒരു ഇടം തേടുകയായിരുന്നു. സാംസ്കാരിക പരിപാടികളെ ഒറ്റത്തവണ നടക്കുന്ന പരിപാടികളായി മാത്രമല്ല, സമൂഹത്തിന്റെ അടിസ്ഥാന ഇടങ്ങളായി കാണാൻ ഈ അനുഭവം മീരയെ പ്രേരിപ്പിച്ചു.

ഇന്ന് കല, സംസ്കാരം, സമൂഹപങ്കാളിത്തം എന്നിവ ചുറ്റിപ്പറ്റി യുവ കലാകാരന്മാരെയും സന്നദ്ധ പ്രവർത്തകരെയും അയൽക്കൂട്ട സംഘടനകളെയും ബന്ധിപ്പിക്കുന്ന പ്രവർത്തനങ്ങളിലാണ് അവർ. വേദിയിൽ പ്രത്യക്ഷപ്പെടുന്നതിന് മാത്രമല്ല, പരിപാടികൾ സംഘടിപ്പിക്കാനും രേഖപ്പെടുത്താനും അവതരിപ്പിക്കാനും യുവാക്കൾക്ക് ഉത്തരവാദിത്വം നൽകുന്നതിലാണ് പ്രത്യേക ശ്രദ്ധ.

ഫോട്ടോഗ്രഫി, സമകാലിക സംഗീതം, പ്രാദേശിക സാംസ്കാരിക ഇടങ്ങൾ പരിചയപ്പെടൽ എന്നിവയാണ് മീരയുടെ താൽപര്യങ്ങൾ. സമൂഹപ്രവർത്തനത്തിലേക്ക് തന്നെ നയിച്ച കൗതുകത്തിന്റെ തുടർച്ചയായാണ് അവർ ഈ താൽപര്യങ്ങളെയും കാണുന്നത്.

34-ാം വയസ്സിൽ മീരയുടെ പൊതുപ്രവർത്തനം ഒരു ലളിതമായ ആശയത്തിൽ അധിഷ്ഠിതമാണ്: ആളുകൾക്ക് ഒത്തുചേരാൻ കല ഒരു കാരണം നൽകാം; എന്നാൽ നല്ല സമൂഹ ഇടങ്ങൾ ആ ബന്ധം തുടരാൻ സഹായിക്കും. യുവാക്കൾക്ക് സ്വാഗതം ചെയ്യപ്പെടുന്നതോടൊപ്പം തങ്ങൾക്കുചുറ്റും നടക്കുന്ന കാര്യങ്ങളെ രൂപപ്പെടുത്താൻ കഴിവുള്ളവരാണെന്ന ബോധവും നൽകുന്ന കൂടുതൽ ഇടങ്ങൾ സൃഷ്ടിക്കുകയാണ് അവരുടെ ലക്ഷ്യം.',
        ],
    ],
];
