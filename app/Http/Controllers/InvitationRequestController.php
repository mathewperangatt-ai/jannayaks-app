<?php

namespace App\Http\Controllers;

use App\Mail\InvitationRequestMail;
use App\Models\InvitationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * "Request an Invitation" (§18).
 *
 * The person THEMSELVES asking to be considered for inclusion. Kept clearly
 * separate from "Recommend Someone You May Know", which is about another person.
 */
class InvitationRequestController extends Controller
{
    public function show(): View
    {
        return view('public.invitation-request');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'contact' => ['required', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'acknowledged_terms' => ['accepted'],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ]);

        if ((string) $request->input('honey_bot', '') !== '') {
            return redirect()->route('invitation-request.show');
        }

        $throttleKey = 'invitation-request:'.sha1((string) $request->ip());
        if (! RateLimiter::attempt($throttleKey, 3, fn () => null, 3600)) {
            return back()->withErrors([
                'invitation' => 'Several requests have already been submitted from this connection. Please try again later.',
            ]);
        }

        $invitation = InvitationRequest::query()->create($validated + [
            'acknowledged_terms' => true,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        $inbox = trim((string) config('jannayaks.contact.public_email'));
        try {
            if ($inbox !== '') {
                Mail::to($inbox)->send(new InvitationRequestMail($invitation));
                $invitation->forceFill(['notified_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('invitation-request: notification failed', [
                'invitation_request_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('security')->info('invitation-request: recorded', [
            'invitation_request_id' => $invitation->id,
        ]);

        return redirect()->route('invitation-request.show')->with('invitation_status', 'received');
    }
}
