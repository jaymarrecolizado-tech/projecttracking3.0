{{-- KPI strip: table of value/label cells. Expects $kpis as [[value, label], ...]. --}}
<table class="kpis">
    <tr>
        @foreach($kpis as [$value, $label])
        <td>
            <div class="kpi-value">{{ $value }}</div>
            <div class="kpi-label">{{ $label }}</div>
        </td>
        @endforeach
    </tr>
</table>
