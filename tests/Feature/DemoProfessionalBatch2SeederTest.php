<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch-2 fixture integrity: every one of the ten professional demo
 * profiles maps one-to-one to its own three supplied photographs. The
 * slug→filename mapping is dr./adv./prof. prefix-preserving with dots
 * replaced by underscores (e.g. dr.devika.nair → dr_devika_nair_01.jpg),
 * and no photograph is ever assigned to another profile.
 */
class DemoProfessionalBatch2SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_fixture_has_exactly_three_correctly_prefixed_photos(): void
    {
        $mediaDir = database_path('seeders/demo-professionals-2/media');
        $slugs = [
            'dr.devika.nair', 'arun.mathew', 'meera.krishnan', 'rajesh.pillai', 'anoop.varghese',
            'dr.sandeep.menon', 'adv.nandita.rao', 'prof.vivek.menon', 'dr.rhea.thomas', 'rajiv.menon',
        ];

        $allSeen = [];
        foreach ($slugs as $slug) {
            $prefix = str_replace('.', '_', $slug);
            $files = glob($mediaDir.DIRECTORY_SEPARATOR.$prefix.'_*.jpg');

            $this->assertCount(3, $files, "$slug must have exactly three photographs.");
            foreach ($files as $file) {
                $base = basename($file, '.jpg');
                $this->assertStringStartsWith($prefix.'_', $base, "$base must belong to $slug.");
                $this->assertNotContains($base, $allSeen, "$base must not be shared with another profile.");
                $allSeen[] = $base;
            }
        }

        $this->assertCount(30, $allSeen, 'Ten profiles × three photographs, no duplicates.');
    }

    public function test_seeder_assigns_each_profile_its_own_three_photos(): void
    {
        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\DemoProfessionalBatch2Seeder::class,
            '--force' => true,
        ]);

        $slugs = [
            'dr.devika.nair', 'arun.mathew', 'meera.krishnan', 'rajesh.pillai', 'anoop.varghese',
            'dr.sandeep.menon', 'adv.nandita.rao', 'prof.vivek.menon', 'dr.rhea.thomas', 'rajiv.menon',
        ];

        $namesBySlug = [];
        foreach ($slugs as $slug) {
            $profile = Profile::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame(3, $profile->media()->count(), "$slug: exactly three photographs.");

            $namesBySlug[$slug] = $profile->display_name;
            foreach ($profile->media as $photo) {
                $this->assertStringContainsString(
                    $profile->display_name,
                    (string) $photo->alt_text,
                    "Every photograph on $slug carries that profile's own identity.",
                );
            }
        }

        // No photograph appears on another profile: every media row belongs
        // to exactly one profile, and profiles own disjoint photo sets.
        $this->assertSame(30, MediaItem::query()->count());
        $this->assertSame(10, MediaItem::query()->distinct('mediable_id')->count('mediable_id'));

        // Photos belong exclusively to their own profile's user.
        foreach ($slugs as $slug) {
            $profile = Profile::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame(
                $profile->user_id,
                $profile->media()->first()->uploaded_by_id,
                "$slug: photographs were uploaded by this profile's own account.",
            );
        }
    }

    public function test_seeded_profiles_publish_with_their_own_three_photos(): void
    {
        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\DemoProfessionalBatch2Seeder::class,
            '--force' => true,
        ]);

        foreach (Profile::query()->whereIn('slug', [
            'dr.devika.nair', 'arun.mathew', 'meera.krishnan', 'rajesh.pillai', 'anoop.varghese',
            'dr.sandeep.menon', 'adv.nandita.rao', 'prof.vivek.menon', 'dr.rhea.thomas', 'rajiv.menon',
        ])->get() as $profile) {
            $this->assertSame(3, $profile->media()->count(), $profile->slug);
            $this->assertSame(1, $profile->media()->where('is_primary', true)->count(), $profile->slug.': exactly one primary photograph.');
            $this->assertSame(
                3,
                $profile->media()->where('review_status', 'approved')->count(),
                $profile->slug.': all three photographs are staff-approved.',
            );
        }
    }
}
