<?php

/*
| Demonstration-profile content — verbatim from the corrected demonstration
| corpus (Jannayaks_Demo_Corpus_CORRECTED.zip, "Rebuilt Demonstration
| Corpus — v2"). This file replaces the earlier 12-profile package.
|
| 14 living demonstrations: 3 Recognised, 4 Acclaimed, 7 Distinguished.
| All are fictional demonstration personas.
|
| Each entry: name, tier (internal key), profession (the corpus
| "Role / theme" line), EN + ML title/summary/body, and the approved
| portrait filename in database/seeders/demo-media/. The summary is the
| first paragraph of the supplied narrative; the body is the complete
| supplied narrative (headings, metadata lines and the closing
| demonstration footer are omitted — the page shows its own notice).
|
| The three Distinguished entries (t.gopalakrishnan, p.sreedharan,
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
    'fr.joseph.mathew' => [
        'name' => 'Fr. Joseph Mathew',
        'tier' => 'emerging',
        'profession' => 'Spiritual Leader and Social Outreach Advocate',
        'portrait' => 'fr_joseph_mathew.jpg',
        'en' => [
            'title' => 'Fr. Joseph Mathew',
            'summary' => 'Fr. Joseph Mathew’s public life combines pastoral leadership with practical community outreach. Alongside his religious responsibilities, he has encouraged volunteers, families and local organisations to work together around social needs and to treat service as a continuing responsibility rather than an occasional activity.',
            'body' => 'Fr. Joseph Mathew’s public life combines pastoral leadership with practical community outreach. Alongside his religious responsibilities, he has encouraged volunteers, families and local organisations to work together around social needs and to treat service as a continuing responsibility rather than an occasional activity.

His community involvement has included support for vulnerable families, educational assistance and local programmes intended to respond to practical needs. He has also encouraged younger volunteers to participate in service initiatives, giving them opportunities to work directly with families and community groups rather than limiting service to formal occasions.

A recurring feature of his approach is the effort to connect people who can help with people who need support. Families, volunteers and local organisations each have different capacities, and he has encouraged them to work together around clearly identified needs. In this sense, his contribution is less about a single project and more about strengthening the habit of community service.

He also places importance on the relationship between institutions and the people they serve. In his view, institutions become stronger when they remain close to ordinary families and when those involved in service are willing to listen before deciding what kind of help is appropriate.

His work reflects a form of local leadership that is built through presence, trust and repeated engagement. By encouraging volunteers and community groups to share responsibility, he has sought to make service something that can continue through the participation of many people.

For Fr. Joseph, leadership is therefore closely connected with responsibility: the value of an institution is ultimately measured by how faithfully it remains connected to the people it exists to serve.',
        ],
        'ml' => [
            'title' => 'Fr. Joseph Mathew',
            'summary' => 'ഫാ. ജോസഫ് മാത്യുവിന്റെ പൊതുപ്രവർത്തനത്തിൽ ആത്മീയ നേതൃത്വവും പ്രായോഗികമായ സാമൂഹിക ഇടപെടലുകളും ഒരുമിക്കുന്നു. മതപരമായ ഉത്തരവാദിത്വങ്ങൾക്കൊപ്പം സന്നദ്ധപ്രവർത്തകരെയും കുടുംബങ്ങളെയും പ്രാദേശിക സംഘടനകളെയും സാമൂഹിക ആവശ്യങ്ങൾക്കായി ഒരുമിച്ച് പ്രവർത്തിക്കാൻ അദ്ദേഹം പ്രോത്സാഹിപ്പിച്ചു. സേവനം ഇടയ്ക്കിടെ നടക്കുന്ന ഒരു പ്രവർത്തനം മാത്രമല്ല, തുടർച്ചയായ ഉത്തരവാദിത്തമാണെന്ന സമീപനമാണ് അദ്ദേഹത്തിന്റേത്.',
            'body' => 'ഫാ. ജോസഫ് മാത്യുവിന്റെ പൊതുപ്രവർത്തനത്തിൽ ആത്മീയ നേതൃത്വവും പ്രായോഗികമായ സാമൂഹിക ഇടപെടലുകളും ഒരുമിക്കുന്നു. മതപരമായ ഉത്തരവാദിത്വങ്ങൾക്കൊപ്പം സന്നദ്ധപ്രവർത്തകരെയും കുടുംബങ്ങളെയും പ്രാദേശിക സംഘടനകളെയും സാമൂഹിക ആവശ്യങ്ങൾക്കായി ഒരുമിച്ച് പ്രവർത്തിക്കാൻ അദ്ദേഹം പ്രോത്സാഹിപ്പിച്ചു. സേവനം ഇടയ്ക്കിടെ നടക്കുന്ന ഒരു പ്രവർത്തനം മാത്രമല്ല, തുടർച്ചയായ ഉത്തരവാദിത്തമാണെന്ന സമീപനമാണ് അദ്ദേഹത്തിന്റേത്.

പിന്തുണ ആവശ്യമുള്ള കുടുംബങ്ങൾക്കുള്ള സഹായം, വിദ്യാഭ്യാസ പിന്തുണ, പ്രാദേശിക സമൂഹപരിപാടികൾ എന്നിവ അദ്ദേഹത്തിന്റെ പ്രവർത്തനങ്ങളിൽ ഉൾപ്പെടുന്നു. യുവ സന്നദ്ധപ്രവർത്തകരെയും സേവന പ്രവർത്തനങ്ങളിൽ സജീവമായി പങ്കെടുപ്പിക്കാൻ അദ്ദേഹം ശ്രദ്ധിച്ചു. ഔപചാരിക പരിപാടികളിൽ മാത്രം ഒതുങ്ങാതെ കുടുംബങ്ങളുമായും സമൂഹസംഘങ്ങളുമായും നേരിട്ട് ഇടപെടാനുള്ള അവസരമാണ് അദ്ദേഹം അവർക്ക് നൽകാൻ ശ്രമിച്ചത്.

സഹായിക്കാൻ കഴിയുന്ന ആളുകളെയും സഹായം ആവശ്യമുള്ളവരെയും തമ്മിൽ ബന്ധിപ്പിക്കുക എന്നത് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിലെ പ്രധാന സവിശേഷതയാണ്. കുടുംബങ്ങൾക്കും സന്നദ്ധപ്രവർത്തകർക്കും പ്രാദേശിക സംഘടനകൾക്കും വ്യത്യസ്ത ശേഷികളുണ്ട്. വ്യക്തമായി തിരിച്ചറിഞ്ഞ ആവശ്യങ്ങളെ ചുറ്റിപ്പറ്റി അവരെ ഒരുമിപ്പിക്കുകയാണ് അദ്ദേഹത്തിന്റെ സമീപനം. അതുകൊണ്ടുതന്നെ ഒരു പ്രത്യേക പദ്ധതിയേക്കാൾ സമൂഹസേവനത്തിന്റെ ഒരു സ്ഥിരമായ ശീലം ശക്തിപ്പെടുത്തുന്നതിലാണ് അദ്ദേഹത്തിന്റെ സംഭാവന.

സ്ഥാപനങ്ങളും അവ സേവിക്കുന്ന ജനങ്ങളും തമ്മിലുള്ള ബന്ധത്തിനും അദ്ദേഹം പ്രാധാന്യം നൽകി. സാധാരണ കുടുംബങ്ങളോട് അടുത്തുനിൽക്കുകയും ആദ്യം അവരുടെ ആവശ്യങ്ങൾ കേൾക്കുകയും ചെയ്ത ശേഷമാണ് സഹായത്തിന്റെ രൂപം തീരുമാനിക്കേണ്ടതെന്ന് അദ്ദേഹം കരുതുന്നു.

സാന്നിധ്യം, വിശ്വാസം, തുടർച്ചയായ ഇടപെടൽ എന്നിവയിലൂടെ രൂപപ്പെടുന്ന പ്രാദേശിക നേതൃത്വത്തിന്റെ ഒരു മാതൃകയാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനം. സന്നദ്ധപ്രവർത്തകരും സമൂഹസംഘങ്ങളും ഉത്തരവാദിത്തം പങ്കിടുന്നതിലൂടെ സേവനപ്രവർത്തനങ്ങൾ പലരുടെയും പങ്കാളിത്തത്തോടെ തുടരാനാകണമെന്നാണ് അദ്ദേഹത്തിന്റെ ശ്രമം.

ഫാ. ജോസഫിന്റെ കാഴ്ചപ്പാടിൽ നേതൃത്വം ഉത്തരവാദിത്തവുമായി അടുത്ത് ബന്ധപ്പെട്ടതാണ്. ഒരു സ്ഥാപനം നിലനിൽക്കുന്ന ജനങ്ങളോട് എത്ര വിശ്വസ്തമായി അടുത്തുനിൽക്കുന്നു എന്നതാണ് അതിന്റെ യഥാർത്ഥ മൂല്യം നിർണ്ണയിക്കുന്നത്.',
        ],
    ],

    'p.rajeev.menon' => [
        'name' => 'P. Rajeev Menon',
        'tier' => 'emerging',
        'profession' => 'Panchayat Member and Local Development Coordinator',
        'portrait' => 'p_rajeev_menon.jpg',
        'en' => [
            'title' => 'P. Rajeev Menon',
            'summary' => 'P. Rajeev Menon’s public life has grown from the practical concerns of the village in which he lives. As a panchayat member and local development coordinator, he has been closely involved with issues that rarely attract attention beyond the neighbourhood itself: drinking-water access, maintenance of public spaces, drainage, street lighting and the everyday difficulties faced by older residents and families.',
            'body' => 'P. Rajeev Menon’s public life has grown from the practical concerns of the village in which he lives. As a panchayat member and local development coordinator, he has been closely involved with issues that rarely attract attention beyond the neighbourhood itself: drinking-water access, maintenance of public spaces, drainage, street lighting and the everyday difficulties faced by older residents and families.

Rajeev began becoming involved in local affairs through residents’ meetings and small community initiatives. He found that many concerns were repeatedly discussed but did not always reach the appropriate local office in a form that could be acted upon. He therefore developed a simple habit of bringing together the people affected by an issue, recording the details and helping them prepare a common representation.

One of the areas in which he became particularly active was the maintenance of small public facilities. Rather than treating each complaint as an isolated matter, he encouraged residents to identify recurring problems and agree on priorities. This approach helped bring attention to minor roads, drainage points, public taps and neighbourhood spaces used by children and older people.

Rajeev has also encouraged younger residents to participate in local activities. He believes that community leadership should not depend entirely on elected representatives. Residents who understand how local institutions function, how applications are made and how public work is followed up can gradually take responsibility for their own neighbourhoods.

His style of leadership is deliberately practical and accessible. He is less interested in making large promises than in helping people understand what can realistically be taken forward, who is responsible and what information is required. For Rajeev, a useful public representative is one who remains available after a meeting is over and follows an issue until there is a clear response.

His contribution reflects the quieter side of local public life: the steady work of listening, organising and helping ordinary residents participate more effectively in the institutions closest to them.',
        ],
        'ml' => [
            'title' => 'P. Rajeev Menon',
            'summary' => 'പി. രാജീവ് മേനോന്റെ പൊതുപ്രവർത്തനം അദ്ദേഹം ജീവിക്കുന്ന ഗ്രാമത്തിലെ ദൈനംദിന ആവശ്യങ്ങളിൽ നിന്നാണ് വളർന്നത്. പഞ്ചായത്തംഗമായും പ്രാദേശിക വികസന പ്രവർത്തനങ്ങളുമായി ബന്ധപ്പെട്ടും പ്രവർത്തിക്കുന്ന അദ്ദേഹം കുടിവെള്ള ലഭ്യത, പൊതുസ്ഥലങ്ങളുടെ പരിപാലനം, ഡ്രെയിനേജ്, തെരുവുവിളക്കുകൾ, വയോധികർക്കും കുടുംബങ്ങൾക്കും നേരിടുന്ന ദൈനംദിന ബുദ്ധിമുട്ടുകൾ തുടങ്ങിയ വിഷയങ്ങളിൽ ഇടപെട്ടിട്ടുണ്ട്.',
            'body' => 'പി. രാജീവ് മേനോന്റെ പൊതുപ്രവർത്തനം അദ്ദേഹം ജീവിക്കുന്ന ഗ്രാമത്തിലെ ദൈനംദിന ആവശ്യങ്ങളിൽ നിന്നാണ് വളർന്നത്. പഞ്ചായത്തംഗമായും പ്രാദേശിക വികസന പ്രവർത്തനങ്ങളുമായി ബന്ധപ്പെട്ടും പ്രവർത്തിക്കുന്ന അദ്ദേഹം കുടിവെള്ള ലഭ്യത, പൊതുസ്ഥലങ്ങളുടെ പരിപാലനം, ഡ്രെയിനേജ്, തെരുവുവിളക്കുകൾ, വയോധികർക്കും കുടുംബങ്ങൾക്കും നേരിടുന്ന ദൈനംദിന ബുദ്ധിമുട്ടുകൾ തുടങ്ങിയ വിഷയങ്ങളിൽ ഇടപെട്ടിട്ടുണ്ട്.

നാട്ടുകാരുടെ യോഗങ്ങളിലൂടെയും ചെറിയ സാമൂഹിക സംരംഭങ്ങളിലൂടെയുമാണ് രാജീവ് പ്രാദേശിക പൊതുപ്രവർത്തനത്തിലേക്ക് കൂടുതൽ സജീവമായി എത്തിയത്. പല പ്രശ്നങ്ങളും ആവർത്തിച്ച് ചർച്ച ചെയ്യപ്പെടുന്നുണ്ടെങ്കിലും അവ പരിഹരിക്കാൻ കഴിയുന്ന തരത്തിൽ ബന്ധപ്പെട്ട ഓഫീസുകളിലെത്താറില്ലെന്ന് അദ്ദേഹം ശ്രദ്ധിച്ചു. അതിനാൽ പ്രശ്നം ബാധിക്കുന്ന ആളുകളെ ഒരുമിപ്പിക്കുകയും ആവശ്യമായ വിവരങ്ങൾ രേഖപ്പെടുത്തുകയും കൂട്ടായ നിവേദനം തയ്യാറാക്കാൻ സഹായിക്കുകയും ചെയ്യുന്നത് അദ്ദേഹത്തിന്റെ പ്രവർത്തനരീതിയായി.

ചെറിയ പൊതുസൗകര്യങ്ങളുടെ പരിപാലനവുമായി ബന്ധപ്പെട്ട വിഷയങ്ങളിലും അദ്ദേഹം ശ്രദ്ധ നൽകി. ഓരോ പരാതിയെയും വേർതിരിച്ച് കാണുന്നതിനുപകരം ആവർത്തിച്ച് ഉയരുന്ന പ്രശ്നങ്ങൾ കണ്ടെത്തി മുൻഗണന നിശ്ചയിക്കാൻ നാട്ടുകാരെ പ്രോത്സാഹിപ്പിച്ചു. ചെറുറോഡുകൾ, വെള്ളം കെട്ടിനിൽക്കുന്ന ഇടങ്ങൾ, പൊതുടാപ്പുകൾ, കുട്ടികളും വയോധികരും ഉപയോഗിക്കുന്ന പൊതുസ്ഥലങ്ങൾ തുടങ്ങിയ വിഷയങ്ങൾ ഇതിലൂടെ കൂടുതൽ ക്രമബദ്ധമായി മുന്നോട്ടുവന്നു.

പ്രാദേശിക പ്രവർത്തനങ്ങളിൽ യുവാക്കളെ ഉൾപ്പെടുത്തുന്നതിനും രാജീവ് പ്രാധാന്യം നൽകി. സമൂഹനേതൃത്വം മുഴുവൻ തിരഞ്ഞെടുക്കപ്പെട്ട പ്രതിനിധികളെ ആശ്രയിക്കേണ്ടതില്ലെന്നാണ് അദ്ദേഹത്തിന്റെ സമീപനം. പ്രാദേശിക സ്ഥാപനങ്ങൾ എങ്ങനെ പ്രവർത്തിക്കുന്നു, അപേക്ഷകൾ എങ്ങനെ നൽകണം, പൊതുപ്രവർത്തനങ്ങളുടെ പുരോഗതി എങ്ങനെ പിന്തുടരണം തുടങ്ങിയ കാര്യങ്ങൾ മനസ്സിലാക്കുന്ന നാട്ടുകാർക്ക് സ്വന്തം പ്രദേശത്തിന്റെ കാര്യങ്ങളിൽ കൂടുതൽ ഉത്തരവാദിത്തം ഏറ്റെടുക്കാനാകുമെന്ന് അദ്ദേഹം വിശ്വസിക്കുന്നു.

വലിയ വാഗ്ദാനങ്ങളേക്കാൾ പ്രായോഗികമായ ഇടപെടലുകൾക്കാണ് അദ്ദേഹത്തിന്റെ നേതൃത്വശൈലിയിൽ പ്രാധാന്യം. എന്താണ് യാഥാർത്ഥ്യമായി മുന്നോട്ടുകൊണ്ടുപോകാൻ കഴിയുന്നത്, ഉത്തരവാദിത്തം ആരുടേതാണ്, എന്ത് വിവരമാണ് ആവശ്യമായത് എന്നിവ ജനങ്ങൾക്ക് വ്യക്തമാക്കുകയാണ് അദ്ദേഹം ശ്രമിക്കുന്നത്. ഒരു പൊതുപ്രതിനിധി യോഗം കഴിഞ്ഞാലും ലഭ്യനായിരിക്കുകയും ഒരു വിഷയത്തിന് വ്യക്തമായ മറുപടി ലഭിക്കുന്നതുവരെ അത് പിന്തുടരുകയും വേണമെന്നാണ് അദ്ദേഹത്തിന്റെ കാഴ്ചപ്പാട്.

കേൾക്കുകയും ആളുകളെ ഒരുമിപ്പിക്കുകയും സാധാരണ ജനങ്ങൾക്ക് തൊട്ടടുത്തുള്ള സ്ഥാപനങ്ങളുമായി കൂടുതൽ ഫലപ്രദമായി ഇടപെടാൻ സഹായിക്കുകയും ചെയ്യുന്ന പ്രാദേശിക പൊതുജീവിതത്തിന്റെ ശാന്തമായ ഒരു മാതൃകയാണ് രാജീവ് മേനോന്റെ സംഭാവന.',
        ],
    ],

    's.beena.kumari' => [
        'name' => 'S. Beena Kumari',
        'tier' => 'emerging',
        'profession' => 'Ward Councillor and Neighbourhood Welfare Convenor',
        'portrait' => 's_beena_kumari.jpg',
        'en' => [
            'title' => 'S. Beena Kumari',
            'summary' => 'S. Beena Kumari’s community work has developed around the everyday concerns of a busy urban neighbourhood. As a ward councillor and neighbourhood welfare convenor, she has worked with residents on issues involving public cleanliness, pedestrian safety, access to local services, neighbourhood facilities and support for families who find government procedures difficult to navigate.',
            'body' => 'S. Beena Kumari’s community work has developed around the everyday concerns of a busy urban neighbourhood. As a ward councillor and neighbourhood welfare convenor, she has worked with residents on issues involving public cleanliness, pedestrian safety, access to local services, neighbourhood facilities and support for families who find government procedures difficult to navigate.

Beena became active in neighbourhood affairs through residents’ groups and women’s community initiatives. She gradually found that many households were aware of problems but were uncertain about where to take them. Her approach has been to create small, practical forums where residents can describe an issue, identify the institution responsible and agree on the next step.

She has paid particular attention to matters affecting women, older residents and families with limited time or mobility. Information sessions on public services, assistance with applications and neighbourhood-level discussions have formed part of this work. She prefers simple explanations and direct communication rather than allowing public programmes to become difficult for ordinary residents to understand.

Beena has also encouraged residents to take greater ownership of their shared spaces. Cleanliness drives, small improvements around public facilities and discussions on safer walking routes have been organised with local volunteers. She believes that local leadership becomes meaningful when people themselves are able to participate rather than simply wait for a representative to act on their behalf.

Her public style is conversational and accessible. She is comfortable listening to complaints that may initially appear minor because she sees them as indicators of how well a neighbourhood is functioning. At the same time, she tries to distinguish between matters that require formal intervention and those that can be solved through local cooperation.

For Beena, community leadership is ultimately about making public systems easier for people to approach and helping residents discover that collective action can improve the places in which they live.',
        ],
        'ml' => [
            'title' => 'S. Beena Kumari',
            'summary' => 'എസ്. ബീന കുമാരിയുടെ സാമൂഹിക പ്രവർത്തനം സജീവമായ ഒരു നഗരപ്രദേശത്തിന്റെ ദൈനംദിന ആവശ്യങ്ങളെ ചുറ്റിപ്പറ്റിയാണ് വളർന്നത്. വാർഡ് കൗൺസിലറായും അയൽക്കൂട്ട ക്ഷേമപ്രവർത്തനങ്ങളുടെ ഏകോപകയായും പൊതുശുചിത്വം, നടപ്പാത സുരക്ഷ, പ്രാദേശിക സേവനങ്ങളിലേക്കുള്ള പ്രവേശനം, പൊതുസൗകര്യങ്ങൾ, സർക്കാർ നടപടിക്രമങ്ങൾ മനസ്സിലാക്കാൻ ബുദ്ധിമുട്ടുന്ന കുടുംബങ്ങൾ തുടങ്ങിയ വിഷയങ്ങളിൽ അവർ ഇടപെട്ടിട്ടുണ്ട്.',
            'body' => 'എസ്. ബീന കുമാരിയുടെ സാമൂഹിക പ്രവർത്തനം സജീവമായ ഒരു നഗരപ്രദേശത്തിന്റെ ദൈനംദിന ആവശ്യങ്ങളെ ചുറ്റിപ്പറ്റിയാണ് വളർന്നത്. വാർഡ് കൗൺസിലറായും അയൽക്കൂട്ട ക്ഷേമപ്രവർത്തനങ്ങളുടെ ഏകോപകയായും പൊതുശുചിത്വം, നടപ്പാത സുരക്ഷ, പ്രാദേശിക സേവനങ്ങളിലേക്കുള്ള പ്രവേശനം, പൊതുസൗകര്യങ്ങൾ, സർക്കാർ നടപടിക്രമങ്ങൾ മനസ്സിലാക്കാൻ ബുദ്ധിമുട്ടുന്ന കുടുംബങ്ങൾ തുടങ്ങിയ വിഷയങ്ങളിൽ അവർ ഇടപെട്ടിട്ടുണ്ട്.

താമസക്കാരുടെ കൂട്ടായ്മകളിലൂടെയും സ്ത്രീകളുടെ സാമൂഹിക പ്രവർത്തനങ്ങളിലൂടെയുമാണ് ബീന പ്രാദേശിക വിഷയങ്ങളിൽ സജീവമായത്. പല കുടുംബങ്ങൾക്കും പ്രശ്നങ്ങൾ വ്യക്തമായി അറിയാമെങ്കിലും അവ എവിടെയാണ് ഉന്നയിക്കേണ്ടതെന്ന് വ്യക്തമല്ലെന്ന് അവർ ശ്രദ്ധിച്ചു. ഒരു പ്രശ്നം വിശദീകരിക്കാനും ഉത്തരവാദിത്തമുള്ള സ്ഥാപനത്തെ കണ്ടെത്താനും അടുത്ത ഘട്ടം തീരുമാനിക്കാനും കഴിയുന്ന ചെറിയ പ്രായോഗിക കൂട്ടായ്മകൾ രൂപപ്പെടുത്തുന്നതാണ് അവരുടെ സമീപനം.

സ്ത്രീകൾ, വയോധികർ, സമയപരിമിതിയോ സഞ്ചാരപരിമിതിയോ ഉള്ള കുടുംബങ്ങൾ എന്നിവരെ ബാധിക്കുന്ന വിഷയങ്ങൾക്ക് അവർ പ്രത്യേക ശ്രദ്ധ നൽകി. പൊതുസേവനങ്ങളെക്കുറിച്ചുള്ള വിവരസമ്മേളനങ്ങൾ, അപേക്ഷകളിൽ സഹായം, അയൽപ്രദേശതലത്തിലുള്ള ചർച്ചകൾ എന്നിവ ഈ പ്രവർത്തനത്തിന്റെ ഭാഗമായിട്ടുണ്ട്. സാധാരണ ആളുകൾക്ക് എളുപ്പത്തിൽ മനസ്സിലാകുന്ന ലളിതമായ വിശദീകരണങ്ങൾക്കും നേരിട്ടുള്ള ആശയവിനിമയത്തിനുമാണ് അവർ പ്രാധാന്യം നൽകുന്നത്.

പങ്കിട്ട പൊതുസ്ഥലങ്ങളുടെ ഉത്തരവാദിത്തം നാട്ടുകാർ തന്നെ ഏറ്റെടുക്കണമെന്നും ബീന പ്രോത്സാഹിപ്പിച്ചു. ശുചീകരണ പ്രവർത്തനങ്ങൾ, പൊതുസൗകര്യങ്ങൾക്ക് ചുറ്റുമുള്ള ചെറിയ മെച്ചപ്പെടുത്തലുകൾ, സുരക്ഷിതമായ നടപ്പാതകളെക്കുറിച്ചുള്ള ചർച്ചകൾ എന്നിവ പ്രാദേശിക സന്നദ്ധ പ്രവർത്തകരുമായി ചേർന്ന് നടത്തി. ഒരു പ്രതിനിധി എല്ലാം ചെയ്തു കൊടുക്കുന്നതിനായി കാത്തിരിക്കാതെ ആളുകൾക്ക് സ്വന്തം പ്രദേശത്തിന്റെ കാര്യങ്ങളിൽ പങ്കാളികളാകണമെന്നാണ് അവരുടെ വിശ്വാസം.

പരാതികൾ കേൾക്കുന്നതിൽ അവരുടെ പൊതുപ്രവർത്തനശൈലി സ്വാഭാവികവും സൗഹൃദപരവുമാണ്. ചെറിയതായി തോന്നുന്ന പ്രശ്നങ്ങളും ഒരു പ്രദേശം എങ്ങനെ പ്രവർത്തിക്കുന്നു എന്നതിന്റെ സൂചനകളാണെന്ന് അവർ കാണുന്നു. അതേസമയം ഔദ്യോഗിക ഇടപെടൽ ആവശ്യമായ വിഷയങ്ങളും നാട്ടുകാർക്ക് കൂട്ടായി പരിഹരിക്കാവുന്ന വിഷയങ്ങളും വേർതിരിച്ച് കാണാൻ അവർ ശ്രമിക്കുന്നു.

ജനങ്ങൾക്ക് പൊതുസംവിധാനങ്ങളെ കൂടുതൽ എളുപ്പത്തിൽ സമീപിക്കാനും കൂട്ടായ പ്രവർത്തനത്തിലൂടെ സ്വന്തം പ്രദേശങ്ങളിൽ മാറ്റം കൊണ്ടുവരാനും സഹായിക്കുന്നതിലാണ് പ്രാദേശിക നേതൃത്വത്തിന്റെ അർത്ഥമെന്ന് ബീന കുമാരി വിശ്വസിക്കുന്നു.',
        ],
    ],

    'k.shafiq.rahman' => [
        'name' => 'K. Shafiq Rahman',
        'tier' => 'accomplished',
        'profession' => 'Trade Union Leader and Workers’ Welfare Organiser',
        'portrait' => 'k_shafiq_rahman.jpg',
        'en' => [
            'title' => 'K. Shafiq Rahman',
            'summary' => 'K. Shafiq Rahman has spent much of his public life working with employees in transport, small manufacturing and service-sector workplaces. His leadership developed through the practical concerns of workers who often needed help understanding workplace procedures, welfare provisions and the institutions through which their grievances could be addressed.',
            'body' => 'K. Shafiq Rahman has spent much of his public life working with employees in transport, small manufacturing and service-sector workplaces. His leadership developed through the practical concerns of workers who often needed help understanding workplace procedures, welfare provisions and the institutions through which their grievances could be addressed.

Shafiq began as a workplace volunteer before taking on responsibilities within a workers’ organisation. His early experience taught him that disputes were often intensified by poor communication. He therefore placed considerable emphasis on documenting concerns, speaking with workers individually and presenting issues collectively rather than allowing disagreements to become personal conflicts.

Over time, his responsibilities expanded to include welfare initiatives, assistance during workplace emergencies and awareness programmes on social-security benefits. He encouraged workers to understand the provisions available to them rather than depending entirely on office-bearers to intervene. Meetings were often organised in simple formats so that workers could ask questions without feeling that they needed specialist knowledge.

One of his continuing interests has been the welfare of workers whose employment is less secure. Contract workers, daily-wage earners and families affected by sudden loss of income often fall between formal systems of support. Shafiq has worked with local volunteers and community organisations to connect such families with available assistance and information.

His approach to collective leadership has also involved younger workers. He encourages them to participate in meetings, maintain records and learn how negotiations and representations are conducted. In his view, an organisation remains strong only when ordinary members understand how it works and are willing to take responsibility.

Shafiq’s contribution is therefore not limited to resolving individual workplace problems. He has helped create a culture in which workers are encouraged to know their rights, discuss their concerns collectively and approach institutions with greater confidence. His public life illustrates the role that organised worker leadership can play in strengthening dignity and participation in everyday economic life.',
        ],
        'ml' => [
            'title' => 'K. Shafiq Rahman',
            'summary' => 'കെ. ഷഫീഖ് റഹ്മാന്റെ പൊതുപ്രവർത്തനത്തിന്റെ വലിയൊരു ഭാഗം ഗതാഗതം, ചെറുകിട നിർമ്മാണം, സേവനമേഖല തുടങ്ങിയ മേഖലകളിലെ തൊഴിലാളികളോടൊപ്പമാണ് വളർന്നത്. ജോലിസ്ഥലത്തെ നടപടിക്രമങ്ങൾ, ക്ഷേമപദ്ധതികൾ, പരാതികൾ ഉന്നയിക്കേണ്ട സ്ഥാപനങ്ങൾ എന്നിവ മനസ്സിലാക്കാൻ തൊഴിലാളികൾക്ക് സഹായം ആവശ്യമുള്ള സാഹചര്യങ്ങളിൽ നിന്നാണ് അദ്ദേഹത്തിന്റെ നേതൃത്വപങ്ക് രൂപപ്പെട്ടത്.',
            'body' => 'കെ. ഷഫീഖ് റഹ്മാന്റെ പൊതുപ്രവർത്തനത്തിന്റെ വലിയൊരു ഭാഗം ഗതാഗതം, ചെറുകിട നിർമ്മാണം, സേവനമേഖല തുടങ്ങിയ മേഖലകളിലെ തൊഴിലാളികളോടൊപ്പമാണ് വളർന്നത്. ജോലിസ്ഥലത്തെ നടപടിക്രമങ്ങൾ, ക്ഷേമപദ്ധതികൾ, പരാതികൾ ഉന്നയിക്കേണ്ട സ്ഥാപനങ്ങൾ എന്നിവ മനസ്സിലാക്കാൻ തൊഴിലാളികൾക്ക് സഹായം ആവശ്യമുള്ള സാഹചര്യങ്ങളിൽ നിന്നാണ് അദ്ദേഹത്തിന്റെ നേതൃത്വപങ്ക് രൂപപ്പെട്ടത്.

ഒരു തൊഴിലാളി സംഘടനയിലെ സന്നദ്ധപ്രവർത്തകനായാണ് ഷഫീഖ് ആദ്യം സജീവമായത്. ആശയവിനിമയത്തിലെ കുറവ് പല തൊഴിൽതർക്കങ്ങളെയും കൂടുതൽ സങ്കീർണമാക്കുന്നുവെന്ന് ആദ്യകാല അനുഭവങ്ങൾ അദ്ദേഹത്തെ പഠിപ്പിച്ചു. അതിനാൽ പ്രശ്നങ്ങൾ രേഖപ്പെടുത്തുക, തൊഴിലാളികളുമായി വ്യക്തിപരമായി സംസാരിക്കുക, വ്യക്തിപരമായ തർക്കങ്ങളാക്കാതെ കൂട്ടായ വിഷയങ്ങളായി അവതരിപ്പിക്കുക എന്നിവയ്ക്ക് അദ്ദേഹം പ്രാധാന്യം നൽകി.

പിന്നീട് തൊഴിലാളി ക്ഷേമ പ്രവർത്തനങ്ങൾ, ജോലിസ്ഥലത്തെ അടിയന്തര സാഹചര്യങ്ങളിലെ സഹായം, സാമൂഹിക സുരക്ഷാ ആനുകൂല്യങ്ങളെക്കുറിച്ചുള്ള ബോധവൽക്കരണം എന്നിവയും അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ ഭാഗമായി. ലഭ്യമായ അവകാശങ്ങളും ആനുകൂല്യങ്ങളും തൊഴിലാളികൾ തന്നെ മനസ്സിലാക്കണം; ഓരോ കാര്യത്തിനും സംഘടനാ ഭാരവാഹികളെ മാത്രം ആശ്രയിക്കേണ്ടതില്ലെന്നതാണ് അദ്ദേഹത്തിന്റെ സമീപനം. പ്രത്യേക പരിജ്ഞാനം ആവശ്യമില്ലാതെ തൊഴിലാളികൾക്ക് ചോദ്യങ്ങൾ ചോദിക്കാനാകുന്ന ലളിതമായ യോഗങ്ങൾക്കും അദ്ദേഹം പ്രാധാന്യം നൽകി.

തൊഴിൽസ്ഥിരത കുറവുള്ള വിഭാഗങ്ങളുടെ ക്ഷേമമാണ് അദ്ദേഹത്തിന്റെ തുടർച്ചയായ ശ്രദ്ധകളിലൊന്ന്. കരാർ തൊഴിലാളികൾ, ദിവസവേതന തൊഴിലാളികൾ, അപ്രതീക്ഷിതമായി വരുമാനം നഷ്ടപ്പെടുന്ന കുടുംബങ്ങൾ എന്നിവർ പലപ്പോഴും ഔദ്യോഗിക സഹായ സംവിധാനങ്ങളുടെ ഇടയിൽപ്പെടുന്നു. പ്രാദേശിക സന്നദ്ധ പ്രവർത്തകരുമായും സാമൂഹിക സംഘടനകളുമായും ചേർന്ന് ലഭ്യമായ സഹായങ്ങളിലേക്കും വിവരങ്ങളിലേക്കും ഇത്തരം കുടുംബങ്ങളെ ബന്ധിപ്പിക്കാൻ ഷഫീഖ് പ്രവർത്തിച്ചു.

യുവ തൊഴിലാളികളെ സംഘടനാ പ്രവർത്തനത്തിൽ പങ്കാളികളാക്കുന്നതിനും അദ്ദേഹം ശ്രദ്ധിച്ചു. യോഗങ്ങളിൽ പങ്കെടുക്കുക, രേഖകൾ സൂക്ഷിക്കുക, നിവേദനങ്ങളും ചർച്ചകളും എങ്ങനെ നടത്തുന്നു എന്ന് മനസ്സിലാക്കുക എന്നിവയിൽ അവർക്ക് ഉത്തരവാദിത്തം നൽകുന്നു. സാധാരണ അംഗങ്ങൾ സംഘടന എങ്ങനെ പ്രവർത്തിക്കുന്നു എന്ന് മനസ്സിലാക്കി ഉത്തരവാദിത്തം ഏറ്റെടുക്കുമ്പോഴാണ് ഒരു സംഘടന ശക്തമാകുന്നതെന്ന് അദ്ദേഹം വിശ്വസിക്കുന്നു.

വ്യക്തിഗത തൊഴിൽപ്രശ്നങ്ങൾ പരിഹരിക്കുന്നതിൽ മാത്രം അദ്ദേഹത്തിന്റെ സംഭാവന ഒതുങ്ങുന്നില്ല. സ്വന്തം അവകാശങ്ങളെക്കുറിച്ച് അറിയാനും പ്രശ്നങ്ങൾ കൂട്ടായി ചർച്ച ചെയ്യാനും സ്ഥാപനങ്ങളെ കൂടുതൽ ആത്മവിശ്വാസത്തോടെ സമീപിക്കാനും തൊഴിലാളികളെ പ്രാപ്തരാക്കുന്ന ഒരു സംഘടനാ സംസ്കാരം വളർത്തുന്നതിലും അദ്ദേഹം പങ്കുവഹിച്ചു.',
        ],
    ],

    'r.leelamma' => [
        'name' => 'R. Leelamma',
        'tier' => 'accomplished',
        'profession' => 'Cooperative Bank Functionary and Cooperative Development Leader',
        'portrait' => 'r_leelamma.jpg',
        'en' => [
            'title' => 'R. Leelamma',
            'summary' => 'R. Leelamma’s public contribution has developed through the cooperative movement and its relationship with ordinary households. Over several years of service within a cooperative banking institution, she became involved in programmes concerned with financial awareness, small borrowers, women’s groups and the practical functioning of member-owned institutions.',
            'body' => 'R. Leelamma’s public contribution has developed through the cooperative movement and its relationship with ordinary households. Over several years of service within a cooperative banking institution, she became involved in programmes concerned with financial awareness, small borrowers, women’s groups and the practical functioning of member-owned institutions.

Leelamma began as a committee volunteer and gradually took on wider organisational responsibilities. She was particularly interested in members who approached a cooperative institution only when they were already facing financial difficulty. She encouraged the institution to communicate more clearly about savings, credit discipline, repayment obligations and the difference between short-term assistance and sustainable financial planning.

Her work with women’s groups became another important part of her community role. Small savings groups often had members with limited experience of formal financial institutions. Leelamma supported simple training sessions in which members learned to maintain records, understand basic accounts and discuss the purpose of a loan before taking on a financial obligation.

She also encouraged cooperative institutions to remain connected to the communities from which their membership came. Meetings with local groups were used to identify recurring problems and explain what the institution could and could not provide. In her view, a cooperative bank should be more than a place where transactions take place; it should help members understand how collective financial institutions work.

Leelamma has sometimes had to balance expectations for quick assistance with the responsibilities of protecting the institution’s financial health. She believes that saying no to an unsuitable loan can also be a form of responsible service when it prevents a family from taking on an obligation it cannot sustain.

Her leadership has therefore combined institutional discipline with a strong emphasis on member participation. She hopes that younger members will see cooperatives not simply as service providers but as institutions in which ordinary people can learn, participate and share responsibility for their community’s economic wellbeing.',
        ],
        'ml' => [
            'title' => 'R. Leelamma',
            'summary' => 'ആർ. ലീലാമ്മയുടെ പൊതുസംഭാവന സഹകരണ പ്രസ്ഥാനത്തിലൂടെയും സാധാരണ കുടുംബങ്ങളുമായുള്ള അതിന്റെ ബന്ധത്തിലൂടെയും വളർന്നതാണ്. സഹകരണ ബാങ്കിംഗ് സ്ഥാപനത്തിലെ ദീർഘകാല പ്രവർത്തനത്തിനിടെ സാമ്പത്തിക ബോധവൽക്കരണം, ചെറുകിട വായ്പക്കാർ, വനിതാ കൂട്ടായ്മകൾ, അംഗങ്ങൾ ചേർന്ന് നടത്തുന്ന സ്ഥാപനങ്ങളുടെ പ്രായോഗിക പ്രവർത്തനം എന്നിവയുമായി ബന്ധപ്പെട്ട പ്രവർത്തനങ്ങളിൽ അവർ സജീവമായി.',
            'body' => 'ആർ. ലീലാമ്മയുടെ പൊതുസംഭാവന സഹകരണ പ്രസ്ഥാനത്തിലൂടെയും സാധാരണ കുടുംബങ്ങളുമായുള്ള അതിന്റെ ബന്ധത്തിലൂടെയും വളർന്നതാണ്. സഹകരണ ബാങ്കിംഗ് സ്ഥാപനത്തിലെ ദീർഘകാല പ്രവർത്തനത്തിനിടെ സാമ്പത്തിക ബോധവൽക്കരണം, ചെറുകിട വായ്പക്കാർ, വനിതാ കൂട്ടായ്മകൾ, അംഗങ്ങൾ ചേർന്ന് നടത്തുന്ന സ്ഥാപനങ്ങളുടെ പ്രായോഗിക പ്രവർത്തനം എന്നിവയുമായി ബന്ധപ്പെട്ട പ്രവർത്തനങ്ങളിൽ അവർ സജീവമായി.

ഒരു കമ്മിറ്റി സന്നദ്ധപ്രവർത്തകയായാണ് ലീലാമ്മ ആദ്യം പ്രവർത്തനം ആരംഭിച്ചത്. പിന്നീട് സംഘടനാതലത്തിലുള്ള കൂടുതൽ ഉത്തരവാദിത്തങ്ങൾ ഏറ്റെടുത്തു. സാമ്പത്തിക ബുദ്ധിമുട്ട് രൂക്ഷമായ ശേഷമാണ് പല അംഗങ്ങളും സഹകരണ സ്ഥാപനത്തെ സമീപിക്കുന്നതെന്ന് അവർ ശ്രദ്ധിച്ചു. സമ്പാദ്യം, വായ്പയുടെ ഉത്തരവാദിത്തം, തിരിച്ചടവ്, താൽക്കാലിക സഹായവും സ്ഥിരതയുള്ള സാമ്പത്തിക ആസൂത്രണവും തമ്മിലുള്ള വ്യത്യാസം എന്നിവയെക്കുറിച്ച് കൂടുതൽ വ്യക്തമായ ആശയവിനിമയം വേണമെന്ന് അവർ മുന്നോട്ടുവച്ചു.

സ്ത്രീകളുടെ കൂട്ടായ്മകളോടൊപ്പമുള്ള പ്രവർത്തനം അവരുടെ സാമൂഹിക പങ്കിന്റെ മറ്റൊരു പ്രധാന ഭാഗമായി. ഔദ്യോഗിക സാമ്പത്തിക സ്ഥാപനങ്ങളുമായി മുമ്പ് അധികം ഇടപെട്ടിട്ടില്ലാത്ത അംഗങ്ങളുള്ള ചെറിയ സമ്പാദ്യ കൂട്ടായ്മകൾക്ക് രേഖകൾ സൂക്ഷിക്കുക, അടിസ്ഥാന കണക്കുകൾ മനസ്സിലാക്കുക, വായ്പ എടുക്കുന്നതിന് മുമ്പ് അതിന്റെ ഉദ്ദേശ്യം ചർച്ച ചെയ്യുക തുടങ്ങിയ കാര്യങ്ങളിൽ ലളിതമായ പരിശീലനങ്ങൾ നൽകുന്നതിന് അവർ പിന്തുണ നൽകി.

സഹകരണ സ്ഥാപനങ്ങൾ അംഗങ്ങൾ വരുന്ന സമൂഹങ്ങളുമായി നിരന്തരം ബന്ധം നിലനിർത്തണമെന്നും ലീലാമ്മ വിശ്വസിക്കുന്നു. പ്രാദേശിക കൂട്ടായ്മകളുമായുള്ള യോഗങ്ങളിൽ ആവർത്തിച്ച് ഉയരുന്ന പ്രശ്നങ്ങൾ മനസ്സിലാക്കുകയും സ്ഥാപനത്തിന് എന്ത് ചെയ്യാനാകുമെന്നും എന്ത് ചെയ്യാനാകില്ലെന്നും വ്യക്തമാക്കുകയും ചെയ്തു. സഹകരണ ബാങ്ക് ഇടപാടുകൾ നടക്കുന്ന ഒരു സ്ഥലം മാത്രമല്ല; കൂട്ടായ സാമ്പത്തിക സ്ഥാപനങ്ങൾ എങ്ങനെ പ്രവർത്തിക്കുന്നു എന്ന് അംഗങ്ങൾക്ക് മനസ്സിലാക്കാൻ സഹായിക്കുന്ന ഇടവുമാകണമെന്നാണ് അവരുടെ കാഴ്ചപ്പാട്.

വേഗത്തിലുള്ള സഹായത്തിനുള്ള പ്രതീക്ഷകളും സ്ഥാപനത്തിന്റെ സാമ്പത്തിക ഉത്തരവാദിത്തവും തമ്മിൽ പലപ്പോഴും സന്തുലനം പാലിക്കേണ്ടിവന്നു. തിരിച്ചടയ്ക്കാൻ കഴിയാത്ത ബാധ്യതയിലേക്ക് ഒരു കുടുംബത്തെ നയിക്കുന്ന വായ്പ അനുവദിക്കാതിരിക്കുന്നതും ഉത്തരവാദിത്തമുള്ള സേവനത്തിന്റെ ഭാഗമാണെന്ന് അവർ കരുതുന്നു.

സ്ഥാപനശാസനയും അംഗപങ്കാളിത്തവും ഒരുമിപ്പിക്കുന്നതാണ് ലീലാമ്മയുടെ നേതൃത്വശൈലി. സഹകരണ സ്ഥാപനങ്ങളെ സേവനം നൽകുന്ന ഇടങ്ങളായി മാത്രം കാണാതെ സാധാരണ ആളുകൾക്ക് പഠിക്കാനും പങ്കെടുക്കാനും സ്വന്തം സമൂഹത്തിന്റെ സാമ്പത്തിക ക്ഷേമത്തിൽ പങ്കുവഹിക്കാനും കഴിയുന്ന സ്ഥാപനങ്ങളായി കാണണമെന്നാണ് അവരുടെ ആഗ്രഹം.',
        ],
    ],

    'c.manoj.kumar' => [
        'name' => 'C. Manoj Kumar',
        'tier' => 'accomplished',
        'profession' => 'Grassroots Organisation Functionary and Public Outreach Coordinator',
        'portrait' => 'c_manoj_kumar.jpg',
        'en' => [
            'title' => 'C. Manoj Kumar',
            'summary' => 'C. Manoj Kumar has built his public life through grassroots organisational work, local outreach and the coordination of volunteers. His role has often been less about formal office and more about maintaining contact with neighbourhood groups, arranging meetings and helping community concerns move from informal conversations into organised representations.',
            'body' => 'C. Manoj Kumar has built his public life through grassroots organisational work, local outreach and the coordination of volunteers. His role has often been less about formal office and more about maintaining contact with neighbourhood groups, arranging meetings and helping community concerns move from informal conversations into organised representations.

Manoj became involved in public organisation work as a young volunteer. He initially helped with small neighbourhood programmes and information campaigns before taking responsibility for coordinating local units. He developed a reputation for being comfortable with the routine work that keeps a large organisation connected to its members: visiting local areas, listening to concerns, maintaining contact lists and ensuring that meetings do not remain merely ceremonial.

His public activities have included support for community facilities, educational assistance, local service information and relief coordination during difficult periods. He has worked with people from different social backgrounds and believes that an organisation’s credibility depends on whether it remains accessible between major events.

A recurring concern in his work has been the gap between public discussion and practical follow-through. Manoj encourages local volunteers to record an issue, identify the relevant authority or institution and report back on what happened. This has helped create a more disciplined approach to representations and community requests.

He has also placed emphasis on bringing younger volunteers into organisational responsibilities. Rather than assigning them only event-related tasks, he encourages them to conduct small meetings, prepare notes and take responsibility for communicating with residents. His view is that grassroots leadership is learned through repeated contact with people and through the experience of carrying a responsibility to completion.

Manoj’s contribution reflects the organisational side of public life: the patient work of keeping people connected, helping local concerns find a collective voice and building a culture in which participation continues even when attention moves elsewhere.',
        ],
        'ml' => [
            'title' => 'C. Manoj Kumar',
            'summary' => 'സി. മനോജ് കുമാറിന്റെ പൊതുജീവിതം അടിത്തറതല സംഘടനാ പ്രവർത്തനങ്ങൾ, പ്രാദേശിക ഇടപെടലുകൾ, സന്നദ്ധ പ്രവർത്തകരുടെ ഏകോപനം എന്നിവയിലൂടെയാണ് വളർന്നത്. ഔദ്യോഗിക പദവിയേക്കാൾ നാട്ടിലെ കൂട്ടായ്മകളുമായി ബന്ധം നിലനിർത്തുക, യോഗങ്ങൾ സംഘടിപ്പിക്കുക, സമൂഹത്തിലെ വിഷയങ്ങളെ ക്രമബദ്ധമായ നിവേദനങ്ങളാക്കി മാറ്റാൻ സഹായിക്കുക എന്നിവയാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ പ്രധാന ഭാഗങ്ങൾ.',
            'body' => 'സി. മനോജ് കുമാറിന്റെ പൊതുജീവിതം അടിത്തറതല സംഘടനാ പ്രവർത്തനങ്ങൾ, പ്രാദേശിക ഇടപെടലുകൾ, സന്നദ്ധ പ്രവർത്തകരുടെ ഏകോപനം എന്നിവയിലൂടെയാണ് വളർന്നത്. ഔദ്യോഗിക പദവിയേക്കാൾ നാട്ടിലെ കൂട്ടായ്മകളുമായി ബന്ധം നിലനിർത്തുക, യോഗങ്ങൾ സംഘടിപ്പിക്കുക, സമൂഹത്തിലെ വിഷയങ്ങളെ ക്രമബദ്ധമായ നിവേദനങ്ങളാക്കി മാറ്റാൻ സഹായിക്കുക എന്നിവയാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ പ്രധാന ഭാഗങ്ങൾ.

യുവ സന്നദ്ധപ്രവർത്തകനായാണ് മനോജ് പൊതുസംഘടനാ പ്രവർത്തനത്തിലേക്ക് എത്തിയത്. ചെറിയ അയൽക്കൂട്ട പരിപാടികളിലും വിവരവിനിമയ പ്രവർത്തനങ്ങളിലും പങ്കെടുത്തശേഷം പ്രാദേശിക യൂണിറ്റുകളുടെ ഏകോപന ചുമതലകൾ ഏറ്റെടുത്തു. ഒരു വലിയ സംഘടനയെ അംഗങ്ങളുമായി ബന്ധിപ്പിച്ചു നിർത്തുന്ന പതിവ് പ്രവർത്തനങ്ങളിൽ അദ്ദേഹം സജീവമായി — പ്രദേശങ്ങൾ സന്ദർശിക്കുക, പ്രശ്നങ്ങൾ കേൾക്കുക, ബന്ധപ്പെടേണ്ടവരുടെ വിവരങ്ങൾ സൂക്ഷിക്കുക, യോഗങ്ങൾ ഔപചാരികമായി മാത്രം അവസാനിക്കാതിരിക്കാൻ ശ്രദ്ധിക്കുക തുടങ്ങിയവ.

പൊതുസൗകര്യങ്ങൾ, വിദ്യാഭ്യാസ സഹായം, പ്രാദേശിക സേവനങ്ങളെക്കുറിച്ചുള്ള വിവരങ്ങൾ, ബുദ്ധിമുട്ടുള്ള സമയങ്ങളിലെ സഹായ ഏകോപനം എന്നിവയുമായി ബന്ധപ്പെട്ട പ്രവർത്തനങ്ങളിലും അദ്ദേഹം പങ്കെടുത്തു. വ്യത്യസ്ത സാമൂഹിക പശ്ചാത്തലങ്ങളിലുള്ള ആളുകളുമായി പ്രവർത്തിച്ച അദ്ദേഹം ഒരു സംഘടനയുടെ വിശ്വാസ്യത വലിയ പരിപാടികളിൽ മാത്രമല്ല, അവയ്ക്കിടയിലും ആളുകൾക്ക് സമീപിക്കാനാകുന്നതിലാണെന്ന് വിശ്വസിക്കുന്നു.

പൊതുചർച്ചയും പ്രായോഗിക തുടർനടപടിയും തമ്മിലുള്ള അകലം കുറയ്ക്കുക എന്നതാണ് അദ്ദേഹത്തിന്റെ സ്ഥിരമായ ശ്രദ്ധകളിലൊന്ന്. ഒരു വിഷയം രേഖപ്പെടുത്തുക, ബന്ധപ്പെട്ട സ്ഥാപനത്തെ കണ്ടെത്തുക, തുടർന്ന് എന്ത് സംഭവിച്ചുവെന്ന് തിരിച്ചറിയുക എന്ന രീതിയിൽ പ്രവർത്തിക്കാൻ പ്രാദേശിക സന്നദ്ധ പ്രവർത്തകരെ അദ്ദേഹം പ്രോത്സാഹിപ്പിക്കുന്നു. നിവേദനങ്ങളും സമൂഹ ആവശ്യങ്ങളും കൂടുതൽ ക്രമബദ്ധമായി മുന്നോട്ടുകൊണ്ടുപോകാൻ ഇത് സഹായിച്ചു.

യുവ സന്നദ്ധപ്രവർത്തകരെ സംഘടനാ ഉത്തരവാദിത്തങ്ങളിലേക്ക് കൊണ്ടുവരുന്നതിനും മനോജ് പ്രാധാന്യം നൽകി. പരിപാടികളിലെ ചെറിയ ജോലികളിൽ മാത്രം ഒതുക്കാതെ യോഗങ്ങൾ നടത്താനും കുറിപ്പുകൾ തയ്യാറാക്കാനും നാട്ടുകാരുമായി ആശയവിനിമയം നടത്താനും അവരെ പ്രോത്സാഹിപ്പിക്കുന്നു. ആളുകളുമായി ആവർത്തിച്ച് ഇടപഴകുകയും ഏറ്റെടുത്ത ഉത്തരവാദിത്തം പൂർത്തിയാക്കുകയും ചെയ്യുന്നതിലൂടെയാണ് അടിത്തറതല നേതൃത്വം പഠിക്കപ്പെടുന്നതെന്ന് അദ്ദേഹം കരുതുന്നു.

ആളുകളെ ബന്ധിപ്പിക്കുകയും പ്രാദേശിക വിഷയങ്ങൾക്ക് കൂട്ടായ ശബ്ദം നൽകുകയും ശ്രദ്ധ മറ്റിടങ്ങളിലേക്ക് മാറിയാലും പങ്കാളിത്തം തുടരുന്ന ഒരു സംഘടനാ സംസ്കാരം വളർത്തുകയും ചെയ്യുന്ന പൊതുജീവിതത്തിന്റെ സംഘടനാപരമായ വശത്തെയാണ് മനോജിന്റെ സംഭാവന പ്രതിനിധീകരിക്കുന്നത്.',
        ],
    ],

    'a.mariamma' => [
        'name' => 'A. Mariamma',
        'tier' => 'accomplished',
        'profession' => 'Religious Community Organisation Functionary and Social Service Organiser',
        'portrait' => 'a_mariamma.jpg',
        'en' => [
            'title' => 'A. Mariamma',
            'summary' => 'A. Mariamma has spent many years working through a community organisation attached to a local religious institution, where her responsibilities have combined social service, women’s participation and support for families facing temporary hardship. Her public role has developed through practical service rather than formal prominence.',
            'body' => 'A. Mariamma has spent many years working through a community organisation attached to a local religious institution, where her responsibilities have combined social service, women’s participation and support for families facing temporary hardship. Her public role has developed through practical service rather than formal prominence.

Mariamma began volunteering with a small group that organised assistance for elderly people living alone. As the group grew, its activities expanded to include educational support for children, assistance to families during medical emergencies and community programmes for women. She gradually became responsible for coordinating volunteers and maintaining communication with local families.

One of her strongest interests has been making community welfare work more systematic. She encouraged volunteers to maintain basic records of requests so that assistance was not distributed only to people who happened to be well known to the organisers. At the same time, she remained careful about privacy and preferred that support be provided without unnecessary public attention.

Her work has also involved bringing women into the planning of community activities. Small groups were encouraged to identify needs in their own neighbourhoods and propose practical responses. Some initiatives involved educational materials, food support during difficult periods and assistance for older residents who found it difficult to manage routine tasks.

Mariamma believes that religious and community institutions have a responsibility to remain connected to the wider social environment around them. In her view, service should not be limited to formal occasions; it should include the ordinary work of noticing who is struggling and finding a respectful way to help.

Her contribution has therefore been built on continuity. She has trained younger volunteers, encouraged shared responsibility and helped turn occasional charitable efforts into more organised community programmes. Her public life demonstrates how local religious institutions can also become spaces for social participation, volunteerism and practical support.',
        ],
        'ml' => [
            'title' => 'A. Mariamma',
            'summary' => 'എ. മറിയാമ്മ വർഷങ്ങളായി ഒരു പ്രാദേശിക മതസ്ഥാപനവുമായി ബന്ധപ്പെട്ട സാമൂഹിക സംഘടനയിലൂടെ പ്രവർത്തിച്ചുവരുന്നു. സാമൂഹിക സേവനം, സ്ത്രീകളുടെ പങ്കാളിത്തം, താൽക്കാലിക ബുദ്ധിമുട്ടുകൾ നേരിടുന്ന കുടുംബങ്ങൾക്ക് സഹായം എന്നിവയെ കൂട്ടിച്ചേർത്താണ് അവരുടെ പ്രവർത്തനപങ്ക് വളർന്നത്. ഔദ്യോഗിക പ്രാധാന്യത്തേക്കാൾ പ്രായോഗിക സേവനത്തിലൂടെയാണ് അവരുടെ പൊതുപങ്ക് രൂപപ്പെട്ടത്.',
            'body' => 'എ. മറിയാമ്മ വർഷങ്ങളായി ഒരു പ്രാദേശിക മതസ്ഥാപനവുമായി ബന്ധപ്പെട്ട സാമൂഹിക സംഘടനയിലൂടെ പ്രവർത്തിച്ചുവരുന്നു. സാമൂഹിക സേവനം, സ്ത്രീകളുടെ പങ്കാളിത്തം, താൽക്കാലിക ബുദ്ധിമുട്ടുകൾ നേരിടുന്ന കുടുംബങ്ങൾക്ക് സഹായം എന്നിവയെ കൂട്ടിച്ചേർത്താണ് അവരുടെ പ്രവർത്തനപങ്ക് വളർന്നത്. ഔദ്യോഗിക പ്രാധാന്യത്തേക്കാൾ പ്രായോഗിക സേവനത്തിലൂടെയാണ് അവരുടെ പൊതുപങ്ക് രൂപപ്പെട്ടത്.

ഒറ്റയ്ക്ക് കഴിയുന്ന വയോധികർക്കായി സഹായം സംഘടിപ്പിച്ചിരുന്ന ഒരു ചെറിയ സംഘത്തിലെ സന്നദ്ധപ്രവർത്തകയായാണ് മറിയാമ്മ ആദ്യം സജീവമായത്. പ്രവർത്തനം വികസിച്ചതോടെ കുട്ടികൾക്ക് വിദ്യാഭ്യാസ സഹായം, ചികിത്സാ അടിയന്തര സാഹചര്യങ്ങളിലുള്ള കുടുംബങ്ങൾക്ക് സഹായം, സ്ത്രീകൾക്കായുള്ള സമൂഹപരിപാടികൾ എന്നിവയും കൂട്ടിച്ചേർത്തു. സന്നദ്ധപ്രവർത്തകരുടെ ഏകോപനവും കുടുംബങ്ങളുമായുള്ള ആശയവിനിമയവും പിന്നീട് അവരുടെ ചുമതലകളുടെ ഭാഗമായി.

സമൂഹക്ഷേമ പ്രവർത്തനങ്ങളെ കൂടുതൽ ക്രമബദ്ധമാക്കുന്നതിൽ അവർ പ്രത്യേക ശ്രദ്ധ നൽകി. സഹായം ആവശ്യപ്പെട്ടവരുടെ അടിസ്ഥാന വിവരങ്ങൾ രേഖപ്പെടുത്തുന്നത് പരിചയമുള്ളവർക്കു മാത്രം സഹായം ലഭിക്കുന്ന സാഹചര്യം കുറയ്ക്കുമെന്ന് അവർ കരുതി. അതേസമയം സ്വകാര്യതയ്ക്ക് അവർ പ്രാധാന്യം നൽകി; ആവശ്യമായ സഹായം അനാവശ്യമായ പൊതുശ്രദ്ധയില്ലാതെ നൽകുന്നതാണ് അവരുടെ സമീപനം.

സമൂഹപരിപാടികളുടെ ആസൂത്രണത്തിൽ സ്ത്രീകളെ കൂടുതൽ ഉൾപ്പെടുത്താനും അവർ ശ്രമിച്ചു. സ്വന്തം പ്രദേശങ്ങളിലെ ആവശ്യങ്ങൾ കണ്ടെത്തി പ്രായോഗിക പരിഹാരങ്ങൾ നിർദ്ദേശിക്കാൻ ചെറിയ കൂട്ടായ്മകളെ പ്രോത്സാഹിപ്പിച്ചു. പഠനസാമഗ്രികൾ, ബുദ്ധിമുട്ടുള്ള കാലയളവുകളിൽ ഭക്ഷണസഹായം, പതിവ് കാര്യങ്ങൾ ചെയ്യാൻ ബുദ്ധിമുട്ടുന്ന വയോധികർക്കുള്ള സഹായം തുടങ്ങിയ പ്രവർത്തനങ്ങൾ ഇതിൽ ഉൾപ്പെട്ടു.

മത-സാമൂഹിക സ്ഥാപനങ്ങൾ തങ്ങളെ ചുറ്റിപ്പറ്റിയുള്ള വിശാലമായ സമൂഹവുമായി ബന്ധപ്പെട്ടു നിൽക്കേണ്ട ഉത്തരവാദിത്തമുണ്ടെന്ന് മറിയാമ്മ വിശ്വസിക്കുന്നു. സേവനം ഔപചാരിക അവസരങ്ങളിൽ മാത്രം ഒതുങ്ങേണ്ടതില്ല; ആരാണ് ബുദ്ധിമുട്ടുന്നത് എന്ന് ശ്രദ്ധിക്കുകയും അവരെ മാന്യമായി സഹായിക്കാനുള്ള മാർഗം കണ്ടെത്തുകയും ചെയ്യുന്നതും അതിന്റെ ഭാഗമാണെന്ന് അവർ കരുതുന്നു.

തുടർച്ചയാണ് അവരുടെ സംഭാവനയുടെ പ്രധാന സവിശേഷത. യുവ സന്നദ്ധപ്രവർത്തകരെ പരിശീലിപ്പിക്കുകയും ഉത്തരവാദിത്തങ്ങൾ പങ്കിടാൻ പ്രോത്സാഹിപ്പിക്കുകയും ഇടയ്ക്കിടെ നടക്കുന്ന ദാനപ്രവർത്തനങ്ങളെ കൂടുതൽ ക്രമബദ്ധമായ സമൂഹപരിപാടികളാക്കി മാറ്റാൻ സഹായിക്കുകയും ചെയ്തു. പ്രാദേശിക മതസ്ഥാപനങ്ങൾ സാമൂഹിക പങ്കാളിത്തത്തിന്റെയും സന്നദ്ധപ്രവർത്തനത്തിന്റെയും പ്രായോഗിക സഹായത്തിന്റെയും ഇടങ്ങളായും മാറാനാകുമെന്ന് അവരുടെ പൊതുപ്രവർത്തനം കാണിക്കുന്നു.',
        ],
    ],

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
