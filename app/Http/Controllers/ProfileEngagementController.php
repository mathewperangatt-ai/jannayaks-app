<?php

namespace App\Http\Controllers;

use App\Mail\ProfileContactMail;
use App\Models\Profile;
use App\Models\ProfileContactMessage;
use App\Models\ProfileReaction;
use App\Services\ProfileUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Public profile engagement: the Contact box and the Like / Applaud
 * reactions shown adjacent to it.
 *
 * Privacy rules (final frontend pass §15–§16):
 * - The profile owner's own contact details are never displayed or exposed.
 * - Visitor contact details are delivered to the owner privately only.
 * - Reactions show no public counts or lists; the owner privately sees
 *   name + broad location + reaction kind.
 */
class ProfileEngagementController extends Controller
{
    public function __construct(private ProfileUrlService $profileUrls) {}

    /**
     * Toggle a Like / Applaud reaction for the signed-in visitor.
     */
    public function react(Request $request, string $slug): RedirectResponse
    {
        $request->validate([
            'reaction' => ['required', Rule::in(ProfileReaction::REACTIONS)],
        ]);

        $profile = Profile::query()->whereRaw('lower(slug) = ?', [strtolower(trim($slug))])->firstOrFail();
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            abort(404);
        }

        $user = $request->user();
        $kind = (string) $request->input('reaction');

        // Broad location is derived from the user's own published profile
        // when they have one; never a precise address.
        $broadLocation = null;
        $ownProfile = $user->profile()->where('status', 'published')->first();
        if ($ownProfile !== null && filled($ownProfile->geography?->district_id)) {
            $broadLocation = \App\Models\GeoDistrict::query()->find($ownProfile->geography->district_id)?->name;
        }

        $existing = ProfileReaction::query()
            ->where('profile_id', $profile->id)
            ->where('user_id', $user->id)
            ->where('reaction', $kind)
            ->first();

        if ($existing) {
            $existing->delete(); // toggle off
        } else {
            ProfileReaction::query()->create([
                'profile_id' => $profile->id,
                'user_id' => $user->id,
                'reaction' => $kind,
                'broad_location' => $broadLocation,
            ]);

            // No public counters; only a private audit trail for the owner.
            Log::channel('security')->info('profile-reaction: recorded', [
                'profile_id' => $profile->id,
                'reaction' => $kind,
                'user_id' => $user->id,
            ]);
        }

        return back(303)->withFragment('wellwishers');
    }

    /**
     * Visitor → owner contact request. The message is stored and a private
     * notification is sent to the owner (or the internal demo/site inbox for
     * demonstration profiles). No visitor data is ever displayed publicly.
     */
    public function contact(Request $request, string $slug): RedirectResponse
    {
        $validated = $request->validate([
            'visitor_name' => ['required', 'string', 'min:2', 'max:120'],
            'visitor_mobile' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'message' => ['required', 'string', 'min:5', 'max:1000'],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ]);

        if ((string) $request->input('honey_bot', '') !== '') {
            return back(303)->withFragment('wellwishers'); // silently drop spam
        }

        $profile = Profile::query()->whereRaw('lower(slug) = ?', [strtolower(trim($slug))])->firstOrFail();
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            abort(404);
        }

        $throttleKey = 'profile-contact:'.md5(strtolower($slug)).':'.sha1((string) $request->ip());
        if (! RateLimiter::attempt($throttleKey, 3, fn () => null, 3600)) {
            return back(303)->withFragment('wellwishers')->withErrors([
                'contact' => 'Too many messages have been sent from this connection. Please try again later.',
            ]);
        }

        $message = ProfileContactMessage::query()->create([
            'profile_id' => $profile->id,
            'visitor_name' => $validated['visitor_name'],
            'visitor_mobile' => $validated['visitor_mobile'],
            'message' => $validated['message'],
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        $recipient = $this->ownerRecipient($profile);

        try {
            if ($recipient !== null) {
                Mail::to($recipient)->send(new ProfileContactMail($message, $profile));
                $message->forceFill(['delivered_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            // Delivery failure must not lose the stored message; log for follow-up.
            Log::warning('profile-contact: owner notification failed', [
                'profile_contact_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('security')->info('profile-contact: message recorded', [
            'profile_contact_message_id' => $message->id,
            'profile_id' => $profile->id,
        ]);

        return back(303)->withFragment('wellwishers')->with('contact_status', 'sent');
    }

    /**
     * Owner notification address. The owner's email is resolved server-side
     * only and never appears in frontend source. Demonstration profiles route
     * to the configured site inbox.
     */
    private function ownerRecipient(Profile $profile): ?string
    {
        $email = $profile->user?->email;
        if (is_string($email) && ! str_ends_with(strtolower($email), '@jannayaks.internal')) {
            return $email;
        }

        $site = trim((string) config('jannayaks.contact.public_email'));

        return $site !== '' ? $site : null;
    }
}
