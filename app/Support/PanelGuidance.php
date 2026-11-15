<?php

namespace App\Support;

/**
 * Approved bilingual guidance for the customer panel and the Filament
 * administration panel. Concise, context-appropriate, and reflecting the
 * ACTUAL implemented functionality — nothing here promises unavailable
 * services, payment capabilities, tiers or prices. The brand name is
 * always written as Jannayaks, including inside Malayalam text.
 */
class PanelGuidance
{
    /**
     * @return array{title_en: string, title_ml: string, en: list<string>, ml: list<string>}
     */
    public static function for(string $key): array
    {
        $catalog = self::catalog();

        return $catalog[$key] ?? $catalog['support'];
    }

    /**
     * @return array<string, array{title_en: string, title_ml: string, en: list<string>, ml: list<string]}>
     */
    public static function catalog(): array
    {
        return [
            'apply' => [
                'title_en' => 'How applying works',
                'title_ml' => 'അപേക്ഷ എങ്ങനെ നടത്താം',
                'en' => [
                    'Fill in this one form — there is a single Jannayaks Profile for every applicant.',
                    'Your country of residence decides the price category; it is never shown on your public profile.',
                    'After sign-in, the Online Interview comes first; payment is prepared after it.',
                ],
                'ml' => [
                    'ഈ ഒരു ഫോം മാത്രം പൂരിപ്പിക്കുക — എല്ലാ അപേക്ഷകൾക്കും ഒരേ Jannayaks Profile ആണ് ഉള്ളത്.',
                    'നിരക്ക് നിർണ്ണയിക്കുന്നത് താങ്കൾ താമസിക്കുന്ന രാജ്യമാണ്; ഇത് പ്രൊഫൈലിൽ പ്രദർശിപ്പിക്കില്ല.',
                    'സൈൻ ഇൻ ചെയ്ത ശേഷം ആദ്യം ഓൺലൈൻ അഭിമുഖമാണ്; പേയ്മെന്റ് അതിന് ശേഷമാണ് തയ്യാറാക്കുന്നത്.',
                ],
            ],
            'interview' => [
                'title_en' => 'About the Online Interview',
                'title_ml' => 'ഓൺലൈൻ അഭിമുഖത്തെക്കുറിച്ച്',
                'en' => [
                    'The interview tells the editorial team who you are and what you have achieved. Answer in your own words; only Q1 is required.',
                    'You can save your answers at any time and return later — the interview stays open for ten calendar days from your first start.',
                    'Only Submit sends your answers to the editorial team; saved answers stay private until then.',
                ],
                'ml' => [
                    'താങ്കളാരാണെന്നും നേട്ടങ്ങൾ എന്തൊക്കെയെന്നും എഡിറ്റോറിയൽ ടീമിനോട് പറയുന്നതാണ് അഭിമുഖം. സ്വന്തം വാക്കുകളിൽ ഉത്തരം നൽകുക; Q1 മാത്രമേ നിർബന്ധിതമുള്ളൂ.',
                    'ഉത്തരങ്ങൾ എപ്പോഴും സേവ് ചെയ്ത് പിന്നീട് തിരിച്ചുവരാം — ആദ്യം തുടങ്ങിയതുമുതൽ പത്ത് ദിവസം വരെ അഭിമുഖം തുറന്നിരിക്കും.',
                    'Submit ചെയ്യുമ്പോൾ മാത്രമേ ഉത്തരങ്ങൾ എഡിറ്റോറിയൽ ടീമിന് ലഭിക്കൂ; അതുവരെ സേവ് ചെയ്ത ഉത്തരങ്ങൾ സ്വകാര്യമായി തുടരും.',
                ],
            ],
            'uploads' => [
                'title_en' => 'Supporting documents',
                'title_ml' => 'തെളിവുരേഖകൾ',
                'en' => [
                    'Upload documents that support your interview answers (certificates, articles, links as files).',
                    'Every upload is reviewed by the editorial team before it is used; nothing is published directly.',
                ],
                'ml' => [
                    'അഭിമുഖ ഉത്തരങ്ങളെ പിന്തുണയ്ക്കുന്ന രേഖകൾ അപ്‌ലോഡ് ചെയ്യുക (സർട്ടിഫിക്കറ്റുകൾ, ലേഖനങ്ങൾ).',
                    'ഓരോ രേഖയും ഉപയോഗിക്കും മുമ്പ് എഡിറ്റോറിയൽ ടീം പരിശോധിക്കുന്നു; ഒന്നും നേരിട്ട് പ്രസിദ്ധീകരിക്കില്ല.',
                ],
            ],
            'photos' => [
                'title_en' => 'Photographs',
                'title_ml' => 'ഫോട്ടോഗ്രാഫുകൾ',
                'en' => [
                    'You may upload up to three photographs — select them together in one upload if you like.',
                    'Photographs stay pending until Jannayaks approves them; they appear on the profile only after approval and publication.',
                    'One photograph can be marked as the primary portrait. Photographs are never generated or altered — only reviewed.',
                ],
                'ml' => [
                    'മൂന്ന് ഫോട്ടോഗ്രാഫുകൾ വരെ അപ്‌ലോഡ് ചെയ്യാം — ഒറ്റ തവണ കൂടെ തിരഞ്ഞെടുക്കാം.',
                    'Jannayaks അംഗീകരിക്കുന്നതുവരെ ഫോട്ടോഗ്രാഫുകൾ പരിശോധനയിൽ തുടരും; പ്രൊഫൈലിൽ കാണുക അംഗീകാരത്തിനും പ്രസിദ്ധീകരണത്തിനും ശേഷം മാത്രം.',
                    'ഒരു ഫോട്ടോയെ പ്രധാന ഛായാചിത്രമായി തിരഞ്ഞെടുക്കാം. ഫോട്ടോഗ്രാഫുകൾ സൃഷ്ടിക്കുകയോ മാറ്റുകയോ ചെയ്യില്ല — പരിശോധിക്കുക മാത്രം.',
                ],
            ],
            'progress' => [
                'title_en' => 'What happens next',
                'title_ml' => 'അടുത്തത് എന്താണ്',
                'en' => [
                    'After submission, the editorial team prepares your profile with AI assistance and human editorial review.',
                    'You will be asked to review and approve the finished profile before anything is published — publication happens only after your approval and Jannayaks’ own release.',
                ],
                'ml' => [
                    'സമർപ്പണത്തിന് ശേഷം എഡിറ്റോറിയൽ ടീം, AI സഹായത്തോടെയും മനുഷ്യ എഡിറ്റോറിയൽ പരിശോധനയോടെയും പ്രൊഫൈൽ തയ്യാറാക്കുന്നു.',
                    'പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് തയ്യാറാക്കിയ പ്രൊഫൈൽ താങ്കൾ പരിശോധിച്ച് അംഗീകരിക്കേണ്ടതാണ് — അംഗീകാരത്തിനും Jannayaks തന്റെ അന്തിമ പ്രസിദ്ധീകരണത്തിനും ശേഷം മാത്രം പ്രസിദ്ധീകരിക്കുന്നു.',
                ],
            ],
            'review' => [
                'title_en' => 'Reviewing and approving',
                'title_ml' => 'പരിശോധിച്ച് അംഗീകരിക്കൽ',
                'en' => [
                    'This preview is exactly how the published profile will look — same layout, content and photographs, in English and Malayalam.',
                    'You cannot edit the biography text directly: request a revision or a factual correction below and the editorial team will prepare it.',
                    'Approving does not publish immediately — Jannayaks staff completes publication.',
                ],
                'ml' => [
                    'പ്രസിദ്ധീകരിക്കുന്ന പ്രൊഫൈൽ ഇങ്ങനെ തന്നെയായിരിക്കും കാണപ്പെടുക — അതേ രൂപവും ഉള്ളടക്കവും ഫോട്ടോഗ്രാഫുകളും, ഇംഗ്ലീഷിലും മലയാളത്തിലും.',
                    'ജീവചരിത്രം നേരിട്ട് തിരുത്താൻ സാധിക്കില്ല: താഴെ റിവിഷൻ അല്ലെങ്കിൽ തിരുത്തൽ അഭ്യർത്ഥിക്കുക; എഡിറ്റോറിയൽ ടീം തയ്യാറാക്കി നൽകും.',
                    'അംഗീകാരം ഉടനടി പ്രസിദ്ധീകരണമല്ല — Jannayaks സ്റ്റാഫ് ആണ് പ്രസിദ്ധീകരണം പൂർത്തിയാക്കുന്നത്.',
                ],
            ],
            'updates' => [
                'title_en' => 'Updates after publication',
                'title_ml' => 'പ്രസിദ്ധീകരണത്തിന് ശേഷമുള്ള മാറ്റങ്ങൾ',
                'en' => [
                    'Submit all requested changes together as one maintenance request; the editorial team reviews and prepares them.',
                    'Typographical and factual corrections are free. One complimentary maintenance update is included every three months; substantial rewriting is assessed and quoted before any work begins.',
                ],
                'ml' => [
                    'എല്ലാ മാറ്റങ്ങളും ഒരു മെയിന്റനൻസ് അഭ്യർത്ഥനയായി ഒരുമിച്ച് സമർപ്പിക്കുക; എഡിറ്റോറിയൽ ടീം പരിശോധിച്ച് തയ്യാറാക്കും.',
                    'അച്ചടിപ്പിശകും വസ്തുതാപരമായ തിരുത്തലുകളും സൗജന്യമാണ്. ഓരോ മൂന്ന് മാസത്തിലും ഒരു സൗജന്യ അപ്ഡേറ്റ് ഉൾപ്പെടുന്നു; വലിയ തിരുത്തലുകൾക്ക് ജോലി തുടങ്ങും മുമ്പ് നിരക്ക് അറിയിക്കും.',
                ],
            ],
            'payments' => [
                'title_en' => 'Payments and waivers',
                'title_ml' => 'പേയ്മെന്റും ഒഴിവാക്കലും',
                'en' => [
                    'The price category follows your recorded country of residence: India pays in rupees, other countries in US dollars. The server calculates the amount — it cannot be changed at checkout.',
                    'If Jannayaks waived your payment, this page will say so and no payment is needed.',
                    'Online payment is not active yet. When a payment link is created, it appears here with its status.',
                ],
                'ml' => [
                    'നിരക്ക് വിഭാഗം രേഖപ്പെടുത്തിയ താമസ രാജ്യം അനുസരിച്ചാണ്: ഇന്ത്യ രൂപയിലും മറ്റ് രാജ്യങ്ങൾ US ഡോളറിലും. തുക കണക്കാക്കുന്നത് സെർവറാണ് — പേയ്മെന്റ് സമയത്ത് മാറ്റാൻ സാധിക്കില്ല.',
                    'Jannayaks പേയ്മെന്റ് ഒഴിവാക്കിയിട്ടുണ്ടെങ്കിൽ ഈ പേജിൽ അത് കാണാം; പേയ്മെന്റ് ആവശ്യമില്ല.',
                    'ഓൺലൈൻ പേയ്മെന്റ് ഇതുവരെ ആരംഭിച്ചിട്ടില്ല. ലിങ്ക് സൃഷ്ടിക്കുമ്പോൾ അത് ഇവിടെ അവസ്ഥയോടെ കാണാം.',
                ],
            ],
            'renewals' => [
                'title_en' => 'Annual renewal',
                'title_ml' => 'വാർഷിക പുതുക്കൽ',
                'en' => [
                    'Keeping a profile published is a yearly renewal: ₹1,500 in India or US$15 for other countries. Your profile and its permanent URL stay published while the renewal is active.',
                    'Renewal never happens automatically — you start it here when it is due.',
                ],
                'ml' => [
                    'പ്രൊഫൈൽ പ്രസിദ്ധീകരിച്ച് തുടരാൻ വാർഷിക പുതുക്കലാണ്: ഇന്ത്യയിൽ ₹1,500, മറ്റ് രാജ്യങ്ങളിൽ US$15. പുതുക്കൽ സജീവമായിരിക്കുമ്പോൾ പ്രൊഫൈലും സ്ഥിരം URL ഉം പ്രസിദ്ധീകരിച്ച് തുടരും.',
                    'പുതുക്കൽ ഒരിക്കലും സ്വയമായി നടക്കില്ല — അവസാന തീയതിയിൽ താങ്കൾ ഇവിടെ നിന്ന് ആരംഭിക്കുക.',
                ],
            ],
            'support' => [
                'title_en' => 'Support and assistance',
                'title_ml' => 'പിന്തുണയും സഹായവും',
                'en' => [
                    'Stuck at any step? Contact the Jannayaks team at the address on the Contact section of the homepage — include your application reference number.',
                    'Your application details and approved photographs are visible only to you and the editorial team.',
                ],
                'ml' => [
                    'ഏത് ഘട്ടത്തിലും സംശയമുണ്ടോ? ഹോംപേജിലെ Contact ഭാഗത്തുള്ള വിലാസത്തിൽ Jannayaks ടീമിനെ ബന്ധപ്പെടുക — അപേക്ഷയുടെ റഫറൻസ് നമ്പർ കൂടെ പറയുക.',
                    'അപേക്ഷയുടെ വിവരങ്ങളും അംഗീകരിച്ച ഫോട്ടോഗ്രാഫുകളും കാണുക താങ്കളും എഡിറ്റോറിയൽ ടീമും മാത്രം.',
                ],
            ],
        ];
    }
}
