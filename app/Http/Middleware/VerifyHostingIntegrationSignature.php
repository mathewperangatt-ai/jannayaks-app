<?php

namespace App\Http\Middleware;

use App\Hosting\HostingIntegrationAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyHostingIntegrationSignature
{
    public function __construct(private HostingIntegrationAuthenticator $authenticator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authenticator->isConfigured()) {
            Log::channel('security')->warning('hosting-integration: secret not configured');

            return response()->json(['message' => 'Service unavailable.'], 503)
                ->header('Cache-Control', 'no-store');
        }

        $reason = $this->authenticator->validationFailureReason($request);
        if ($reason !== null) {
            Log::channel('security')->info('hosting-integration: authentication failed', [
                'reason' => $reason,
                'path' => $request->getPathInfo(),
            ]);

            return response()->json(['message' => 'Unauthorized.'], 401)
                ->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
