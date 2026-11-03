<?php

namespace App\Http\Controllers\Hosting;

use App\Hosting\HostingProfilePayloadBuilder;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only IconsHerald Hosting integration endpoints.
 * Authentication is enforced by VerifyHostingIntegrationSignature.
 */
class HostingIntegrationController extends Controller
{
    public function __construct(private HostingProfilePayloadBuilder $payloads) {}

    public function show(string $slug): JsonResponse
    {
        try {
            $profile = $this->payloads->findEligibleBySlug($slug);
            if ($profile === null) {
                return $this->jsonError('Not found.', 404);
            }

            return $this->jsonOk($this->payloads->buildProfilePayload($profile));
        } catch (Throwable $e) {
            Log::error('hosting-integration: profile read failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->jsonError('Service unavailable.', 503);
        }
    }

    public function sitemap(Request $request): JsonResponse
    {
        try {
            $defaultPerPage = (int) config('hosting_integration.sitemap.default_per_page', 100);
            $maxPerPage = (int) config('hosting_integration.sitemap.max_per_page', 200);
            $perPage = min(max(1, (int) $request->query('per_page', $defaultPerPage)), max(1, $maxPerPage));
            $page = max(1, (int) $request->query('page', 1));

            $paginator = $this->payloads
                ->eligibleSitemapQuery()
                ->with('application')
                ->orderBy('published_at')
                ->orderBy('id')
                ->paginate(
                    perPage: $perPage,
                    columns: ['id', 'slug', 'published_at'],
                    pageName: 'page',
                    page: $page,
                );

            $profiles = collect($paginator->items())
                ->map(fn (Profile $profile): array => $this->payloads->buildSitemapEntry($profile))
                ->values()
                ->all();

            return $this->jsonOk([
                'profiles' => $profiles,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('hosting-integration: sitemap list failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->jsonError('Service unavailable.', 503);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonOk(array $data): JsonResponse
    {
        return response()->json($data, 200)
            ->header('Cache-Control', 'no-store');
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status)
            ->header('Cache-Control', 'no-store');
    }
}
