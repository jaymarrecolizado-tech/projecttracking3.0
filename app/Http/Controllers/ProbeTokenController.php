<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Field-probe API tokens: probes authenticate to POST /api/heartbeat with a
 * Sanctum bearer token created here. Plaintext is shown exactly once.
 */
class ProbeTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $token = Auth::user()->createToken($validated['name'], ['heartbeat']);

        return back()
            ->with('success', 'Probe token created — copy it now, it will not be shown again.')
            ->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId)
    {
        Auth::user()->tokens()->where('id', $tokenId)->firstOrFail()->delete();

        return back()->with('success', 'Probe token revoked.');
    }
}
