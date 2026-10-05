<?php

/*
| Demonstration-profile content — verbatim from the corrected demonstration
| corpus (Jannayaks_Demo_Corpus_CORRECTED.zip, "Rebuilt Demonstration
| Corpus — v2"). This file replaces the earlier 12-profile package.
|
| 10 living demonstrations: 3 Recognised, 4 Acclaimed, 3 Distinguished.
| All are fictional demonstration personas.
|
| Each entry: name, tier (internal key), profession (the corpus
| "Role / theme" line), EN + ML title/summary/body, and the approved
| portrait filename in database/seeders/demo-media/. The summary is the
| first paragraph of the supplied narrative; the body is the complete
| supplied narrative (headings, metadata lines and the closing
| demonstration footer are omitted — the page shows its own notice).
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
            'summary' => 'T. Gopalakrishnan’s public life has developed alongside the growth of the fictional Sree Narayana Community Development Council (SNCDC), an organisation that began as a small community initiative and expanded into education, social welfare and community development. His work has combined organisational leadership with a sustained interest in education, youth participation and the development of community institutions.',
            'body' => 'T. Gopalakrishnan’s public life has developed alongside the growth of the fictional Sree Narayana Community Development Council (SNCDC), an organisation that began as a small community initiative and expanded into education, social welfare and community development. His work has combined organisational leadership with a sustained interest in education, youth participation and the development of community institutions.

Born in Alappuzha, Gopalakrishnan studied history at the University of Kerala before entering the cooperative sector. He worked for several years in banking and later became involved in community organisations, initially as a volunteer supporting educational programmes for young people. His association with the SNCDC began at the local level, where he helped organise scholarship assistance for students from economically weaker families.

What began as a volunteer activity gradually became a larger responsibility. He served successively as branch secretary, district coordinator and state education convenor before being elected president of the SNCDC. Under his leadership, the organisation expanded existing programmes and introduced initiatives in vocational education, women’s self-help groups and youth development.

Education has remained one of his principal areas of interest. The organisation’s scholarship programme, which initially assisted fewer than fifty students, eventually expanded to several hundred beneficiaries. Gopalakrishnan also supported the creation of a vocational training centre intended to provide practical skills to young people who had not pursued conventional higher education.

Another programme he considers particularly meaningful is a women’s micro-enterprise initiative. Small groups received training and modest financial assistance to establish home-based businesses, and the programme developed into a network of women’s self-help groups operating in several districts. For Gopalakrishnan, however, the purpose of community work is not simply to provide assistance. He believes that people who receive support should eventually become participants in creating opportunities for others.

That philosophy has also shaped the organisation’s youth programmes. Young volunteers are given responsibility for educational camps, cultural programmes and community service activities, while senior office-bearers act increasingly as mentors rather than permanent organisers. His organisational responsibilities have also required him to work with people holding different public viewpoints. He has maintained that the SNCDC’s community programmes should remain accessible to people with different affiliations and backgrounds. He has nevertheless spoken publicly on issues involving education, social mobility, representation and access to public institutions.

His responsibilities have included President of the SNCDC, Chairman of its Education Committee, Convenor of its Community Development Programme and member of the governing board of the organisation’s educational trust. He has also served on advisory committees connected with vocational education and community development.

Away from the organisation, Gopalakrishnan leads a comparatively ordinary family life. His wife, Radha, is a retired teacher, and their two children work in medicine and education. He remains interested in books, classical Malayalam literature and conversations with young people entering professional life.

In recent years, he has become increasingly interested in succession. He wants the organisation to become less dependent on individual leaders and more capable of producing its next generation of volunteers, educators and community organisers. He sees continuity between his early work helping a small number of students and his later responsibility for a wider institution. For him, the purpose of an organisation is not merely to solve today’s problems, but to leave behind people capable of solving tomorrow’s.',
        ],
        'ml' => [
            'title' => 'T. Gopalakrishnan',
            'summary' => 'ടി. ഗോപാലകൃഷ്ണന്റെ പൊതുജീവിതം ചെറിയൊരു സാമൂഹിക സംരംഭമായി ആരംഭിച്ച് വിദ്യാഭ്യാസം, സാമൂഹികക്ഷേമം, സമൂഹവികസനം എന്നിവയിലേക്ക് വളർന്ന സാങ്കൽപ്പിക ശ്രീനാരായണ കമ്മ്യൂണിറ്റി ഡെവലപ്മെന്റ് കൗൺസിലുമായി (SNCDC) ചേർന്നാണ് വികസിച്ചത്. സംഘടനാ നേതൃത്വത്തോടൊപ്പം വിദ്യാഭ്യാസം, യുവജനപങ്കാളിത്തം, സമൂഹസ്ഥാപനങ്ങളുടെ വളർച്ച എന്നിവയിൽ തുടർച്ചയായ താൽപര്യമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ സവിശേഷത.',
            'body' => 'ടി. ഗോപാലകൃഷ്ണന്റെ പൊതുജീവിതം ചെറിയൊരു സാമൂഹിക സംരംഭമായി ആരംഭിച്ച് വിദ്യാഭ്യാസം, സാമൂഹികക്ഷേമം, സമൂഹവികസനം എന്നിവയിലേക്ക് വളർന്ന സാങ്കൽപ്പിക ശ്രീനാരായണ കമ്മ്യൂണിറ്റി ഡെവലപ്മെന്റ് കൗൺസിലുമായി (SNCDC) ചേർന്നാണ് വികസിച്ചത്. സംഘടനാ നേതൃത്വത്തോടൊപ്പം വിദ്യാഭ്യാസം, യുവജനപങ്കാളിത്തം, സമൂഹസ്ഥാപനങ്ങളുടെ വളർച്ച എന്നിവയിൽ തുടർച്ചയായ താൽപര്യമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ സവിശേഷത.

ആലപ്പുഴയിൽ ജനിച്ച ഗോപാലകൃഷ്ണൻ കേരള സർവകലാശാലയിൽ ചരിത്രം പഠിച്ചശേഷം സഹകരണ മേഖലയിലേക്കാണ് പ്രവേശിച്ചത്. ഏതാനും വർഷം ബാങ്കിംഗ് രംഗത്ത് പ്രവർത്തിച്ച അദ്ദേഹം പിന്നീട് സമൂഹസംഘടനകളുമായി ബന്ധപ്പെട്ട് പ്രവർത്തിച്ചു. യുവാക്കൾക്കായുള്ള വിദ്യാഭ്യാസ പരിപാടികൾക്ക് പിന്തുണ നൽകുന്ന സന്നദ്ധപ്രവർത്തകനായാണ് അദ്ദേഹം ആദ്യം SNCDCയുമായി ബന്ധപ്പെട്ടത്. സാമ്പത്തികമായി പിന്നാക്കം നിൽക്കുന്ന കുടുംബങ്ങളിലെ വിദ്യാർത്ഥികൾക്ക് സ്കോളർഷിപ്പ് സഹായം സംഘടിപ്പിക്കുന്നതിലൂടെയാണ് അദ്ദേഹത്തിന്റെ പ്രാദേശിക സംഘടനാ പ്രവർത്തനം ആരംഭിച്ചത്.

സന്നദ്ധപ്രവർത്തനമായി തുടങ്ങിയ ഉത്തരവാദിത്തം പിന്നീട് വലുതായി. ബ്രാഞ്ച് സെക്രട്ടറി, ജില്ലാ കോഓർഡിനേറ്റർ, സംസ്ഥാന വിദ്യാഭ്യാസ കൺവീനർ എന്നീ ചുമതലകൾ വഹിച്ചശേഷം അദ്ദേഹം SNCDCയുടെ പ്രസിഡന്റായി തിരഞ്ഞെടുക്കപ്പെട്ടു. അദ്ദേഹത്തിന്റെ നേതൃത്വത്തിൽ വിദ്യാഭ്യാസം, വനിതാ സ്വയംസഹായ സംഘങ്ങൾ, യുവജനവികസനം എന്നിവയുമായി ബന്ധപ്പെട്ട പുതിയ പ്രവർത്തനങ്ങൾ ആരംഭിക്കുകയും നിലവിലുള്ള പരിപാടികൾ വിപുലീകരിക്കുകയും ചെയ്തു.

വിദ്യാഭ്യാസം അദ്ദേഹത്തിന്റെ പ്രധാന താൽപര്യ മേഖലകളിലൊന്നായി തുടർന്നു. ആദ്യം അമ്പതിൽ താഴെ വിദ്യാർത്ഥികൾക്ക് സഹായം നൽകിയിരുന്ന സ്കോളർഷിപ്പ് പദ്ധതി പിന്നീട് നൂറുകണക്കിന് ഗുണഭോക്താക്കളിലേക്ക് വികസിച്ചു. പരമ്പരാഗത ഉയർന്ന വിദ്യാഭ്യാസത്തിലേക്ക് കടക്കാത്ത യുവാക്കൾക്ക് പ്രായോഗിക കഴിവുകൾ നൽകുന്നതിനായി ഒരു വൊക്കേഷണൽ പരിശീലന കേന്ദ്രം രൂപപ്പെടുത്തുന്നതിനും അദ്ദേഹം പിന്തുണ നൽകി.

വനിതകൾക്കായുള്ള ചെറുകിട സംരംഭ പരിപാടിയെയും അദ്ദേഹം പ്രധാനപ്പെട്ടതായി കാണുന്നു. ചെറിയ കൂട്ടായ്മകൾക്ക് പരിശീലനവും പരിമിതമായ സാമ്പത്തിക സഹായവും നൽകി വീട്ടുതല സംരംഭങ്ങൾ ആരംഭിക്കാൻ അവസരം നൽകി. പിന്നീട് ഇത് വിവിധ ജില്ലകളിലായി പ്രവർത്തിക്കുന്ന വനിതാ സ്വയംസഹായ സംഘങ്ങളുടെ ശൃംഖലയായി വളർന്നു. എന്നാൽ സമൂഹപ്രവർത്തനം സഹായം നൽകുന്നതിൽ മാത്രം ഒതുങ്ങരുതെന്നാണ് ഗോപാലകൃഷ്ണന്റെ വിശ്വാസം. സഹായം ലഭിക്കുന്നവർ പിന്നീട് മറ്റുള്ളവർക്ക് അവസരങ്ങൾ സൃഷ്ടിക്കുന്നതിൽ പങ്കാളികളാകണം എന്നാണ് അദ്ദേഹം കരുതുന്നത്.

യുവജന പരിപാടികളിലും ഇതേ സമീപനം പ്രതിഫലിച്ചു. വിദ്യാഭ്യാസ ക്യാമ്പുകൾ, സാംസ്കാരിക പരിപാടികൾ, സമൂഹസേവന പ്രവർത്തനങ്ങൾ എന്നിവ സംഘടിപ്പിക്കുന്നതിൽ യുവ സന്നദ്ധപ്രവർത്തകർക്ക് ഉത്തരവാദിത്തം നൽകി. മുതിർന്ന ഭാരവാഹികൾ സ്ഥിരം സംഘാടകരായി തുടരുന്നതിന് പകരം മെന്റർമാരുടെ പങ്ക് ഏറ്റെടുക്കണമെന്നാണ് അദ്ദേഹത്തിന്റെ സമീപനം. വ്യത്യസ്ത പൊതുനിലപാടുകളുള്ള ആളുകളുമായി സംഘടനാ പ്രവർത്തനത്തിൽ ഇടപെടേണ്ട സാഹചര്യങ്ങളും അദ്ദേഹത്തിനുണ്ടായി. SNCDCയുടെ സമൂഹപരിപാടികൾ വ്യത്യസ്ത പശ്ചാത്തലങ്ങളിലും നിലപാടുകളിലും ഉള്ള ആളുകൾക്കും തുറന്നിരിക്കണമെന്നും അദ്ദേഹം നിലനിർത്തി. വിദ്യാഭ്യാസം, സാമൂഹിക മുന്നേറ്റം, പ്രതിനിധാനം, പൊതുസ്ഥാപനങ്ങളിലേക്കുള്ള പ്രവേശനം തുടങ്ങിയ വിഷയങ്ങളിൽ അദ്ദേഹം പൊതുവായി അഭിപ്രായപ്പെട്ടിട്ടുമുണ്ട്.

SNCDCയുടെ പ്രസിഡന്റ്, വിദ്യാഭ്യാസ സമിതി ചെയർമാൻ, കമ്മ്യൂണിറ്റി ഡെവലപ്മെന്റ് പ്രോഗ്രാം കൺവീനർ, സംഘടനയുടെ വിദ്യാഭ്യാസ ട്രസ്റ്റിന്റെ ഭരണസമിതി അംഗം എന്നീ ചുമതലകൾ അദ്ദേഹം വഹിച്ചു. വൊക്കേഷണൽ വിദ്യാഭ്യാസവും സമൂഹവികസനവും സംബന്ധിച്ച ഉപദേശക സമിതികളിലും അദ്ദേഹം പ്രവർത്തിച്ചിട്ടുണ്ട്.

സംഘടനയ്ക്ക് പുറത്തുള്ള ജീവിതം താരതമ്യേന ലളിതമാണ്. ഭാര്യ രാധ വിരമിച്ച അധ്യാപികയാണ്. മക്കൾ വൈദ്യശാസ്ത്ര-വിദ്യാഭ്യാസ മേഖലകളിൽ പ്രവർത്തിക്കുന്നു. പുസ്തകങ്ങൾ, ക്ലാസിക്കൽ മലയാള സാഹിത്യം, തൊഴിൽജീവിതത്തിലേക്ക് കടക്കുന്ന യുവാക്കളുമായുള്ള സംഭാഷണങ്ങൾ എന്നിവയിൽ അദ്ദേഹത്തിന് താൽപര്യമുണ്ട്.

സമീപകാലത്ത് നേതൃത്വ കൈമാറ്റത്തോടുള്ള അദ്ദേഹത്തിന്റെ താൽപര്യം കൂടുതൽ ശക്തമായി. സംഘടന ഒരൊറ്റ വ്യക്തിയുടെ നേതൃത്വത്തെ ആശ്രയിക്കാതെ അടുത്ത തലമുറയിലെ സന്നദ്ധപ്രവർത്തകരെയും വിദ്യാഭ്യാസ പ്രവർത്തകരെയും സമൂഹസംഘാടകരെയും വളർത്താൻ കഴിയണമെന്നാണ് അദ്ദേഹത്തിന്റെ ആഗ്രഹം. കുറച്ച് വിദ്യാർത്ഥികൾക്ക് വിദ്യാഭ്യാസസഹായം നൽകാൻ തുടങ്ങിയ ആദ്യകാല പ്രവർത്തനവും പിന്നീട് ഒരു വലിയ സമൂഹസ്ഥാപനത്തിന്റെ ഉത്തരവാദിത്തം ഏറ്റെടുത്തതും തമ്മിൽ അദ്ദേഹം ഒരു തുടർച്ച കാണുന്നു. ഒരു സംഘടനയുടെ ലക്ഷ്യം ഇന്നത്തെ പ്രശ്നങ്ങൾ പരിഹരിക്കുക മാത്രമല്ല; നാളത്തെ പ്രശ്നങ്ങൾ പരിഹരിക്കാൻ കഴിവുള്ള ആളുകളെ പിന്നിൽ വിട്ടുപോകുകയുമാണെന്ന് അദ്ദേഹം വിശ്വസിക്കുന്നു.',
        ],
    ],

    'p.sreedharan' => [
        'name' => 'P. Sreedharan',
        'tier' => 'distinguished',
        'profession' => 'Community Organisation Leader and Social Representation Advocate',
        'portrait' => 'p_sreedharan.jpg',
        'en' => [
            'title' => 'P. Sreedharan',
            'summary' => 'P. Sreedharan’s public life has developed over several decades through a community organisation that began with mutual assistance and gradually expanded into education, youth development and social representation. His work has been rooted in the belief that community organisations are most effective when they create institutions and opportunities that remain useful beyond the tenure of any one office-bearer.',
            'body' => 'P. Sreedharan’s public life has developed over several decades through a community organisation that began with mutual assistance and gradually expanded into education, youth development and social representation. His work has been rooted in the belief that community organisations are most effective when they create institutions and opportunities that remain useful beyond the tenure of any one office-bearer.

Sreedharan became involved in community activities at a time when many families in his area were seeking better access to education and stable employment. What began as a small scholarship and assistance programme eventually developed into a wider network of volunteers. He helped establish systems for identifying students who required support, maintaining transparent records and involving local donors in a structured way.

Education remained central to the organisation’s development. Scholarships were later supplemented by career guidance, study materials and orientation programmes for young people who were the first in their families to pursue higher education. Sreedharan encouraged the organisation to move gradually from providing assistance to building confidence and capacity among beneficiaries.

Another major area of his work has been community representation. He has participated in consultations concerning access to public institutions, local development, welfare services and the concerns of communities that may not always have an organised channel through which to present their views. His approach has generally been to prepare a clear representation, seek dialogue and maintain a record of what was promised and what was actually implemented.

Over the years, he has also encouraged younger members to take on organisational responsibilities. Committees were created around education, welfare, youth activities and community development, with senior office-bearers acting increasingly as mentors. This was partly a response to a problem he had observed in many voluntary organisations: institutions can become overly dependent on a small group of experienced individuals.

Sreedharan has therefore placed unusual emphasis on succession. He believes that a community organisation should be capable of producing new organisers who understand both the history of the institution and the changing needs of the people it serves. He has supported leadership training, documentation of organisational decisions and greater participation by younger members and women.

His contribution lies not in a single campaign or project but in the gradual strengthening of a community institution. For Sreedharan, representation becomes meaningful when it leads to participation, and participation becomes durable when people acquire the confidence and organisational knowledge to continue the work themselves.',
        ],
        'ml' => [
            'title' => 'P. Sreedharan',
            'summary' => 'പി. ശ്രീധരന്റെ പൊതുജീവിതം നിരവധി ദശാബ്ദങ്ങളായി പരസ്പരസഹായത്തിൽ നിന്ന് വിദ്യാഭ്യാസം, യുവജനവികസനം, സാമൂഹിക പ്രതിനിധാനം എന്നിവയിലേക്ക് വളർന്ന ഒരു സമൂഹസംഘടനയിലൂടെയാണ് രൂപപ്പെട്ടത്. ഒരു വ്യക്തിയുടെ പദവിക്കപ്പുറം സമൂഹത്തിന് ദീർഘകാലം പ്രയോജനപ്പെടുന്ന സ്ഥാപനങ്ങളും അവസരങ്ങളും സൃഷ്ടിക്കുമ്പോഴാണ് സമൂഹസംഘടനകൾ കൂടുതൽ ഫലപ്രദമാകുന്നതെന്ന വിശ്വാസമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ അടിസ്ഥാനം.',
            'body' => 'പി. ശ്രീധരന്റെ പൊതുജീവിതം നിരവധി ദശാബ്ദങ്ങളായി പരസ്പരസഹായത്തിൽ നിന്ന് വിദ്യാഭ്യാസം, യുവജനവികസനം, സാമൂഹിക പ്രതിനിധാനം എന്നിവയിലേക്ക് വളർന്ന ഒരു സമൂഹസംഘടനയിലൂടെയാണ് രൂപപ്പെട്ടത്. ഒരു വ്യക്തിയുടെ പദവിക്കപ്പുറം സമൂഹത്തിന് ദീർഘകാലം പ്രയോജനപ്പെടുന്ന സ്ഥാപനങ്ങളും അവസരങ്ങളും സൃഷ്ടിക്കുമ്പോഴാണ് സമൂഹസംഘടനകൾ കൂടുതൽ ഫലപ്രദമാകുന്നതെന്ന വിശ്വാസമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ അടിസ്ഥാനം.

അദ്ദേഹത്തിന്റെ പ്രദേശത്തെ നിരവധി കുടുംബങ്ങൾ വിദ്യാഭ്യാസത്തിനും സ്ഥിരതയുള്ള തൊഴിൽ അവസരങ്ങൾക്കും കൂടുതൽ പ്രവേശനം തേടിയിരുന്ന കാലത്താണ് ശ്രീധരൻ സമൂഹപ്രവർത്തനങ്ങളിൽ സജീവമായത്. ചെറിയൊരു വിദ്യാഭ്യാസസഹായ-സ്കോളർഷിപ്പ് പ്രവർത്തനമായി തുടങ്ങിയ ശ്രമം പിന്നീട് സന്നദ്ധപ്രവർത്തകരുടെ വിശാലമായ ശൃംഖലയായി വളർന്നു. സഹായം ആവശ്യമുള്ള വിദ്യാർത്ഥികളെ കണ്ടെത്തുക, രേഖകൾ ക്രമമായി സൂക്ഷിക്കുക, പ്രാദേശിക സഹായകരെ കൂടുതൽ ക്രമബദ്ധമായി പങ്കാളികളാക്കുക എന്നിവയ്ക്കുള്ള സംവിധാനങ്ങൾ രൂപപ്പെടുത്തുന്നതിൽ അദ്ദേഹം പങ്കെടുത്തു.

വിദ്യാഭ്യാസം സംഘടനയുടെ പ്രവർത്തനത്തിൽ തുടർച്ചയായി പ്രധാന സ്ഥാനത്ത് നിന്നു. സ്കോളർഷിപ്പുകൾക്ക് പുറമെ തൊഴിൽ-പഠന മാർഗനിർദ്ദേശം, പഠനസാമഗ്രികൾ, കുടുംബത്തിൽ ആദ്യമായി ഉയർന്ന വിദ്യാഭ്യാസത്തിലേക്ക് കടക്കുന്ന യുവാക്കൾക്കായുള്ള പരിചയപ്പെടുത്തൽ പരിപാടികൾ എന്നിവയും പിന്നീട് നടന്നു. സഹായം നൽകുന്നതിൽ നിന്ന് സഹായം ലഭിക്കുന്നവരുടെ ആത്മവിശ്വാസവും കഴിവും വളർത്തുന്നതിലേക്ക് പ്രവർത്തനം മാറണമെന്ന് ശ്രീധരൻ പ്രോത്സാഹിപ്പിച്ചു.

സമൂഹ പ്രതിനിധാനമാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിലെ മറ്റൊരു പ്രധാന മേഖല. പൊതുസ്ഥാപനങ്ങളിലേക്കുള്ള പ്രവേശനം, പ്രാദേശിക വികസനം, ക്ഷേമസേവനങ്ങൾ, സംഘടിതമായ ശബ്ദം ലഭിക്കാത്ത സമൂഹങ്ങളുടെ ആവശ്യങ്ങൾ എന്നിവയുമായി ബന്ധപ്പെട്ട ചർച്ചകളിൽ അദ്ദേഹം പങ്കെടുത്തു. വ്യക്തമായ നിവേദനം തയ്യാറാക്കുക, സംവാദത്തിന് ശ്രമിക്കുക, വാഗ്ദാനം ചെയ്ത കാര്യങ്ങളും നടപ്പാക്കിയ കാര്യങ്ങളും രേഖപ്പെടുത്തുക എന്നതാണ് പൊതുവെ അദ്ദേഹത്തിന്റെ രീതി.

യുവ അംഗങ്ങളെ സംഘടനാ ഉത്തരവാദിത്തങ്ങളിലേക്ക് കൊണ്ടുവരുന്നതിനും അദ്ദേഹം വർഷങ്ങളായി ശ്രദ്ധ നൽകി. വിദ്യാഭ്യാസം, ക്ഷേമം, യുവജന പ്രവർത്തനം, സമൂഹവികസനം തുടങ്ങിയ മേഖലകൾക്കായി കമ്മിറ്റികൾ രൂപീകരിക്കുകയും മുതിർന്ന ഭാരവാഹികൾ ക്രമേണ മെന്റർമാരുടെ പങ്ക് ഏറ്റെടുക്കുകയും ചെയ്തു. പരിചയസമ്പന്നരായ കുറച്ച് ആളുകളെ മാത്രം ആശ്രയിക്കുന്നതുകൊണ്ട് സന്നദ്ധസംഘടനകൾ ദുർബലമാകാമെന്ന അനുഭവമാണ് ഇതിന് പിന്നിൽ.

അതുകൊണ്ടുതന്നെ നേതൃത്വ കൈമാറ്റത്തിന് ശ്രീധരൻ പ്രത്യേക പ്രാധാന്യം നൽകി. സ്ഥാപനത്തിന്റെ ചരിത്രവും സേവിക്കുന്ന സമൂഹത്തിന്റെ മാറുന്ന ആവശ്യങ്ങളും മനസ്സിലാക്കുന്ന പുതിയ സംഘാടകരെ വളർത്താൻ സമൂഹസംഘടനയ്ക്ക് കഴിയണം എന്നാണ് അദ്ദേഹത്തിന്റെ വിശ്വാസം. നേതൃപരിശീലനം, സംഘടനാ തീരുമാനങ്ങളുടെ രേഖപ്പെടുത്തൽ, യുവാക്കളുടെയും സ്ത്രീകളുടെയും കൂടുതൽ പങ്കാളിത്തം എന്നിവയ്ക്ക് അദ്ദേഹം പിന്തുണ നൽകി.

ഒരു പ്രത്യേക പ്രചാരണത്തിലോ പദ്ധതിയിലോ മാത്രം ഒതുങ്ങാത്ത, ഒരു സമൂഹസ്ഥാപനത്തെ ക്രമേണ ശക്തിപ്പെടുത്തിയ പ്രവർത്തനമാണ് ശ്രീധരന്റെ സംഭാവന. പ്രതിനിധാനം ജനപങ്കാളിത്തത്തിലേക്ക് നയിക്കുമ്പോഴാണ് അതിന് അർത്ഥമുണ്ടാകുന്നത്; ആളുകൾക്ക് സ്വന്തം പ്രവർത്തനം തുടരാനുള്ള ആത്മവിശ്വാസവും സംഘടനാ അറിവും ലഭിക്കുമ്പോഴാണ് ആ പങ്കാളിത്തം ദീർഘകാലം നിലനിൽക്കുന്നതെന്നും അദ്ദേഹം വിശ്വസിക്കുന്നു.',
        ],
    ],

    'v.suresh.babu' => [
        'name' => 'V. Suresh Babu',
        'tier' => 'distinguished',
        'profession' => 'Workers’ Movement and Community Development Leader',
        'portrait' => 'v_suresh_babu.jpg',
        'en' => [
            'title' => 'V. Suresh Babu',
            'summary' => 'V. Suresh Babu’s public life has developed at the intersection of workers’ organisation, neighbourhood development and community welfare. Over a long period of grassroots work, he has moved between workplace concerns and wider community issues, arguing that the wellbeing of working families cannot be separated from the quality of the places in which they live.',
            'body' => 'V. Suresh Babu’s public life has developed at the intersection of workers’ organisation, neighbourhood development and community welfare. Over a long period of grassroots work, he has moved between workplace concerns and wider community issues, arguing that the wellbeing of working families cannot be separated from the quality of the places in which they live.

Suresh began his organisational life through workers’ meetings and local welfare activities. He gradually took responsibility for coordinating members across several workplaces and later became involved in community programmes concerning housing, education, public services and emergency assistance. His experience led him to favour practical organisation over highly centralised decision-making.

One of his important initiatives was the creation of a community assistance network that brought together workers, retired employees and local volunteers. The network helped families during periods of sudden financial difficulty and also supported educational needs of children. Suresh insisted that assistance should be accompanied by information and guidance so that families could understand what other institutional support might be available to them.

He has also been involved in discussions around housing and basic services in working-class neighbourhoods. Instead of treating each problem as an isolated complaint, he encouraged residents to document common issues, identify the responsible agencies and work collectively. This approach sometimes required patience because improvements in public infrastructure rarely occurred quickly.

As his responsibilities grew, Suresh became increasingly concerned with institutional continuity. He supported training programmes for younger organisers and encouraged women members to take more active roles in committees and community initiatives. He believes that an organisation becomes stronger when leadership is distributed and when ordinary members are able to understand both the purpose and the limits of the institution.

His years of work have also exposed him to disagreements within organisations. He regards disagreement as a normal part of collective life and prefers structured discussion to personal confrontation. The ability to listen to different views, record decisions and return to an agreed objective has been one of the principles he has tried to reinforce.

Suresh’s contribution represents a form of public leadership that is built through sustained organisation rather than a single visible moment. His emphasis remains on dignity at work, stronger communities and the development of people capable of carrying collective institutions forward.',
        ],
        'ml' => [
            'title' => 'V. Suresh Babu',
            'summary' => 'വി. സുരേഷ് ബാബുവിന്റെ പൊതുജീവിതം തൊഴിലാളി സംഘടനാ പ്രവർത്തനം, പ്രദേശവികസനം, സമൂഹക്ഷേമം എന്നിവയുടെ സംഗമത്തിലൂടെയാണ് വളർന്നത്. ദീർഘകാലത്തെ അടിത്തറതല പ്രവർത്തനത്തിനിടെ ജോലിസ്ഥലത്തെ വിഷയങ്ങളും വിശാലമായ സാമൂഹിക പ്രശ്നങ്ങളും ഒരുമിച്ച് കാണുന്ന സമീപനമാണ് അദ്ദേഹം സ്വീകരിച്ചത്. ജോലി ചെയ്യുന്ന കുടുംബങ്ങളുടെ ക്ഷേമം അവർ ജീവിക്കുന്ന പ്രദേശങ്ങളുടെ നിലവാരത്തിൽ നിന്ന് വേർതിരിക്കാനാവില്ലെന്നാണ് അദ്ദേഹത്തിന്റെ നിലപാട്.',
            'body' => 'വി. സുരേഷ് ബാബുവിന്റെ പൊതുജീവിതം തൊഴിലാളി സംഘടനാ പ്രവർത്തനം, പ്രദേശവികസനം, സമൂഹക്ഷേമം എന്നിവയുടെ സംഗമത്തിലൂടെയാണ് വളർന്നത്. ദീർഘകാലത്തെ അടിത്തറതല പ്രവർത്തനത്തിനിടെ ജോലിസ്ഥലത്തെ വിഷയങ്ങളും വിശാലമായ സാമൂഹിക പ്രശ്നങ്ങളും ഒരുമിച്ച് കാണുന്ന സമീപനമാണ് അദ്ദേഹം സ്വീകരിച്ചത്. ജോലി ചെയ്യുന്ന കുടുംബങ്ങളുടെ ക്ഷേമം അവർ ജീവിക്കുന്ന പ്രദേശങ്ങളുടെ നിലവാരത്തിൽ നിന്ന് വേർതിരിക്കാനാവില്ലെന്നാണ് അദ്ദേഹത്തിന്റെ നിലപാട്.

തൊഴിലാളി യോഗങ്ങളിലൂടെയും പ്രാദേശിക ക്ഷേമ പ്രവർത്തനങ്ങളിലൂടെയുമാണ് സുരേഷ് സംഘടനാ പ്രവർത്തനത്തിലേക്ക് എത്തിയത്. പല ജോലിസ്ഥലങ്ങളിലെ അംഗങ്ങളുടെ ഏകോപന ചുമതലകൾ ഏറ്റെടുത്തശേഷം താമസം, വിദ്യാഭ്യാസം, പൊതുസേവനങ്ങൾ, അടിയന്തര സഹായം തുടങ്ങിയ വിഷയങ്ങളിലുള്ള സമൂഹപരിപാടികളിലും അദ്ദേഹം സജീവമായി. അതിലൂടെ കൂടുതൽ കേന്ദ്രീകൃതമായ തീരുമാനങ്ങളേക്കാൾ പ്രായോഗികമായ കൂട്ടായ പ്രവർത്തനത്തിനാണ് അദ്ദേഹം പ്രാധാന്യം നൽകിയത്.

തൊഴിലാളികൾ, വിരമിച്ച ജീവനക്കാർ, പ്രാദേശിക സന്നദ്ധപ്രവർത്തകർ എന്നിവരെ ഒരുമിപ്പിച്ച ഒരു സമൂഹസഹായ ശൃംഖല രൂപപ്പെടുത്തിയത് അദ്ദേഹത്തിന്റെ പ്രധാന സംരംഭങ്ങളിലൊന്നാണ്. അപ്രതീക്ഷിത സാമ്പത്തിക ബുദ്ധിമുട്ടുകൾ നേരിടുന്ന കുടുംബങ്ങൾക്ക് സഹായം നൽകുന്നതിനൊപ്പം കുട്ടികളുടെ വിദ്യാഭ്യാസ ആവശ്യങ്ങൾക്കും പിന്തുണ നൽകി. സഹായത്തോടൊപ്പം ലഭ്യമായ മറ്റ് സ്ഥാപനപരമായ പിന്തുണകളെക്കുറിച്ചുള്ള വിവരവും മാർഗനിർദ്ദേശവും കുടുംബങ്ങൾക്ക് ലഭിക്കണം എന്നത് സുരേഷ് ഉറപ്പാക്കാൻ ശ്രമിച്ചു.

തൊഴിലാളി പ്രദേശങ്ങളിലെ താമസവും അടിസ്ഥാനസൗകര്യങ്ങളും സംബന്ധിച്ച ചർച്ചകളിലും അദ്ദേഹം പങ്കെടുത്തു. ഓരോ പ്രശ്നത്തെയും ഒറ്റപ്പെട്ട പരാതിയായി കാണാതെ പൊതുവായി ബാധിക്കുന്ന വിഷയങ്ങൾ രേഖപ്പെടുത്താനും ഉത്തരവാദിത്തമുള്ള സ്ഥാപനങ്ങളെ കണ്ടെത്താനും കൂട്ടായി പ്രവർത്തിക്കാനും നാട്ടുകാരെ പ്രോത്സാഹിപ്പിച്ചു. പൊതുസൗകര്യങ്ങളിലെ മാറ്റങ്ങൾ പലപ്പോഴും വേഗത്തിൽ ഉണ്ടാകാത്തതിനാൽ ക്ഷമയും തുടർച്ചയും ആവശ്യമായി വന്നു.

ഉത്തരവാദിത്തങ്ങൾ വർധിച്ചതോടെ സംഘടനയുടെ തുടർച്ചയെക്കുറിച്ചും സുരേഷ് കൂടുതൽ ശ്രദ്ധിച്ചു. യുവ സംഘാടകർക്ക് പരിശീലനം നൽകുകയും വനിതാ അംഗങ്ങളെ കമ്മിറ്റികളിലും സമൂഹസംരംഭങ്ങളിലും കൂടുതൽ സജീവമാക്കുകയും ചെയ്തു. നേതൃത്വം ചില വ്യക്തികളിൽ മാത്രം കേന്ദ്രീകരിക്കാതെ പങ്കിട്ടിരിക്കുമ്പോഴും സാധാരണ അംഗങ്ങൾക്ക് സ്ഥാപനത്തിന്റെ ലക്ഷ്യവും പരിധികളും മനസ്സിലാകുമ്പോഴും സംഘടന കൂടുതൽ ശക്തമാകുമെന്ന് അദ്ദേഹം വിശ്വസിക്കുന്നു.

സംഘടനകൾക്കുള്ളിലെ അഭിപ്രായവ്യത്യാസങ്ങളും അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ ഭാഗമായിരുന്നു. കൂട്ടായ ജീവിതത്തിൽ അഭിപ്രായവ്യത്യാസം സ്വാഭാവികമാണെന്നും വ്യക്തിപരമായ ഏറ്റുമുട്ടലിനേക്കാൾ ക്രമബദ്ധമായ ചർച്ചയാണ് പ്രയോജനകരമെന്നും അദ്ദേഹം കരുതുന്നു. വ്യത്യസ്ത അഭിപ്രായങ്ങൾ കേൾക്കുക, തീരുമാനങ്ങൾ രേഖപ്പെടുത്തുക, പൊതുവായി അംഗീകരിച്ച ലക്ഷ്യത്തിലേക്ക് വീണ്ടും മടങ്ങുക എന്നിവയ്ക്ക് അദ്ദേഹം പ്രാധാന്യം നൽകി.

ഒരു പ്രത്യേക ശ്രദ്ധേയ നിമിഷത്തേക്കാൾ തുടർച്ചയായ സംഘടനാ പ്രവർത്തനത്തിലൂടെ രൂപപ്പെട്ട പൊതുനേതൃത്വത്തിന്റെ മാതൃകയാണ് സുരേഷ് ബാബുവിന്റെ സംഭാവന. ജോലിസ്ഥലത്തെ മാന്യത, ശക്തമായ സമൂഹങ്ങൾ, കൂട്ടായ സ്ഥാപനങ്ങളെ മുന്നോട്ടുകൊണ്ടുപോകാൻ കഴിയുന്ന പുതിയ ആളുകളുടെ വളർച്ച എന്നിവയാണ് അദ്ദേഹത്തിന്റെ പ്രവർത്തനത്തിന്റെ പ്രധാന ആശയങ്ങൾ.',
        ],
    ],
];
