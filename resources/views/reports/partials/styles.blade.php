{{-- Shared DomPDF stylesheet: tables for layout (no flex — DomPDF), teal accent lock. --}}
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    h1 { color: #0E5E6F; font-size: 20px; margin: 0 0 4px; }
    h2 { color: #0E5E6F; font-size: 14px; margin: 18px 0 6px; border-bottom: 2px solid #0E5E6F; padding-bottom: 3px; }
    .scope { color: #64748b; font-size: 10px; margin-bottom: 12px; }
    .kpis { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .kpis td { width: 25%; border: 1px solid #cbd5e1; padding: 8px; text-align: center; }
    .kpi-value { font-size: 20px; font-weight: bold; color: #0E5E6F; }
    .kpi-label { font-size: 9px; color: #64748b; }
    table.grid { width: 100%; border-collapse: collapse; margin: 8px 0; }
    table.grid th { background: #0E5E6F; color: #fff; padding: 5px 7px; text-align: left; font-size: 9px; }
    table.grid td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
    .bar-row td { border-bottom: none; padding: 2px 7px; }
    .bar-track { background: #e2e8f0; height: 10px; }
    .bar-fill-up { background: #15803d; height: 10px; }
    .bar-fill-teal { background: #0E5E6F; height: 10px; }
    .bar-fill-down { background: #dc2626; height: 10px; }
    .bar-fill-other { background: #d97706; height: 10px; }
    .badge { padding: 2px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; }
    .b-green { background: #dcfce7; color: #15803d; }
    .b-red { background: #fee2e2; color: #dc2626; }
    .b-amber { background: #fef3c7; color: #b45309; }
    .b-slate { background: #f1f5f9; color: #64748b; }
    .b-blue { background: #dbeafe; color: #1d4ed8; }
    .footer { margin-top: 24px; font-size: 8px; color: #94a3b8; text-align: center; }
    .muted { color: #64748b; }
</style>
