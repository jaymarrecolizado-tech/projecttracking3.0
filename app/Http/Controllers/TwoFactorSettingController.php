<?php

namespace App\Http\Controllers;

use App\Support\Totp;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * TOTP enrollment for privileged accounts (gated by users.manage on the
 * routes). Flow: start setup → scan QR → confirm with a live code → enabled.
 * Disabling requires a current valid code so a stolen session can't drop it.
 */
class TwoFactorSettingController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->hasTwoFactorEnabled(), 409);

        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_enabled_at' => null])->save();

        $request->session()->put('two_factor_setup', [
            'secret' => $secret,
            'qr' => (new QRCode(new QROptions([
                'outputInterface' => QRMarkupSVG::class,
                'outputBase64' => false,
                'svgAddXmlHeader' => false,
                'scale' => 5,
            ])))->render(Totp::otpauthUri(config('app.name'), $user->email, $secret)),
        ]);

        return back()->with('success', 'Scan the QR code with your authenticator app, then confirm with a current code.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();
        abort_if($user->hasTwoFactorEnabled() || ! $user->two_factor_secret, 409);

        if (! Totp::verify($user->two_factor_secret, $validated['code'])) {
            return back()->withErrors(['code' => 'That code isn\'t valid yet — check your authenticator and try again.']);
        }

        $user->forceFill(['two_factor_enabled_at' => now()])->save();
        $request->session()->forget('two_factor_setup');

        return back()->with('success', 'Two-factor authentication is now protecting your account.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        $user = $request->user();
        if (! $user->hasTwoFactorEnabled() || ! Totp::verify($user->two_factor_secret, $validated['code'])) {
            return back()->withErrors(['code' => 'That code isn\'t valid — two-factor authentication is still enabled.']);
        }

        $user->forceFill(['two_factor_secret' => null, 'two_factor_enabled_at' => null])->save();

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }
}
