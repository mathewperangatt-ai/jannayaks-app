<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SourceMaterial;
use App\Services\StaffAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SourceMaterialDownloadController extends Controller
{
    public function __invoke(Request $request, SourceMaterial $sourceMaterial, StaffAuditLogger $auditLogger): StreamedResponse
    {
        Gate::authorize('download', $sourceMaterial);

        $disk = Storage::disk($sourceMaterial->storage_disk);
        if ($sourceMaterial->isPurged() || ! $disk->exists($sourceMaterial->storage_path)) {
            abort(404);
        }

        $auditLogger->log(
            action: 'source_material.download',
            subject: $sourceMaterial,
            after: [
                'application_id' => $sourceMaterial->application_id,
                'material_type' => $sourceMaterial->material_type,
            ],
        );

        $filename = $sourceMaterial->original_filename ?: basename($sourceMaterial->storage_path);

        return $disk->download(
            $sourceMaterial->storage_path,
            $filename,
            [
                'Content-Type' => $sourceMaterial->mime_type ?: 'application/octet-stream',
            ]
        );
    }
}
