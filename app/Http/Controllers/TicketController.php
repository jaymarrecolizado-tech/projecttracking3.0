<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceTicketRequest;
use App\Http\Requests\UpdateMaintenanceTicketRequest;
use App\Models\Device;
use App\Models\MaintenanceTicket;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
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
            'users' => User::orderBy('name')->get(['id', 'name']),
            'sites' => Site::orderBy('location_name')->get(['id', 'location_name']),
            'devices' => Device::orderBy('asset_tag')->get(['id', 'asset_tag']),
        ]);
    }

    public function store(StoreMaintenanceTicketRequest $request)
    {
        $data = $request->validated();
        $data['reported_by'] = auth()->id();
        MaintenanceTicket::create($data);

        return redirect()->route('tickets.index')->with('success', 'Ticket created.');
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $ticket)
    {
        $ticket->update($request->validatedWithTimestamps());

        return redirect()->back()->with('success', 'Ticket updated.');
    }
}
