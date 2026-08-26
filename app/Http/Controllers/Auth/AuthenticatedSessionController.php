<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        // Public, aggregate figures for the login panel — makes the page feel
        // like the operations console it is, not a generic auth form.
        $recent = SiteDailyStatus::query()
            ->join('sites', 'sites.id', '=', 'site_daily_statuses.site_id')
            ->orderByDesc('site_daily_statuses.date')
            ->orderByDesc('site_daily_statuses.id')
            ->limit(4)
            ->get([
                'sites.ap_site_code',
                'sites.location_name',
                'sites.municipality',
                'site_daily_statuses.status',
                'site_daily_statuses.date',
            ]);

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'network' => [
                'sites' => Site::count(),
                'active' => Site::where('status', 'active')->count(),
                'provinces' => Site::whereNotNull('province')->distinct()->count('province'),
                'upToday' => SiteDailyStatus::whereDate('date', today())->where('status', 'UP')->count(),
            ],
            // Joined site columns ride on the model as dynamic attributes.
            'recent' => $recent->map(fn ($r) => [
                'code' => data_get($r, 'ap_site_code'),
                'name' => data_get($r, 'location_name'),
                'municipality' => data_get($r, 'municipality'),
                'status' => $r->status,
                'date' => $r->date->toDateString(),
            ]),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
