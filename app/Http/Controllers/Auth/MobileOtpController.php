<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Msg91WidgetService;
use App\Support\SafeInternalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Indian mobile OTP login via the MSG91 OTP Widget.
 *
 * The widget (Default UI, token integration) sends and verifies the OTP
 * client-side and hands a JWT access token to the page; the browser posts
 * that token here, and ONLY a successful server-side verification of the
 * token with MSG91 (returning the verified mobile number) authenticates the
 * user. The kill switch (JANNAYAKS_OTP_LOGIN_ENABLED) hides and disables the
 * entire flow, exactly as before.
 */
class MobileOtpController extends Controller
{
    public function __construct(
        private readonly Msg91WidgetService $msg91,
    ) {}

    public function showWidget(Request $request): View|RedirectResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        return view('auth.otp-widget', [
            'widgetId' => (string) config('jannayaks.otp.msg91.widget_id', ''),
            'widgetToken' => (string) config('jannayaks.otp.msg91.widget_token', ''),
            'widgetConfigured' => $this->msg91->widgetConfigured(),
            'return' => SafeInternalUrl::intended($request->query('return')),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        $validated = $request->validate([
            'access_token' => ['required', 'string', 'max:4096'],
        ]);

        try {
            $verifiedMobile = $this->msg91->verifyAccessToken((string) $validated['access_token']);
            $user = $this->msg91->resolveUserForVerifiedMobile($verifiedMobile);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('auth.otp.request.show')
                ->withErrors(['otp' => $e->getMessage()]);
        }

        if (! $user->isActiveAccount()) {
            return redirect()->route('login')->withErrors([
                'otp' => 'This account is suspended and cannot sign in. Contact support for assistance.',
            ]);
        }

        // Bounded remember-me: 30 days instead of Laravel's ~5-year default.
        Auth::guard()->setRememberDuration(60 * 24 * 30);
        Auth::login($user, remember: true);
        $request->session()->regenerate();

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
     * OTP routes fail closed when login is disabled so no user is ever shown
     * a sign-in path that cannot complete.
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
