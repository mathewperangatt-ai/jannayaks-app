<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ISOLATED DESIGN CONCEPT — profile page exploration.
 *
 * Completely self-contained: static specimen data (the approved Anil
 * Varma benchmark profile), no database reads, no authentication, no
 * dependency on the production profile views, services or layout.
 * Exists solely so a fresh profile design can be evaluated in the
 * browser against the current production design before any adoption
 * decision. Not linked from anywhere; no production surface depends
 * on it. Removing this controller + view + route removes the concept
 * entirely.
 */
class ProfileConceptController extends Controller
{
    public function __invoke(Request $request): View
    {
        $lang = $request->query('lang') === 'ml' ? 'ml' : 'en';

        return view('concept.profile-concept', [
            'lang' => $lang,
            'specimen' => $this->specimen(),
        ]);
    }

    /**
     * Specimen: Anil Varma benchmark (approved corpus, sections 30 of the
     * Master Editorial Specification). Text is used verbatim from the
     * approved benchmark; structure below is presentation-side only.
     *
     * @return array<string, mixed>
     */
    private function specimen(): array
    {
        return [
            'slug' => 'anil.varma',
            'en' => [
                'name' => 'Anil Varma',
                'credentials' => 'Municipal Councillor & Member of the Legislative Assembly',
                'field' => 'Public Administration',
                'organisation' => 'Municipal Council · State Legislative Assembly',
                'intro' => 'Anil Varma entered public life through the practical world of municipal administration, where questions about roads, waste collection and public facilities were rarely abstract.',
                'biography' => [
                    'A former civil-construction professional who spent more than a decade managing projects in Kochi, Varma first became involved in local affairs after residents in his neighbourhood struggled for years with poor road access and inadequate drainage. What began as an effort to get a local problem addressed eventually led him to municipal politics, and later to the state legislature.',
                    'Born in Thrissur and educated in economics, Varma began his career with a construction company before moving into project administration. His work took him across Kerala and involved coordinating contractors, engineers and local authorities. He later became regional operations head of a mid-sized infrastructure company. The experience gave him an understanding of how delays occur between a decision being taken and a project actually reaching the ground.',
                    'His entry into public life came without a political position. A residential area on the outskirts of his town had repeatedly faced problems with access during the monsoon. Several residents had raised the issue with different authorities, but responsibility for the road and drainage infrastructure was divided between agencies. Varma began helping residents document the problem and identify which parts came under which authority. The exercise eventually resulted in a coordinated representation and improvements to the road.',
                    'The experience changed his understanding of local administration. A problem that appeared simple to residents could involve several departments, different budgets and competing priorities. He began attending local meetings more regularly and eventually agreed to contest a municipal election as an independent-supported candidate.',
                    'As a councillor, Varma\'s responsibilities expanded beyond the issues that had first brought him into public life. He worked on road maintenance, waste management and public facilities, while also becoming involved in the redevelopment of a traditional market in the town. The market project proved more complicated than expected. Traders were concerned that construction would disrupt their businesses, while the municipal administration wanted the work completed within a fixed schedule.',
                    'Varma\'s approach was to revise the implementation plan rather than insist on the original timetable. Discussions with traders and municipal officials resulted in the work being divided into stages so that parts of the market could remain operational while construction continued elsewhere. The arrangement extended the overall schedule, but allowed more businesses to remain open during the redevelopment.',
                    'That experience became one of the episodes he later referred to when speaking about public decision-making. For Varma, administration is not simply about choosing a solution; it is also about explaining a decision to people who may have legitimate reasons for disagreeing with it.',
                    'His interests gradually widened to include vocational education and opportunities for small businesses. As an MLA, he supported a programme that brought industrial units and technical institutions together to provide practical training for young people who had not pursued conventional university education. The programme was modest in scale, but Varma saw it as a way of connecting education more directly with the skills employers actually needed.',
                    'Not every initiative moved smoothly. A drinking-water project in one part of his constituency was delayed by difficulties involving land acquisition and tender procedures. The delay led to criticism from residents who had expected the project to be completed earlier. Varma worked with officials on a phased approach, allowing portions of the project to proceed while the remaining administrative issues were addressed.',
                    'He describes the episode as one of the more useful lessons of his public career. Projects, he says, are often discussed as if a decision automatically produces a result. In practice, implementation depends on a chain of people and institutions, and a public representative has to keep working through that chain when something goes wrong.',
                    'His political career developed alongside these responsibilities. After his first term as a councillor, Varma joined the fictional People\'s Development Party and was subsequently elected to the state assembly. He later served as Parliamentary Secretary for Local Development and took part in assembly and administrative committees dealing with local infrastructure and public services. He has also held organisational responsibilities within the party at the district level.',
                    'The progression from municipal politics to the assembly did not entirely change his working style. He remains particularly interested in local infrastructure, small businesses, vocational education and the functioning of municipal institutions. His professional background continues to influence the way he approaches these subjects: he tends to ask what is preventing an idea from being implemented and which part of the process needs to change.',
                ],
                'closing' => [
                    'Outside politics, Varma\'s life is closely connected to his family. His wife, Meera, is a teacher, while their daughter is a civil engineer and their son is pursuing postgraduate medical studies. He continues to spend time with young volunteers and community groups, particularly when discussing vocational education and local development.',
                    'Looking back, Varma sees his public journey less as a series of positions than as a gradual widening of responsibility. A road and drainage problem first brought him into local public affairs; municipal administration taught him the complexity of translating decisions into results; and the experience eventually took him into wider public responsibilities.',
                    'For him, the question that remains at the centre of public work is a simple one: when a problem has occupied people\'s lives for years, did their lives actually become a little easier because you chose to get involved?',
                ],
                'career' => [
                    ['Kerala, construction sector', 'Began with a construction company; moved into project administration coordinating contractors, engineers and local authorities across the State.'],
                    ['Regional operations head', 'Led operations for a mid-sized infrastructure company before entering public life.'],
                    ['Municipal councillor', 'Road maintenance, waste management, public facilities; led the phased redevelopment of a traditional market.'],
                    ['State legislature', 'Elected to the state assembly; served as Parliamentary Secretary for Local Development; assembly and administrative committee responsibilities.'],
                ],
                'contributions' => [
                    'Road and drainage campaign — helped residents document a divided-responsibility infrastructure problem into a coordinated representation.',
                    'Traditional market redevelopment — revised implementation into stages so businesses stayed operational through construction.',
                    ['Vocational education programme', 'Brought industrial units and technical institutions together for practical training.'],
                    'Drinking-water project — phased approach through land-acquisition and tender difficulties.',
                ],
                'achievements' => [
                    'Phased market redevelopment completed while keeping trading businesses open',
                    'Vocational training programme linking industry with technical institutions',
                    'Coordinated inter-agency road and drainage improvements',
                    'Phased delivery of a delayed drinking-water project',
                    'Parliamentary Secretary for Local Development',
                ],
                'milestones' => [
                    'Construction profession — a decade of project management in Kochi',
                    'Residents\' road-and-drainage campaign',
                    'Municipal election as an independent-supported candidate',
                    'First term as municipal councillor',
                    'Election to the state assembly',
                    'Parliamentary Secretary for Local Development',
                ],
                'labels' => [
                    'archive' => 'Jannayaks · Biographical Archive',
                    'biography' => 'Biography',
                    'career' => 'Career & Responsibilities',
                    'contributions' => 'Public Contributions',
                    'achievements' => 'Achievements',
                    'gallery' => 'Gallery',
                    'milestones' => 'Milestones',
                    'education' => 'Education & Early Career',
                    'education_body' => 'Born in Thrissur and educated in economics, Varma began his career with a construction company before moving into project administration.',
                    'url_label' => 'Permanent profile address',
                    'copy' => 'Copy link',
                    'copied' => 'Link copied',
                    'share' => 'Share',
                    'specimen' => 'Specimen profile · Design concept — not a published profile',
                    'portrait_caption' => 'Portrait · Archival plate',
                    'plate_i' => 'Documentation photograph · Archival plate',
                    'plate_ii' => 'Constituency work · Archival plate',
                    'plate_iii' => 'Assembly session · Archival plate',
                    'plate_iv' => 'Field visit · Archival plate',
                ],
            ],
            'ml' => [
                'name' => 'അനിൽ വർമ്മ',
                'name_sub' => 'Anil Varma',
                'credentials' => 'മുനിസിപ്പൽ കൗൺസിലർ · എം.എൽ.എ · പൊതുഭരണ പ്രവർത്തകൻ',
                'field' => 'പൊതുഭരണം',
                'organisation' => 'മുനിസിപ്പൽ കൗൺസിൽ · സംസ്ഥാന നിയമസഭ',
                'intro' => 'റോഡുകൾ, മാലിന്യസംസ്കരണം, പൊതുസൗകര്യങ്ങൾ തുടങ്ങിയ വിഷയങ്ങൾ രാഷ്ട്രീയ ചർച്ചകളിലെ വലിയ ആശയങ്ങളാകുന്നതിന് മുമ്പ് തന്നെ അവ ദിവസേന നേരിടുന്ന പ്രായോഗിക പ്രശ്നങ്ങളാണെന്ന് അനിൽ വർമ്മ തന്റെ പ്രൊഫഷണൽ ജീവിതത്തിൽനിന്നും പൊതുപ്രവർത്തനത്തിൽനിന്നും മനസ്സിലാക്കി. കൊച്ചിയിൽ നിർമ്മാണരംഗത്ത് ഒരു ദശാബ്ദത്തിലേറെ പ്രോജക്ട് മാനേജ്മെന്റിൽ പ്രവർത്തിച്ച അദ്ദേഹം പിന്നീട് മുനിസിപ്പൽ കൗൺസിലറായും നിയമസഭാംഗമായും പ്രവർത്തിച്ചു.',
                'biography' => [
                    'തൃശൂരിൽ ജനിച്ച വർമ്മ സാമ്പത്തികശാസ്ത്രം പഠിച്ചശേഷം നിർമ്മാണ സ്ഥാപനത്തിൽ ജോലി ആരംഭിച്ചു. പിന്നീട് പ്രോജക്ട് അഡ്മിനിസ്ട്രേഷനിലേക്ക് മാറി. എൻജിനീയർമാർ, കരാറുകാർ, പ്രാദേശിക അധികൃതർ തുടങ്ങിയവരുമായി ഏകോപനം നടത്തുന്നതായിരുന്നു അദ്ദേഹത്തിന്റെ പ്രധാന ചുമതലകൾ. പിന്നീട് ഒരു ഇടത്തരം ഇൻഫ്രാസ്ട്രക്ചർ സ്ഥാപനത്തിന്റെ റീജിയണൽ ഓപ്പറേഷൻസ് ഹെഡായി.',
                    'ഒരു പ്രദേശത്തെ റോഡിനും ഡ്രെയിനേജിനുമുണ്ടായിരുന്ന ദീർഘകാല പ്രശ്നമാണ് അദ്ദേഹത്തെ പൊതുപ്രവർത്തനത്തിലേക്ക് അടുപ്പിച്ചത്. പലരും പല വകുപ്പുകളെയും സമീപിച്ചിരുന്നെങ്കിലും ഉത്തരവാദിത്തം പല സ്ഥാപനങ്ങൾക്കിടയിൽ വിഭജിക്കപ്പെട്ടിരുന്നതിനാൽ പ്രശ്നം നീണ്ടുപോയി. വർമ്മ നാട്ടുകാരോടൊപ്പം പ്രശ്നം രേഖപ്പെടുത്തുകയും ഓരോ ഭാഗത്തിനും ഉത്തരവാദിത്തമുള്ള സ്ഥാപനങ്ങളെ തിരിച്ചറിയുകയും ചെയ്തു.',
                    'അവസാനം പ്രശ്നം അധികൃതരുടെ ശ്രദ്ധയിൽപ്പെട്ടതോടെ റോഡിൽ മെച്ചപ്പെടുത്തലുകൾ നടന്നു.',
                    'ഈ അനുഭവമാണ് പ്രാദേശിക ഭരണത്തെക്കുറിച്ചുള്ള അദ്ദേഹത്തിന്റെ കാഴ്ചപ്പാടിനെ മാറ്റിയത്. നാട്ടുകാർക്ക് ലളിതമായി തോന്നുന്ന ഒരു പ്രശ്നത്തിന് പിന്നിൽ പല വകുപ്പുകളും ബജറ്റുകളും നടപടിക്രമങ്ങളും ഉണ്ടാകാമെന്ന് അദ്ദേഹം തിരിച്ചറിഞ്ഞു.',
                    'തുടർന്ന് പ്രാദേശിക യോഗങ്ങളിൽ സജീവമായി പങ്കെടുക്കുകയും സ്വതന്ത്ര പിന്തുണയോടെ മുനിസിപ്പൽ തിരഞ്ഞെടുപ്പിൽ മത്സരിക്കുകയും ചെയ്തു.',
                    'കൗൺസിലറായിരിക്കെ റോഡ് പരിപാലനം, മാലിന്യസംസ്കരണം, പൊതുസൗകര്യങ്ങൾ തുടങ്ങിയ വിഷയങ്ങളിൽ പ്രവർത്തിച്ചു. പിന്നീട് നഗരത്തിലെ ഒരു പഴയ മാർക്കറ്റിന്റെ പുനർവികസനത്തിലും പങ്കെടുത്തു.',
                    'മാർക്കറ്റ് പദ്ധതിയിൽ വ്യാപാരികൾക്ക് നിർമാണപ്രവർത്തനത്തെ തുടർന്ന് ബിസിനസ് തടസ്സപ്പെടുമെന്ന ആശങ്കയുണ്ടായിരുന്നു. അധികൃതർക്ക് നിശ്ചിത സമയപരിധിക്കുള്ളിൽ പദ്ധതി പൂർത്തിയാക്കേണ്ടതുമുണ്ടായിരുന്നു. വ്യാപാരികളുമായും ഉദ്യോഗസ്ഥരുമായും നടത്തിയ ചർച്ചകൾക്ക് ശേഷം പദ്ധതി ഘട്ടംഘട്ടമായി നടപ്പാക്കാൻ തീരുമാനിച്ചു. നിർമ്മാണം നടക്കുന്ന സമയത്തും മാർക്കറ്റിന്റെ ചില ഭാഗങ്ങൾ പ്രവർത്തിക്കാൻ സാധിച്ചു.',
                    'പൊതുപ്രവർത്തനത്തിലെ ഒരു പ്രധാന പാഠമായി വർമ്മ പിന്നീട് ഈ അനുഭവത്തെ കാണിച്ചു. ഒരു തീരുമാനം എടുക്കുന്നത് മാത്രം പോരാ; അതിനെ എതിര്ക്കുന്ന ആളുകൾക്ക് പോലും അതിന്റെ കാരണം വിശദീകരിക്കേണ്ടത് പൊതുഭരണത്തിന്റെ ഭാഗമാണെന്ന് അദ്ദേഹം കരുതുന്നു.',
                    'തുടർന്ന് യുവാക്കൾക്കുള്ള തൊഴിൽപരിശീലനത്തിലേക്കും ചെറിയ ബിസിനസുകൾക്കുള്ള അവസരങ്ങളിലേക്കും അദ്ദേഹത്തിന്റെ ശ്രദ്ധ നീങ്ങി. വ്യവസായ സ്ഥാപനങ്ങളെയും സാങ്കേതിക വിദ്യാഭ്യാസ സ്ഥാപനങ്ങളെയും കൂട്ടിചേർത്ത് പ്രായോഗിക പരിശീലനം നൽകുന്ന ഒരു പരിപാടിക്ക് അദ്ദേഹം പിന്തുണ നൽകി.',
                    'എല്ലാ പദ്ധതികളും ഒരേ വേഗത്തിൽ മുന്നോട്ടുപോയില്ല. ഒരു കുടിവെള്ള പദ്ധതി ഭൂമി, ടെൻഡർ നടപടികൾ തുടങ്ങിയ പ്രശ്നങ്ങൾ കാരണം വൈകി. താമസത്തെക്കുറിച്ച് നാട്ടുകാരിൽനിന്ന് വിമർശനവും ഉയർന്നു. വർമ്മ ഉദ്യോഗസ്ഥരുമായി ചേർന്ന് ഘട്ടംഘട്ടമായി പദ്ധതി നടപ്പാക്കാനുള്ള മാർഗം കണ്ടെത്തി.',
                    'ഒരു പദ്ധതി പ്രഖ്യാപിച്ചാൽ അത് സ്വാഭാവികമായി ഫലത്തിലേക്ക് എത്തുമെന്ന് കരുതാനാവില്ലെന്ന് ഈ അനുഭവം അദ്ദേഹത്തെ വീണ്ടും ഓർമ്മിപ്പിച്ചു. പല സ്ഥാപനങ്ങളുടെയും ആളുകളുടെയും ഇടയിലൂടെ പ്രവർത്തനം മുന്നോട്ടുകൊണ്ടുപോകേണ്ടതുണ്ട്.',
                    'മുനിസിപ്പൽ കൗൺസിലറായും തുടർന്ന് നിയമസഭാംഗമായും പ്രവർത്തിച്ച വർമ്മ പിന്നീട് പ്രാദേശിക വികസനത്തിന്റെ ചുമതലയുള്ള പാർലമെന്ററി സെക്രട്ടറിയായും പ്രവർത്തിച്ചു. പാർട്ടിയുടെ ജില്ലാ തല ഉത്തരവാദിത്തങ്ങളും നിയമസഭാ സമിതി പ്രവർത്തനങ്ങളും അദ്ദേഹത്തിന്റെ പൊതുജീവിതത്തിന്റെ ഭാഗമായിട്ടുണ്ട്.',
                ],
                'closing' => [
                    'രാഷ്ട്രീയത്തിന് പുറത്തുള്ള ജീവിതത്തിൽ ഭാര്യ മീര അധ്യാപികയാണ്. മകൾ സിവിൽ എൻജിനീയറും മകൻ മെഡിസിനിൽ ഉപരിപഠനം നടത്തുകയും ചെയ്യുന്നു.',
                    'പൊതുജീവിതത്തിലെ തന്റെ യാത്രയെ പദവികളുടെ ഒരു പട്ടികയായി കാണുന്നില്ല വർമ്മ. ഒരു റോഡിന്റെയും ഡ്രെയിനേജിന്റെയും പ്രശ്നത്തിൽനിന്ന് ആരംഭിച്ച ഇടപെടൽ പിന്നീട് നഗരഭരണത്തിലേക്കും അതിലൂടെ കൂടുതൽ വിശാലമായ പൊതുഭരണ ഉത്തരവാദിത്തങ്ങളിലേക്കും വളർന്നതാണ് അദ്ദേഹത്തിന്റെ യാത്ര.',
                    'അവസാനം അദ്ദേഹം തിരിച്ചെത്തുന്നത് അതേ ചോദ്യത്തിലേക്കാണ്: വർഷങ്ങളായി ആളുകളുടെ ജീവിതത്തെ ബാധിച്ചിരുന്ന ഒരു പ്രശ്നത്തിൽ ഇടപെട്ടതിന് ശേഷം അവരുടെ ജീവിതം യഥാർത്ഥത്തിൽ കുറച്ചെങ്കിലും എളുപ്പമായോ?',
                ],
                'career' => [
                    ['നിർമ്മാണരംഗം', 'കൊച്ചിയിൽ ഒരു ദശാബ്ദത്തിലേറെ പ്രോജക്ട് അഡ്മിനിസ്ട്രേഷൻ; കേരളമെമ്പാടുമുള്ള ഏകോപന ജോലികൾ.'],
                    ['റീജിയണൽ ഓപ്പറേഷൻസ് ഹെഡ്', 'ഇൻഫ്രാസ്ട്രക്ചർ കമ്പനിയുടെ പ്രാദേശിക ചുമതല.'],
                    ['മുനിസിപ്പൽ കൗൺസിലർ', 'റോഡ്, മാലിന്യം, പൊതുസൗകര്യങ്ങൾ; മാർക്കറ്റ് പുനർവികസനം.'],
                    ['നിയമസഭ', 'തിരഞ്ഞെടുക്കപ്പെട്ടു; പാർലമെന്ററി സെക്രട്ടറി; സമിതി ചുമതലകൾ.'],
                ],
                'contributions' => [
                    'റോഡ്-ഡ്രെയിനേജ് പ്രശ്നം ഏകോപിത നിവേദനമാക്കി മാറ്റിയ നാട്ടുകാരുടെ കൂട്ടായ്മ',
                    'വ്യാപാരം തുറന്നുനിർത്തിക്കൊണ്ടുള്ള ഘട്ടംഘട്ട മാർക്കറ്റ് പുനർവികസനം',
                    ['തൊഴിൽപരിശീലന പരിപാടി', 'വ്യവസായവും സാങ്കേതിക വിദ്യാഭ്യാസവും കൂട്ടിയിണക്കി.'],
                    'ഭൂമി-ടെൻഡർ തടസ്സങ്ങളിലൂടെ ഘട്ടംഘട്ടമായെത്തിയ കുടിവെള്ള പദ്ധതി',
                ],
                'achievements' => [
                    'വ്യാപാരം നിലനിർത്തിക്കൊണ്ടുള്ള മാർക്കറ്റ് പുനർവികസനം',
                    'വ്യവസായ-സാങ്കേതിക ഇടപെടലുകളുള്ള തൊഴിൽപരിശീലനം',
                    'ഏകോപിത റോഡ്-ഡ്രെയിനേജ് മെച്ചപ്പെടുത്തലുകൾ',
                    'ഘട്ടംഘട്ട കുടിവെള്ള പദ്ധതി നടപ്പാക്കൽ',
                    'പ്രാദേശിക വികസന പാർലമെന്ററി സെക്രട്ടറി',
                ],
                'milestones' => [
                    'കൊച്ചിയിലെ നിർമ്മാണ കാലം',
                    'റോഡ്-ഡ്രെയിനേജ് കൂട്ടായ്മ',
                    'സ്വതന്ത്ര പിന്തുണയിൽ മുനിസിപ്പൽ മത്സരം',
                    'ആദ്യ കൗൺസിലർ കാലാവധി',
                    'നിയമസഭാംഗമായി തിരഞ്ഞെടുപ്പ്',
                    'പാർലമെന്ററി സെക്രട്ടറി ചുമതല',
                ],
                'labels' => [
                    'archive' => 'ജന്നയക്സ് · ജീവചരിത്ര ശേഖരം',
                    'biography' => 'ജീവചരിത്രം',
                    'career' => 'കരിയറും ചുമതലകളും',
                    'contributions' => 'പൊതുസംഭാവനകൾ',
                    'achievements' => 'നേട്ടങ്ങൾ',
                    'gallery' => 'ഗാലറി',
                    'milestones' => 'നാഴികക്കല്ലുകൾ',
                    'education' => 'വിദ്യാഭ്യാസവും ആദ്യകാല കരിയറും',
                    'education_body' => 'തൃശൂരിൽ ജനിച്ച് സാമ്പത്തികശാസ്ത്രം പഠിച്ച വർമ്മ നിർമ്മാണ കമ്പനിയിലാണ് കരിയർ ആരംഭിച്ചത്.',
                    'url_label' => 'സ്ഥിര പ്രൊഫൈൽ വിലാസം',
                    'copy' => 'ലിങ്ക് പകർത്തുക',
                    'copied' => 'ലിങ്ക് പകർത്തി',
                    'share' => 'പങ്കിടുക',
                    'specimen' => 'മാതൃകാ പ്രൊഫൈൽ · ഡിസൈൻ ആശയം — പ്രസിദ്ധീകരിച്ചതല്ല',
                    'portrait_caption' => 'ഛായാചിത്രം · ആർക്കൈവ് പ്ലേറ്റ്',
                    'plate_i' => 'രേഖപ്പെടുത്തിയ ദൃശ്യം',
                    'plate_ii' => 'നിയോജക പ്രവർത്തനം',
                    'plate_iii' => 'നിയമസഭാ സമ്മേളനം',
                    'plate_iv' => 'നേരിട്ടുള്ള സന്ദർശനം',
                ],
            ],
        ];
    }
}
