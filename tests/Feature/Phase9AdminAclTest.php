<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\SourceMaterial;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Support\OnlineInterviewCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9AdminAclTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_filament_panel(): void
    {
        $member = User::factory()->create();

        $this->assertFalse($member->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($member)->get('/admin')->assertForbidden();
    }

    public function test_suspended_staff_cannot_access_filament_panel(): void
    {
        $editor = User::factory()->editor()->suspended()->create();
        $admin = User::factory()->admin()->suspended()->create();

        $this->assertFalse($editor->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_editor_and_support_can_access_panel_when_active(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $support = User::factory()->support()->create();

        foreach ([$admin, $editor, $support] as $staff) {
            $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
            $this->actingAs($staff)->get('/admin')->assertOk();
        }
    }

    public function test_editor_cannot_access_payment_or_user_resources(): void
    {
        $editor = User::factory()->editor()->create();
        $payment = Payment::query()->create([
            'application_id' => Application::factory()->create()->id,
            'transaction_reference' => 'P9-PAY-'.uniqid(),
            'gateway' => Payment::GATEWAY_MANUAL,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '3000.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'event_type' => 'application_package',
        ]);
        $member = User::factory()->create();

        $this->actingAs($editor)
            ->get('/admin/payments')
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/payments/'.$payment->id)
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/users/'.$member->id)
            ->assertForbidden();
    }

    public function test_support_can_view_applications_but_cannot_edit_or_download_source(): void
    {
        $support = User::factory()->support()->create();
        $application = Application::factory()->paid()->create([
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        Storage::fake('private_uploads');
        Storage::disk('private_uploads')->put('source-materials/applications/'.$application->id.'/doc.pdf', 'pdf-bytes');

        $material = SourceMaterial::query()->create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'material_type' => 'biography',
            'storage_disk' => 'private_uploads',
            'storage_path' => 'source-materials/applications/'.$application->id.'/doc.pdf',
            'original_filename' => 'doc.pdf',
            'mime_type' => 'application/pdf',
            'file_bytes' => 9,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($support)->get('/admin/applications')->assertOk();
        $this->actingAs($support)->get('/admin/applications/'.$application->id)->assertOk();
        $this->actingAs($support)->get('/admin/applications/'.$application->id.'/edit')->assertForbidden();

        $this->assertFalse($support->can('download', $material));
        $this->actingAs($support)
            ->get(route('staff.source-materials.download', $material))
            ->assertForbidden();
    }

    public function test_editor_can_access_applications_and_download_source_material(): void
    {
        $editor = User::factory()->editor()->create();
        $application = Application::factory()->paid()->create([
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        Storage::fake('private_uploads');
        Storage::disk('private_uploads')->put('source-materials/applications/'.$application->id.'/bio.pdf', 'hello-pdf');

        $material = SourceMaterial::query()->create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'material_type' => 'biography',
            'storage_disk' => 'private_uploads',
            'storage_path' => 'source-materials/applications/'.$application->id.'/bio.pdf',
            'original_filename' => 'bio.pdf',
            'mime_type' => 'application/pdf',
            'file_bytes' => 9,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($editor)->get('/admin/applications')->assertOk();
        $this->actingAs($editor)->get('/admin/applications/'.$application->id)->assertOk();
        $this->actingAs($editor)->get('/admin/applications/'.$application->id.'/edit')->assertOk();

        $this->actingAs($editor)
            ->get(route('staff.source-materials.download', $material))
            ->assertOk();

        $this->assertDatabaseHas('staff_action_logs', [
            'action' => 'source_material.download',
            'actor_user_id' => $editor->id,
            'subject_id' => $material->id,
        ]);
    }

    public function test_member_cannot_download_staff_source_material_route(): void
    {
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create();

        Storage::fake('private_uploads');
        Storage::disk('private_uploads')->put('source-materials/applications/'.$application->id.'/x.pdf', 'x');

        $material = SourceMaterial::query()->create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'material_type' => 'other',
            'storage_disk' => 'private_uploads',
            'storage_path' => 'source-materials/applications/'.$application->id.'/x.pdf',
            'original_filename' => 'x.pdf',
            'mime_type' => 'application/pdf',
            'file_bytes' => 1,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('staff.source-materials.download', $material))
            ->assertForbidden();
    }

    public function test_admin_can_access_payments_and_users(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = Payment::query()->create([
            'application_id' => Application::factory()->create()->id,
            'transaction_reference' => 'P9-ADMIN-'.uniqid(),
            'gateway' => Payment::GATEWAY_MANUAL,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '3000.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'event_type' => 'application_package',
        ]);

        $this->actingAs($admin)->get('/admin/payments')->assertOk();
        $this->actingAs($admin)->get('/admin/payments/'.$payment->id)->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_editor_status_change_is_audited_and_restricted(): void
    {
        $editor = User::factory()->editor()->create();
        $application = Application::factory()->paid()->create([
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $updated = app(ApplicationWorkflowService::class)->updateStaffFields(
            $application,
            ['status' => Application::STATUS_IN_EDITORIAL_REVIEW],
            $editor,
        );

        $this->assertSame(Application::STATUS_IN_EDITORIAL_REVIEW, $updated->status);
        $this->assertDatabaseHas('staff_action_logs', [
            'action' => 'application.staff_update',
            'actor_user_id' => $editor->id,
            'subject_id' => $application->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ApplicationWorkflowService::class)->updateStaffFields(
            $application->fresh(),
            ['status' => Application::STATUS_PUBLISHED],
            $editor,
        );
    }

    public function test_interview_submit_advances_status_to_awaiting_editorial_when_paid(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id' => $user->id,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Ready For Editorial',
        ])->paid()->create();

        $requiredIds = OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $answers = [];
        foreach ($requiredIds as $id) {
            $answers[$id] = 'Answer for '.$id;
        }

        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $app->id]), [
            'answers' => $answers,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('online-interview.submit', ['application' => $app->id]))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            ]);

        $app->refresh();
        $this->assertSame(Application::STATUS_AWAITING_EDITORIAL_REVIEW, $app->status);
        $this->assertNotNull($app->online_interview_completed_at);
    }

    public function test_editor_cannot_view_payment_policy_directly(): void
    {
        $editor = User::factory()->editor()->create();
        $payment = Payment::query()->create([
            'application_id' => Application::factory()->create()->id,
            'transaction_reference' => 'P9-POL-'.uniqid(),
            'gateway' => Payment::GATEWAY_MANUAL,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '8000.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'event_type' => 'application_package',
        ]);

        $this->assertFalse($editor->can('viewAny', Payment::class));
        $this->assertFalse($editor->can('view', $payment));
        $this->assertTrue($editor->can('viewAny', Application::class));
    }

    public function test_support_cannot_access_editorial_content_resource(): void
    {
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/editorial-contents')->assertForbidden();
    }

    public function test_staff_can_view_account_email_but_only_admin_sees_private_contact(): void
    {
        $member = User::factory()->create([
            'email' => 'applicant-account@example.com',
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'preferred_contact_email' => 'preferred-private@example.com',
            'preferred_contact_mobile' => '919876543210',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $support = User::factory()->support()->create();

        foreach ([$admin, $editor, $support] as $staff) {
            $this->assertTrue($staff->canViewApplicantAccountEmail());
            $this->assertTrue($staff->can('viewAccountEmail', $application));
        }

        $this->assertTrue($admin->can('viewContactDetails', $application));
        $this->assertFalse($editor->can('viewContactDetails', $application));
        $this->assertFalse($support->can('viewContactDetails', $application));

        $this->actingAs($editor)
            ->get('/admin/applications/'.$application->id)
            ->assertOk()
            ->assertSee('applicant-account@example.com', false)
            ->assertDontSee('preferred-private@example.com', false)
            ->assertDontSee('919876543210', false);

        $this->actingAs($support)
            ->get('/admin/applications/'.$application->id)
            ->assertOk()
            ->assertSee('applicant-account@example.com', false)
            ->assertDontSee('preferred-private@example.com', false)
            ->assertDontSee('919876543210', false);

        $this->actingAs($admin)
            ->get('/admin/applications/'.$application->id)
            ->assertOk()
            ->assertSee('applicant-account@example.com', false)
            ->assertSee('preferred-private@example.com', false)
            ->assertSee('919876543210', false);
    }

    public function test_admin_can_set_publication_terminal_statuses_and_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $application = Application::factory()->paid()->create([
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'source_method' => 'direct_submission',
        ]);
        $application->forceFill(['status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW])->save();

        $service = app(ApplicationWorkflowService::class);

        $awaitingPublication = $service->updateStaffFields(
            $application,
            ['status' => Application::STATUS_AWAITING_PUBLICATION],
            $admin,
        );
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $awaitingPublication->status);

        $published = $service->updateStaffFields(
            $awaitingPublication,
            ['status' => Application::STATUS_PUBLISHED],
            $admin,
        );
        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);

        $this->assertDatabaseHas('staff_action_logs', [
            'action' => 'application.staff_update',
            'actor_user_id' => $admin->id,
            'subject_id' => $application->id,
        ]);

        $this->assertTrue(
            StaffActionLog::query()
                ->where('actor_user_id', $admin->id)
                ->where('subject_id', $application->id)
                ->where('action', 'application.staff_update')
                ->get()
                ->contains(fn ($log): bool => ($log->after['status'] ?? null) === Application::STATUS_PUBLISHED)
        );
    }

    public function test_support_cannot_publish_or_change_workflow_status(): void
    {
        $support = User::factory()->support()->create();
        $application = Application::factory()->paid()->create();
        $beforeStatus = $application->fresh()->status;

        $result = app(ApplicationWorkflowService::class)->updateStaffFields(
            $application,
            ['status' => Application::STATUS_PUBLISHED],
            $support,
        );

        $this->assertSame($beforeStatus, $result->status);
        $this->assertDatabaseMissing('staff_action_logs', [
            'actor_user_id' => $support->id,
            'action' => 'application.staff_update',
            'subject_id' => $application->id,
        ]);
    }

    public function test_editor_cannot_set_awaiting_publication_or_published(): void
    {
        $editor = User::factory()->editor()->create();
        $application = Application::factory()->paid()->create([
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
        ]);
        // paid() afterCreating may overwrite status — force the editorial state.
        $application->forceFill(['status' => Application::STATUS_IN_EDITORIAL_REVIEW])->save();

        foreach ([
            Application::STATUS_AWAITING_PUBLICATION,
            Application::STATUS_PUBLISHED,
        ] as $forbidden) {
            try {
                app(ApplicationWorkflowService::class)->updateStaffFields(
                    $application->fresh(),
                    ['status' => $forbidden],
                    $editor,
                );
                $this->fail('Expected InvalidArgumentException for editor status '.$forbidden);
            } catch (\InvalidArgumentException) {
                $this->assertSame(
                    Application::STATUS_IN_EDITORIAL_REVIEW,
                    $application->fresh()->status
                );
            }
        }
    }
}
