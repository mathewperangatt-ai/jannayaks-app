<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Security\PublishedProfileAuthorizer;
use App\Services\PostPublicationUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wave 3 — the Jannayaks Security Administrator boundary.
 *
 * Exclusive authority over published-profile operations; the legacy admin
 * retains them only while the transitional override is enabled; unauthorised
 * attempts are rejected AND recorded; the security_admin account itself is
 * manageable only through the emergency console command.
 */
class SecurityAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.security.integrity.published_profile_admin.allow_legacy_admin' => false]);
    }

    private function seedPublishedProfile(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'full_name' => 'Security Fixture Leader',
            'preferred_display_name' => 'Security Fixture Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'published_at' => now(),
            'full_name' => 'Security Fixture Leader',
            'display_name' => 'Security Fixture Leader',
            'slug' => 'sec-fixture-'.strtolower(\Illuminate\Support\Str::random(8)),
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Security Fixture Leader',
            'body' => 'Body.',
            'summary' => 'Summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(\App\Services\ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return ['member' => $member, 'admin' => $admin, 'application' => $application->fresh(), 'profile' => $profile->fresh()];
    }

    public function test_security_admin_can_modify_published_profiles(): void
    {
        ['application' => $application, 'member' => $member] = $this->seedPublishedProfile();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);

        // The member submits a correction; the Security Administrator prepares
        // the maintenance draft (the published-profile authority).
        app(PostPublicationUpdateService::class)->submit($application, $member, 'Please correct my job title and add my new role.');
        $request = app(PostPublicationUpdateService::class)->prepareVersions($application, $securityAdmin, [
            'title' => 'Security Fixture Leader (updated)',
            'summary' => 'Updated summary.',
            'body' => 'Updated body copy approved for the maintenance cycle.',
            'status' => EditorialContent::STATUS_APPROVED,
        ], null);
        $this->assertSame('in_progress', $request->status);
        $this->assertNotNull($request->resulting_english_editorial_content_id);
    }

    public function test_existing_admin_cannot_modify_published_profiles_in_strict_mode(): void
    {
        ['application' => $application, 'admin' => $admin] = $this->seedPublishedProfile();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('recorded');

        try {
            app(PostPublicationUpdateService::class)->prepareViaAiDraft($application, $admin);
        } finally {
            // The attempt is recorded in the audit trail.
            $this->assertTrue(StaffActionLog::query()
                ->where('actor_user_id', $admin->id)
                ->where('action', 'security.published_profile_access_denied')
                ->exists());
        }
    }

    public function test_legacy_admin_retains_rights_during_transitional_window(): void
    {
        config(['jannayaks.security.integrity.published_profile_admin.allow_legacy_admin' => true]);
        ['application' => $application, 'member' => $member, 'admin' => $admin] = $this->seedPublishedProfile();

        app(PostPublicationUpdateService::class)->submit($application, $member, 'Please correct my job title and add my new role.');
        $request = app(PostPublicationUpdateService::class)->prepareVersions($application, $admin, [
            'title' => 'Security Fixture Leader (updated)',
            'summary' => 'Updated summary.',
            'body' => 'Updated body copy approved for the maintenance cycle.',
            'status' => EditorialContent::STATUS_APPROVED,
        ], null);
        $this->assertSame('in_progress', $request->status);
    }

    public function test_editors_cannot_modify_published_profiles(): void
    {
        ['application' => $application] = $this->seedPublishedProfile();
        $editor = User::factory()->editor()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(PostPublicationUpdateService::class)->prepareViaAiDraft($application, $editor);
    }

    public function test_unauthenticated_users_cannot_modify_published_profiles(): void
    {
        ['application' => $application] = $this->seedPublishedProfile();

        $this->post(route('applications.maintenance.correction', $application), [
            'correction_text' => 'hacked text',
        ])->assertRedirect(); // redirected to login
    }

    public function test_security_admin_capability_helper_respects_strict_mode(): void
    {
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

        $this->assertTrue($securityAdmin->canManagePublishedProfiles());
        $this->assertFalse($admin->canManagePublishedProfiles());
        $this->assertFalse($editor->canManagePublishedProfiles());
        $this->assertFalse($member->canManagePublishedProfiles());

        // Security Administrator is deliberately NOT a super-admin.
        $this->assertFalse($securityAdmin->canManageFinance());
        $this->assertFalse($securityAdmin->canManageStaffUsers());
        $this->assertFalse($securityAdmin->isAdmin());
    }

    public function test_member_cannot_elevate_to_security_admin_role(): void
    {
        // The UI role options never include security_admin — elevation via
        // the staff form is impossible by construction.
        $this->assertArrayNotHasKey(User::ROLE_SECURITY_ADMIN, \App\Filament\Resources\Users\Schemas\UserForm::roleOptions());
        $this->assertArrayHasKey(User::ROLE_ADMIN, \App\Filament\Resources\Users\Schemas\UserForm::roleOptions());
    }

    public function test_users_resource_hides_security_admin_account_from_existing_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);
        $plain = User::factory()->create(['role' => User::ROLE_MEMBER]);

        $this->actingAs($admin);
        $authorizer = app(PublishedProfileAuthorizer::class);

        // UI query scoping excludes the account from the staff listing.
        $ids = \App\Filament\Resources\Users\UserResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($plain->id, $ids);
        $this->assertNotContains($securityAdmin->id, $ids);
        $this->assertContains($admin->id, $ids);
    }

    public function test_emergency_recovery_command_requires_recovery_key(): void
    {
        config([
            'jannayaks.security.integrity.published_profile_admin.email' => 'jannayaks@protonmail.com',
            'jannayaks.security.integrity.recovery_key' => str_repeat('k', 32),
        ]);

        // Wrong key → nothing provisioned.
        $this->artisan('jannayaks:security-admin')
            ->expectsQuestion('Enter JANNAYAKS_SECURITY_RECOVERY_KEY', 'wrong-key')
            ->assertExitCode(1);
        $this->assertSame(0, User::query()->where('role', User::ROLE_SECURITY_ADMIN)->count());

        // Correct key → provisioned with the configured Proton identity.
        $this->artisan('jannayaks:security-admin')
            ->expectsQuestion('Enter JANNAYAKS_SECURITY_RECOVERY_KEY', str_repeat('k', 32))
            ->assertExitCode(0);

        $account = User::query()->where('role', User::ROLE_SECURITY_ADMIN)->first();
        $this->assertNotNull($account);
        $this->assertSame('jannayaks@protonmail.com', $account->email);
        $this->assertNotNull(StaffActionLog::query()->where('action', 'security.security_admin_provisioned')->first());
    }

    public function test_emergency_recovery_can_reset_password_and_totp(): void
    {
        config([
            'jannayaks.security.integrity.published_profile_admin.email' => 'jannayaks@protonmail.com',
            'jannayaks.security.integrity.recovery_key' => str_repeat('k', 32),
        ]);
        $key = str_repeat('k', 32);
        $this->artisan('jannayaks:security-admin')
            ->expectsQuestion('Enter JANNAYAKS_SECURITY_RECOVERY_KEY', $key)->assertExitCode(0);
        $account = User::query()->where('role', User::ROLE_SECURITY_ADMIN)->first();
        $oldHash = $account->password;

        $this->artisan('jannayaks:security-admin', ['--action' => 'reset-totp'])
            ->expectsQuestion('Enter JANNAYAKS_SECURITY_RECOVERY_KEY', $key)->assertExitCode(0);
        $account->refresh();
        $this->assertNull($account->totp_enabled_at);
        $this->assertIsArray($account->totp_recovery_codes);
        $this->assertSame($oldHash, $account->password, 'reset-totp must not alter the password.');

        $this->artisan('jannayaks:security-admin', ['--action' => 'reset-password'])
            ->expectsQuestion('Enter JANNAYAKS_SECURITY_RECOVERY_KEY', $key)->assertExitCode(0);
        $this->assertNotSame($oldHash, $account->refresh()->password, 'reset-password rotates the password.');
    }
}
