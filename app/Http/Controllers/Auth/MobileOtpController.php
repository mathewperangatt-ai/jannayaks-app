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

    public function showRequestForm(Request $request): View
    {
        return view('auth.otp-request', [
            'return' => SafeInternalUrl::intended($request->query('return')),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
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
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->otp->verify($validated['mobile'], $validated['otp'], $request->ip());

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
}
