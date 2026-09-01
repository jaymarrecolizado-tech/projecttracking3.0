<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Second step of sign-in for accounts with TOTP enabled. The user id is held
 * in the session (never a signed cookie) between the credential check and a
 * valid code; the challenge endpoint is throttled 5/min.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->get('two_factor_pending.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', ['status' => session('status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        $pending = $request->session()->get('two_factor_pending');
        $user = $pending ? User::find($pending['user_id'] ?? null) : null;

        if (! $user || ! $user->is_active || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('two_factor_pending');

            return redirect()->route('login')->withErrors(['email' => 'Your session expired — please sign in again.']);
        }

        if (! Totp::verify($user->two_factor_secret, $validated['code'])) {
            return back()->withErrors(['code' => 'The provided two-factor authentication code is invalid.']);
        }

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->forget('two_factor_pending');

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
