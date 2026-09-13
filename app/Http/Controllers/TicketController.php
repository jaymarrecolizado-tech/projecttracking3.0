<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Models\Device;
use App\Models\MaintenanceTicket;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = MaintenanceTicket::with([
            'site:id,location_name,municipality,province',
            'device:id,asset_tag',
            'reporter:id,name',
            'assignee:id,name',
        ])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority']),
            'counts' => [
                'open' => MaintenanceTicket::whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
                'critical_open' => MaintenanceTicket::where('priority', 'critical')->whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
            ],
            // Assignees must be able to work tickets, so offer active
            // tickets.manage holders instead of every account in the system.
            'users' => User::where('is_active', true)
                ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', 'tickets.manage'))
                ->orderBy('name')->get(['id', 'name']),
            // ponytail: dropdown caps at 500 rows; searchable async selects if the fleet outgrows it.
            'sites' => Site::orderBy('location_name')->limit(500)->get(['id', 'location_name']),
            'devices' => Device::orderBy('asset_tag')->limit(500)->get(['id', 'asset_tag']),
        ]);
    }

    public function store(StoreMaintenanceTicketRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['reported_by'] = auth()->id();
        MaintenanceTicket::create($data);

        return redirect()->route('tickets.index')->with('success', 'Ticket created.');
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $ticket->update($request->validatedWithTimestamps());

        return redirect()->back()->with('success', 'Ticket updated.');
    }
}
