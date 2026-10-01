<?php

namespace App\Http\Controllers;

use App\Mail\RecommendationReceivedMail;
use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * "Recommend Someone You May Know" (§17).
 *
 * A restrained public form for suggesting ANOTHER person whose life, work or
 * public contribution may be appropriate for inclusion. Distinct from
 * "Request an Invitation", which concerns the person themselves.
 */
class RecommendationController extends Controller
{
    public function show(): View
    {
        return view('public.recommend');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recommender_name' => ['required', 'string', 'min:2', 'max:120'],
            'recommender_contact' => ['required', 'string', 'max:255'],
            'recommended_name' => ['required', 'string', 'min:2', 'max:120'],
            'recommended_location' => ['nullable', 'string', 'max:120'],
            'recommended_role' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'supporting_info' => ['nullable', 'string', 'max:2000'],
            'acknowledged_terms' => ['accepted'],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ]);

        if ((string) $request->input('honey_bot', '') !== '') {
            return redirect()->route('recommend.show'); // silently drop spam
        }

        $throttleKey = 'recommend:'.sha1((string) $request->ip());
        if (! RateLimiter::attempt($throttleKey, 3, fn () => null, 3600)) {
            return back()->withErrors([
                'recommend' => 'Several recommendations have already been submitted from this connection. Please try again later.',
            ]);
        }

        $recommendation = Recommendation::query()->create($validated + [
            'acknowledged_terms' => true,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        $inbox = trim((string) config('jannayaks.contact.public_email'));
        try {
            if ($inbox !== '') {
                Mail::to($inbox)->send(new RecommendationReceivedMail($recommendation));
                $recommendation->forceFill(['notified_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('recommendation: notification failed', [
                'recommendation_id' => $recommendation->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('security')->info('recommendation: recorded', [
            'recommendation_id' => $recommendation->id,
        ]);

        return redirect()->route('recommend.show')->with('recommendation_status', 'received');
    }
}
