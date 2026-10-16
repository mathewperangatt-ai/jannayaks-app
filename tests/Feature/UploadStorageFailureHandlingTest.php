<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\SourceMaterial;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config as FlysystemConfig;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Tests\TestCase;

/**
 * Storage write failures (e.g. an R2 AccessDenied on PutObject) must end in
 * the controllers' own error responses, not an unhandled HTTP 500. Each disk
 * is rebuilt with the same `throw` setting as its production configuration:
 * `source_materials` throws, the photo disk returns false.
 */
class UploadStorageFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('jannayaks.payments.testing_mode', true);
        Config::set('jannayaks.ai.image_enhancement.enabled', false);
        Config::set('jannayaks.media.public_disk', 'public');
        Config::set('online_interview.uploads.disk', 'source_materials');

        $this->member = User::factory()->create(['email_verified_at' => now()]);
        $this->application = Application::factory()->create([
            'user_id' => $this->member->id,
            'full_name' => 'Leela Varghese (TEST DATA)',
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
    }

    public function test_source_material_write_failure_returns_the_json_storage_error(): void
    {
        $this->useDiskThatRefusesWrites('source_materials', throw: true);

        $this->actingAs($this->member)
            ->postJson(route('applications.upload.material', ['application' => $this->application->id]), [
                'material' => $this->syntheticPdf(),
                'material_type' => 'resume',
            ])
            ->assertStatus(500)
            ->assertExactJson(['ok' => false, 'error' => 'Failed to store the uploaded file.']);

        $this->assertSame(0, SourceMaterial::query()->count());
    }

    public function test_source_material_write_failure_redirects_the_form_with_an_error(): void
    {
        $this->useDiskThatRefusesWrites('source_materials', throw: true);

        $this->actingAs($this->member)
            ->from(route('applications.upload.show', ['application' => $this->application->id]))
            ->post(route('applications.upload.material', ['application' => $this->application->id]), [
                'material' => $this->syntheticPdf(),
                'material_type' => 'resume',
            ])
            ->assertRedirect(route('applications.upload.show', ['application' => $this->application->id]))
            ->assertSessionHasErrors('application');

        $this->assertSame(0, SourceMaterial::query()->count());
    }

    public function test_photo_write_failure_redirects_with_a_photo_error(): void
    {
        $this->useDiskThatRefusesWrites('public', throw: false);
        $profile = Profile::query()->create([
            'user_id' => $this->member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Leela Varghese (TEST DATA)',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $this->application->forceFill(['profile_id' => $profile->id])->save();

        $this->actingAs($this->member)
            ->from(route('applications.media', $this->application))
            ->post(route('applications.media.store', $this->application), [
                'photo' => UploadedFile::fake()->image('portrait.jpg', 600, 800),
            ])
            ->assertRedirect(route('applications.media', $this->application))
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, MediaItem::query()->count());
    }

    private function useDiskThatRefusesWrites(string $disk, bool $throw): void
    {
        $adapter = new class(storage_path('framework/testing/disks/refusing-'.$disk)) extends LocalFilesystemAdapter
        {
            public function write(string $path, string $contents, FlysystemConfig $config): void
            {
                throw UnableToWriteFile::atLocation($path, 'AccessDenied (simulated)');
            }

            public function writeStream(string $path, $contents, FlysystemConfig $config): void
            {
                throw UnableToWriteFile::atLocation($path, 'AccessDenied (simulated)');
            }
        };

        Storage::set($disk, new FilesystemAdapter(new Filesystem($adapter), $adapter, ['throw' => $throw]));
    }

    private function syntheticPdf(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jn-synthetic-');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF\n");

        return new UploadedFile($path, 'resume.pdf', 'application/pdf', null, true);
    }
}
