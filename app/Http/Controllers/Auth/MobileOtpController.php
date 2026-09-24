<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MobileOtpService;
use App\Support\SafeInternalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MobileOtpController extends Controller
{
    public function __construct(
        private readonly MobileOtpService $otp,
    ) {}

    public function showRequestForm(Request $request): View|RedirectResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        return view('auth.otp-request', [
            'return' => SafeInternalUrl::intended($request->query('return')),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
        ]);

        $mobile = $this->otp->request($validated['mobile'], $request->ip());

        $request->session()->put('otp.pending_mobile', $mobile);

        return redirect()
            ->route('auth.otp.verify.show')
            ->with('status', 'OTP sent to your Indian mobile number.');
    }

    public function showVerifyForm(Request $request): View|RedirectResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        $mobile = $request->session()->get('otp.pending_mobile');
        if (! is_string($mobile) || $mobile === '') {
            return redirect()->route('auth.otp.request.show')
                ->withErrors(['mobile' => 'Request an OTP first.']);
        }

        return view('auth.otp-verify', [
            'mobile' => $mobile,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->otp->verify($validated['mobile'], $validated['otp'], $request->ip());

        if (! $user->isActiveAccount()) {
            $request->session()->forget('otp.pending_mobile');

            return redirect()->route('login')->withErrors([
                'otp' => 'This account is suspended and cannot sign in. Contact support for assistance.',
            ]);
        }

        // Bounded remember-me: 30 days instead of Laravel's ~5-year default.
        Auth::guard()->setRememberDuration(60 * 24 * 30);
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->forget('otp.pending_mobile');

        if (session()->has('apply.intent')) {
            return redirect()->route('apply.continue');
        }

        $intended = SafeInternalUrl::intended(session()->pull('url.intended'));
        if ($intended !== null) {
            return redirect()->to($intended);
        }

        return redirect()->route('apply');
    }

    /**
     * OTP routes fail closed when login is disabled (production until an SMS
     * provider exists) so no user is ever told a code was sent when it cannot be.
     */
    private function disabledResponse(): ?RedirectResponse
    {
        if (! (bool) config('jannayaks.otp.login_enabled', true)) {
            return redirect()->route('login')->withErrors([
                'otp' => 'Mobile OTP sign-in is currently unavailable. Please sign in with Google.',
            ]);
        }

        return null;
    }
}
