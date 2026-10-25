<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\ProfileMediaRelationManager;
use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Support\PhotographReplacementComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhotographReplacementComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_comparison_shows_current_and_proposed_without_publishing(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Photo Subject',
            'display_name' => 'Photo Subject',
            'profession' => 'Teacher',
            'slug' => 'photo.subject',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Photo Subject',
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'status' => Application::STATUS_PUBLISHED,
        ]);

        $current = $this->photo($profile, $member, 'Current portrait', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, true);
        $proposed = $this->photo($profile, $member, '<script>alert(1)</script>', MediaItem::REVIEW_PENDING, MediaItem::PRIVACY_PRIVATE, false);

        $pair = PhotographReplacementComparison::pair($proposed);
        $this->assertSame($current->id, $pair['current']?->id);
        $this->assertSame($proposed->id, $pair['proposed']?->id);

        $html = view('filament.relation-managers.photo-replacement-comparison', [
            'record' => $proposed,
        ])->render();

        $this->assertStringContainsString('Currently public', $html);
        $this->assertStringContainsString('Proposed replacement — not public', $html);
        $this->assertStringContainsString(route('staff.profile-media.preview', ['media' => $current]), $html);
        $this->assertStringContainsString(route('staff.profile-media.preview', ['media' => $proposed]), $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('jy-photo-replace-grid', $html);

        $this->assertSame(MediaItem::REVIEW_PENDING, $proposed->fresh()->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $proposed->fresh()->privacy);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $current->fresh()->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $current->fresh()->privacy);

        Livewire::actingAs($admin)
            ->test(ProfileMediaRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->assertSuccessful()
            ->assertSee('Compare photographs', false);
    }

    private function photo(Profile $profile, User $owner, string $alt, string $review, string $privacy, bool $primary): MediaItem
    {
        return MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
            'storage_path_key' => 'tests/'.md5($alt).'.jpg',
            'disk' => 'public',
            'alt_text' => $alt,
            'display_order' => $primary ? 1 : 2,
            'is_primary' => $primary,
            'uploaded_by_id' => $owner->id,
            'privacy' => $privacy,
            'review_status' => $review,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1200,
        ]);
    }
}
