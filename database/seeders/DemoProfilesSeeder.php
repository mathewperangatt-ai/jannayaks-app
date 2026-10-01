<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\GeoDistrict;
use App\Models\InMemoriamEditorialContent;
use App\Models\InMemoriamGeography;
use App\Models\InMemoriamProfile;
use App\Models\InMemoriamPublicOffice;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileGeography;
use App\Models\ProfilePublicOffice;
use App\Models\User;
use App\Support\TierLabels;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FINAL DEMONSTRATION PROFILES.
 *
 * The final public demonstration set (per the final frontend pass and the
 * recovered demo-profile text master):
 *
 *   Living    — T. Gopalakrishnan, DISTINGUISHED, President, Sree Narayana
 *               Community Development Council · Social Educator & Community
 *               Leader. English text below is the RECOVERED APPROVED text and
 *               is used VERBATIM. Fictional demonstration material.
 *   Memorials — K. V. Mathew (1945–2021) and Dr. Saroja Nair (1952–2020).
 *               No approved final editorial text was recovered — the body
 *               below is a clearly-marked TEMPORARY state built only from the
 *               confirmed identity facts, awaiting insertion of the approved
 *               final text. DO NOT treat it as approved copy.
 *
 * The 12-person reference Kerala gallery identities are NOT published here;
 * they are reference/gallery assets only.
 *
 * Portraits: place approved images in database/seeders/demo-media/ using the
 * filenames below and re-run the seeder — the seeder attaches any file that
 * is present, computes its SHA-256 integrity hash, and skips missing ones
 * (the page then shows the dignified monogram placeholder).
 *   - t_gopalakrishnan.jpg            (no approved portrait located to date)
 *   - k_v_mathew_memorial_portrait.jpg (supplied separately; not on disk yet)
 *   - dr_saroja_nair_memorial_portrait.jpg (supplied separately; not on disk yet)
 *
 * Also creates two internal test accounts using the existing controlled
 * admin_test_demo payment-waiver architecture (server-side only, never a
 * public bypass).
 *
 * Run explicitly: php artisan db:seed --class=DemoProfilesSeeder
 * Never added to DatabaseSeeder so test databases stay unpolluted.
 */
class DemoProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGopalakrishnan();
        $this->seedMemorials();
        $this->seedTestAccounts();
    }

    private function seedGopalakrishnan(): void
    {
        // profiles.user_id is NOT NULL — demonstration profiles are owned by
        // a locked internal demo account (random password, never used to log in).
        $demoOwner = User::query()->updateOrCreate(
            ['email' => 'demo.profiles@jannayaks.internal'],
            [
                'name' => 'Jannayaks Demonstration Profiles',
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ],
        );

        $profile = Profile::query()->updateOrCreate(
            ['slug' => 't.gopalakrishnan'],
            [
                'user_id' => $demoOwner->id,
                'full_name' => 'T. Gopalakrishnan',
                'display_name' => 'T. Gopalakrishnan',
                'status' => 'published',
                'profession' => 'Social Educator & Community Leader',
                'bio_headline' => 'President, Sree Narayana Community Development Council',
                'published_at' => now()->subDays(30),
                'approved_at' => now()->subDays(31),
                'submitted_at' => now()->subDays(40),
            ],
        );

        $district = GeoDistrict::query()->where('name', 'like', 'Alappuzha%')->first();
        ProfileGeography::query()->updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'country_code' => 'IN',
                'state_region_name' => 'Kerala',
                'district_id' => $district?->id,
            ],
        );

        $offices = [
            ['President, Sree Narayana Community Development Council', 'Kerala', 'Elected president after serving as branch secretary, district coordinator and state education convenor.'],
            ['Chairman, SNCDC Education Committee', 'Kerala', 'Oversaw the scholarship programme and the vocational training centre.'],
            ['Convenor, SNCDC Community Development Programme', 'Kerala', "Coordinated women's self-help groups and community development initiatives."],
            ['Member, governing board — SNCDC educational trust', 'Kerala', "Governing board responsibility for the organisation's educational trust."],
            ['Advisory committees — vocational education and community development', 'Kerala', 'Served on advisory committees connected with vocational education and community development.'],
        ];
        foreach ($offices as $i => [$office, $where, $summary]) {
            ProfilePublicOffice::query()->updateOrCreate(
                ['profile_id' => $profile->id, 'office_name' => $office],
                [
                    'where_location' => $where,
                    'term_summary' => $summary,
                    'is_current' => $i === 0,
                    'sort_order' => $i + 1,
                ],
            );
        }

        // Summary is the editorial deck (opening line of the approved body),
        // not a repetition of the credentials shown in the hero eyebrow.
        $summary = 'T. Gopalakrishnan\'s public life has developed alongside the growth of an organisation that began as a small community initiative and eventually expanded into education, social welfare and community development.';

        // RECOVERED APPROVED English text — used verbatim. Do not edit.
        $body = <<<'TEXT'
T. Gopalakrishnan's public life has developed alongside the growth of an organisation that began as a small community initiative and eventually expanded into education, social welfare and community development.

Born in Alappuzha, Gopalakrishnan studied history at the University of Kerala before entering the cooperative sector. He worked for several years in banking and later became involved in community organisations, initially as a volunteer supporting educational programmes for young people.

His association with the fictional Sree Narayana Community Development Council (SNCDC) began at the local level.

The organisation had originally been established to support educational and social development programmes among members of the community. Gopalakrishnan first became involved by helping organise scholarship assistance for students from economically weaker families.

What began as a volunteer activity gradually became a larger responsibility.

He served successively as branch secretary, district coordinator and state education convenor before being elected president of the SNCDC. Under his leadership, the organisation expanded several of its existing programmes and introduced new initiatives in vocational education, women's self-help groups and youth development.

Education has remained one of his principal areas of interest.

The SNCDC's scholarship programme, which initially assisted fewer than fifty students, eventually expanded to several hundred beneficiaries. Gopalakrishnan also supported the creation of a vocational training centre designed to provide practical skills to young people who had not pursued conventional higher education.

One of the programmes he considers particularly meaningful is a women's micro-enterprise initiative. Small groups were provided training and modest financial assistance to establish home-based businesses. The programme has since developed into a network of women's self-help groups operating in several districts.

For Gopalakrishnan, however, the organisation's work is not simply about providing assistance.

He believes that community organisations become sustainable only when people who receive support eventually become participants in creating opportunities for others.

That philosophy has influenced the organisation's youth programmes as well. Young volunteers are given responsibility for organising educational camps, cultural programmes and community service activities, with senior office-bearers acting more as mentors than as permanent organisers.

His years in organisational leadership have also brought him into contact with people holding very different political views.

The SNCDC is not a political party, and Gopalakrishnan has maintained that its community programmes should remain accessible to people across political affiliations. At the same time, he has occasionally spoken publicly on issues involving education, social mobility, representation and access to public institutions.

His approach has sometimes required him to balance the expectations of a large membership with the practical limitations of an organisation dependent on volunteers and donations.

Among the responsibilities he has held are President of the SNCDC, Chairman of its Education Committee, Convenor of its Community Development Programme and member of the governing board of the organisation's educational trust.

He has also served on advisory committees connected with vocational education and community development.

Away from the organisation, Gopalakrishnan leads a comparatively ordinary family life. His wife, Radha, is a retired teacher. Their two children work in the fields of medicine and education. He remains particularly interested in books, classical Malayalam literature and conversations with young people entering professional life.

At an age when many people begin to reduce their responsibilities, he has instead become increasingly interested in succession.

He wants the organisation to become less dependent on individual leaders and more capable of producing its next generation of volunteers, educators and community organisers.

That, perhaps, is where his present interests meet the experience of his earlier years.

He began as someone helping a few students obtain educational assistance. He eventually found himself responsible for an organisation working across several areas of community life.

He sees the continuity between the two stages quite simply.

The purpose of an organisation, he believes, is not merely to solve today's problems, but to leave behind people who are capable of solving tomorrow's.
TEXT;

        $editorial = EditorialContent::query()->updateOrCreate(
            ['profile_id' => $profile->id, 'language' => EditorialContent::LANGUAGE_EN, 'version_number' => 1],
            [
                'title' => 'T. Gopalakrishnan',
                'summary' => $summary,
                'body' => $body,
                'status' => EditorialContent::STATUS_APPROVED,
            ],
        );

        $application = Application::query()->updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'user_id' => $demoOwner->id,
                'source_method' => 'direct_submission',
                'package_tier' => 'distinguished',
                'full_name' => 'T. Gopalakrishnan',
                'preferred_display_name' => 'T. Gopalakrishnan',
                'payment_status' => Application::PAYMENT_STATUS_PAID,
                'status' => Application::STATUS_PUBLISHED,
                'direct_submission_received_at' => now()->subDays(40),
                'published_english_editorial_content_id' => $editorial->id,
                'published_malayalam_editorial_content_id' => null,
            ],
        );

        $this->attachPortrait($profile, 't_gopalakrishnan.jpg', 'Portrait of T. Gopalakrishnan');

        $this->command?->info(sprintf(
            'Demo profile: T. Gopalakrishnan (%s) — application #%d. No locked Malayalam editorial exists yet; ML falls back to EN.',
            TierLabels::label('distinguished'),
            $application->id,
        ));
    }

    private function seedMemorials(): void
    {
        $memorials = [
            [
                'slug' => 'k.v.mathew',
                'name' => 'K. V. Mathew',
                'profession' => 'Teacher',
                'headline' => 'Teacher, Institution Builder, Mentor',
                'born' => '1945-01-01',
                'died' => '2021-01-01',
                'portrait' => 'k_v_mathew_memorial_portrait.jpg',
                'summary' => 'K. V. Mathew (1945–2021) is remembered as a teacher, institution builder and mentor.',
            ],
            [
                'slug' => 'dr.saroja.nair',
                'name' => 'Dr. Saroja Nair',
                'profession' => 'Doctor',
                'headline' => "Pioneer in Women's Healthcare",
                'born' => '1952-01-01',
                'died' => '2020-01-01',
                'portrait' => 'dr_saroja_nair_memorial_portrait.jpg',
                'summary' => "Dr. Saroja Nair (1952–2020) is remembered as a pioneer in women's healthcare.",
            ],
        ];

        foreach ($memorials as $m) {
            $profile = InMemoriamProfile::query()->updateOrCreate(
                ['slug' => $m['slug']],
                [
                    'deceased_full_name' => $m['name'],
                    'deceased_display_name' => $m['name'],
                    'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
                    // Required NOT NULL columns; never displayed (display consent false).
                    'commissioner_contact_name' => 'Jannayaks Demonstration Records',
                    'commissioner_contact_mobile' => '0000000000',
                    'commissioner_contact_email' => 'demo.profiles@jannayaks.internal',
                    'commissioner_relation' => 'Fictional demonstration record',
                    'profession' => $m['profession'],
                    'bio_headline' => $m['headline'],
                    'deceased_date_of_birth' => $m['born'],
                    'deceased_date_of_death' => $m['died'],
                    'verification_status' => InMemoriamProfile::VERIFICATION_WAIVED, // fictional demonstration profile
                    'commissioner_display_consent' => false,
                    'published_at' => now()->subDays(20),
                    'hosting_starts_on' => now()->subYears(2)->startOfYear(),
                    'hosting_ends_on' => now()->addYears(10)->endOfYear(),
                ],
            );

            InMemoriamGeography::query()->updateOrCreate(
                ['in_memoriam_profile_id' => $profile->id],
                ['country_code' => 'IN', 'state_region_name' => 'Kerala'],
            );

            // TEMPORARY content state: the recovered corpus explicitly marks
            // the final memorial texts as NOT LOCATED / DO NOT INVENT. This
            // body uses only the confirmed identity facts and states plainly
            // that the full record is in preparation. Replace with the
            // approved final text when it becomes available.
            $body = $m['summary']
                ."\n\nThis memorial demonstration page is shown with a temporary editorial state. "
                .'The complete life record for '.$m['name'].' is in preparation and will be published here.';

            InMemoriamEditorialContent::query()->updateOrCreate(
                ['in_memoriam_profile_id' => $profile->id, 'language' => InMemoriamEditorialContent::LANGUAGE_EN, 'version_number' => 1],
                [
                    'title' => $m['name'],
                    'summary' => $m['headline'],
                    'body' => $body,
                    'status' => InMemoriamEditorialContent::STATUS_APPROVED,
                    'ai_generated' => false,
                ],
            );

            $this->command?->warn(sprintf(
                '%s: temporary content state — approved final memorial text was not located; do not treat as final copy.',
                $m['name'],
            ));
        }
    }

    private function seedTestAccounts(): void
    {
        $staff = User::query()->where('email', 'like', '%@jannayaks.in')->first();

        $accounts = [
            ['Test Customer — Recognised', 'test.recognised@jannayaks.in', 'emerging'],
            ['Test Customer — Distinguished', 'test.distinguished@jannayaks.in', 'distinguished'],
        ];

        foreach ($accounts as [$name, $email, $tier]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(40)), // set a real password via admin before use
                    'email_verified_at' => now(),
                ],
            );

            $application = Application::query()->updateOrCreate(
                ['user_id' => $user->id, 'source_method' => 'admin_test_demo'],
                [
                    'package_tier' => $tier,
                    'full_name' => $name,
                    'preferred_display_name' => $name,
                    'payment_status' => 'waived',
                    'status' => 'payment_pending',
                    'waived_by_user_id' => $staff?->id,
                    'admin_demo_audit_note' => 'Controlled internal test account for payment-unlocked journey testing.',
                    'intake_started_at' => now(),
                ],
            );

            $this->command?->info(sprintf('Test account: %s (%s) — application #%d (admin_test_demo waiver).', $email, $tier, $application->id));
        }
    }

    /**
     * Attach an approved public portrait from database/seeders/demo-media/
     * when the file is present. Missing files are skipped (monogram shown).
     */
    private function attachPortrait(Profile $profile, string $filename, string $alt): void
    {
        $source = database_path('seeders/demo-media/'.$filename);
        if (! is_file($source)) {
            $this->command?->warn("Portrait not bundled yet: {$filename} (skipping — placeholder will be shown).");

            return;
        }

        $bytes = file_get_contents($source);
        if ($bytes === false) {
            return;
        }

        $disk = (string) config('jannayaks.media.public_disk', 'public');
        $prefix = trim((string) config('jannayaks.media.object_prefix', 'profile-media'), '/');
        $key = $prefix.'/profiles/'.$profile->id.'/'.Str::lower((string) Str::ulid()).'.jpg';
        Storage::disk($disk)->put($key, $bytes);

        [$width, $height] = getimagesizefromstring($bytes) ?: [null, null];

        MediaItem::query()->updateOrCreate(
            [
                'mediable_type' => $profile->getMorphClass(),
                'mediable_id' => $profile->id,
                'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
                'is_primary' => true,
            ],
            [
                'storage_path_key' => $key,
                'disk' => $disk,
                'alt_text' => $alt,
                'display_order' => 1,
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'review_status' => MediaItem::REVIEW_APPROVED,
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($bytes),
                'photo_sha256' => hash('sha256', $bytes),
                'width' => $width,
                'height' => $height,
            ],
        );
    }
}
