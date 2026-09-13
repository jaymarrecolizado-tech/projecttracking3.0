<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Field-probe API tokens: probes authenticate to POST /api/heartbeat with a
 * Sanctum bearer token created here. Plaintext is shown exactly once.
 */
class ProbeTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Probe tokens can rewrite operational status, so issuance requires an
        // existing daily-write permission — not merely an authenticated account.
        abort_unless($request->user()->hasPermission('daily.create'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        // Rotation hygiene: drop this account's expired tokens on each
        // issuance (plus the monthly sanctum:prune-expired sweep) so the
        // token table cannot grow stale rows forever.
        Auth::user()->tokens()->where('expires_at', '<', now())->delete();

        $lifetime = config('sanctum.expiration');
        $token = Auth::user()->createToken(
            $validated['name'],
            ['heartbeat'],
            $lifetime ? now()->addMinutes((int) $lifetime) : null,
        );

        return back()
            ->with('success', 'Probe token created — copy it now, it will not be shown again.')
            ->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        Auth::user()->tokens()->where('id', $tokenId)->firstOrFail()->delete();

        return back()->with('success', 'Probe token revoked.');
    }
}
