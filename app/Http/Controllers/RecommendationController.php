<?php

namespace App\Http\Controllers;

use App\Mail\RecommendationInvitationMail;
use App\Models\Recommendation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Recommend Someone You May Know".
 *
 * The recommender describes another person. After the existing consent,
 * duplicate and rate-limit checks, Jannayaks sends one invitation to the
 * recommended person's email from its own configured sender.
 */
class RecommendationController extends Controller
{
    public const NOTICE_VERSION = 'recommendation.invitation.v1';

    public function show(): View
    {
        return view('public.recommend');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recommender_name' => [
                'nullable',
                'string',
                'max:120',
                Rule::when($request->boolean('share_name'), ['required', 'min:2']),
            ],
            'recommended_name' => ['required', 'string', 'min:2', 'max:120'],
            'recommended_email' => ['required', 'email:strict', 'max:255'],
            'recommended_location' => ['nullable', 'string', 'max:120'],
            'recommended_role' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'supporting_info' => ['nullable', 'string', 'max:2000'],
            'acknowledged_terms' => ['accepted'],
            'share_name' => ['nullable', 'boolean'],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ], [
            'recommender_name.required' => 'Enter your name so it can be included, or leave “Share my name” unselected.',
            'recommender_name.min' => 'Enter your name so it can be included, or leave “Share my name” unselected.',
            'recommended_email.required' => 'Enter the email address of the person you are recommending.',
            'recommended_email.email' => 'Enter a valid email address for the person you are recommending.',
        ]);

        if ((string) $request->input('honey_bot', '') !== '') {
            return redirect()->route('recommend.show');
        }

        $throttleKey = 'recommend:'.sha1((string) $request->ip());
        if (! RateLimiter::attempt($throttleKey, 3, fn () => null, 3600)) {
            return back()->withInput()->withErrors([
                'recommend' => 'Several recommendations have already been submitted from this connection. Please try again later.',
            ]);
        }

        $email = strtolower(trim((string) $validated['recommended_email']));
        $alreadyInvited = Recommendation::query()
            ->whereRaw('lower(recommended_email) = ?', [$email])
            ->whereNotNull('invitation_sent_at')
            ->where('invitation_sent_at', '>=', now()->subDays(30))
            ->exists();
        if ($alreadyInvited) {
            return back()->withInput()->withErrors([
                'recommended_email' => 'An invitation has already been sent to this email address. Another invitation cannot be sent yet.',
            ]);
        }

        $shareName = $request->boolean('share_name');
        $recommenderName = trim((string) ($validated['recommender_name'] ?? ''));

        $recommendation = Recommendation::query()->create([
            'recommender_name' => $recommenderName,
            'recommender_contact' => '',
            'recommended_name' => $validated['recommended_name'],
            'recommended_email' => $email,
            'recommended_location' => $validated['recommended_location'] ?? null,
            'recommended_role' => $validated['recommended_role'] ?? null,
            'reason' => $validated['reason'],
            'supporting_info' => $validated['supporting_info'] ?? null,
            'acknowledged_terms' => true,
            'share_recommender_name' => $shareName,
            'consent_notice_version' => self::NOTICE_VERSION,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        $from = trim((string) config('mail.from.address'));
        if ($from === '') {
            $this->recordInvitationFailure($recommendation, 'missing_sender');

            return back()->withInput()->withErrors([
                'recommend' => 'The invitation was not sent. Nothing was recorded as delivered. Please try again later.',
            ]);
        }

        try {
            Mail::to($email)->send(new RecommendationInvitationMail($recommendation));
        } catch (\Throwable $e) {
            $this->recordInvitationFailure($recommendation, $e::class);

            return back()->withInput()->withErrors([
                'recommend' => 'The invitation was not sent. Nothing was recorded as delivered. Please try again later.',
            ]);
        }

        $recommendation->forceFill(['invitation_sent_at' => now()])->save();

        Log::channel('security')->info('recommendation: invitation sent', [
            'recommendation_id' => $recommendation->id,
            'share_recommender_name' => $shareName,
        ]);

        return redirect()->route('recommend.show')->with('recommendation_status', 'sent');
    }

    private function recordInvitationFailure(Recommendation $recommendation, string $reason): void
    {
        $recommendation->forceFill([
            'invitation_sent_at' => null,
            'invitation_failed_at' => now(),
        ])->save();

        Log::warning('recommendation: invitation failed', [
            'recommendation_id' => $recommendation->id,
            'reason' => $reason,
        ]);
    }
}
