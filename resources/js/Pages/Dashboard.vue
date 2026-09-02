<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    IconAlertTriangle, IconCalendarCheck, IconClipboardCheck,
    IconMap, IconReportAnalytics, IconRouter, IconUpload,
} from '@tabler/icons-vue';

defineProps({ stats: Object });

const maxTrend = (trend) => Math.max(...trend.map((d) => d.up + d.down), 1);
const maxProvince = (rows) => Math.max(...rows.map((r) => r.n), 1);

const severityStyles = {
    critical: 'border-red-900/40 bg-red-50',
    warning: 'border-amber-900/30 bg-amber-50',
    info: 'border-blue-900/20 bg-blue-50',
};
const severityText = {
    critical: 'text-red-600',
    warning: 'text-amber-600',
    info: 'text-blue-600',
};

function duration(h) {
    if (h >= 48) return Math.floor(h / 24) + 'd ' + (h % 24) + 'h';
    if (h >= 1) return h + 'h';
    return '<1h';
}
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-lg text-slate-800 leading-tight">Dashboard</h2>
        <Link :href="route('wallboard')" class="text-sm font-medium text-blue-600 hover:text-blue-800">
          Open NOC Wallboard →
        </Link>
      </div>
    </template>

    <div class="space-y-6">
      <!-- Hero counters -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
        <div class="bg-gradient-to-r from-teal-50 to-white rounded-lg border-l-4 border-teal-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Active Sites</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.active_sites }}</p>
        </div>
        <div class="bg-gradient-to-r from-green-50 to-white rounded-lg border-l-4 border-green-600 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">UP Today</p>
          <p class="text-2xl font-bold text-green-700 mt-1 tabular-nums">{{ stats.total_up_today }}</p>
        </div>
        <div class="rounded-lg border-l-4 p-5 shadow-sm" :class="stats.down_today > 0 ? 'from-red-50 to-white border-red-600' : 'from-slate-50 to-white border-slate-300'">
          <p class="text-sm font-medium text-slate-500">DOWN Today</p>
          <p class="text-2xl font-bold mt-1 tabular-nums" :class="stats.down_today > 0 ? 'text-red-700' : 'text-slate-400'">{{ stats.down_today }}</p>
        </div>
        <div class="bg-gradient-to-r from-amber-50 to-white rounded-lg border-l-4 border-amber-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">No Data</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.no_data_today }}</p>
        </div>
        <div class="bg-gradient-to-r from-blue-50 to-white rounded-lg border-l-4 border-blue-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Reported Today</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.reported_today }}<span class="text-sm text-slate-400">/{{ stats.active_sites }}</span></p>
          <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full bg-blue-500 rounded-full" :style="{ width: (stats.active_sites ? Math.min(100, stats.reported_today / stats.active_sites * 100) : 0) + '%' }"></div>
          </div>
        </div>
        <div class="bg-gradient-to-r from-emerald-50 to-white rounded-lg border-l-4 border-emerald-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Uptime 7d</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.uptime_pct_7d }}%</p>
        </div>
      </div>

      <!-- Trend + coverage -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div v-if="stats.trend?.length" class="dict-card p-6 lg:col-span-2">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Site status — last 14 days</h3>
          <div class="flex items-end gap-1 h-40">
            <div v-for="day in stats.trend" :key="day.date" class="flex-1 flex flex-col items-center gap-1 group relative">
              <div class="w-full flex flex-col justify-end h-32">
                <div
                  v-if="day.up > 0" class="w-full bg-green-500/90 rounded-t-sm group-hover:bg-green-500 transition-colors"
                  :style="{ height: (day.up / maxTrend(stats.trend) * 100) + '%' }"
                ></div>
                <div
                  v-if="day.down > 0" class="w-full bg-red-500/90 rounded-b-sm"
                  :style="{ height: (day.down / maxTrend(stats.trend) * 100) + '%' }"
                ></div>
              </div>
              <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ day.date }}</span>
              <div class="pointer-events-none absolute opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-xs rounded px-2 py-1 bottom-full mb-1 z-10 whitespace-nowrap">
                {{ day.date }}: {{ day.up }} UP · {{ day.down }} DOWN
              </div>
            </div>
          </div>
        </div>

        <!-- Coverage snapshot -->
        <div class="dict-card p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-800">Coverage</h3>
            <Link :href="route('reports.index')" class="text-xs font-medium text-blue-600 hover:text-blue-800">Reports →</Link>
          </div>

          <div class="mb-4">
            <div class="flex items-baseline justify-between">
              <span class="text-sm text-slate-500">Barangays with Free WiFi</span>
              <span class="text-lg font-bold text-teal-600 tabular-nums">{{ stats.barangay_coverage?.coverage_pct ?? 0 }}%</span>
            </div>
            <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full bg-teal-500 rounded-full" :style="{ width: (stats.barangay_coverage?.coverage_pct ?? 0) + '%' }"></div>
            </div>
            <p class="mt-1.5 text-xs text-slate-500 tabular-nums">
              {{ stats.barangay_coverage?.covered ?? 0 }} of {{ stats.barangay_coverage?.barangays ?? 0 }} barangays ·
              <span class="text-orange-600">{{ stats.barangay_coverage?.remaining ?? 0 }} remaining</span>
            </p>
          </div>

          <dl class="space-y-2 text-sm border-t border-slate-100 pt-3">
            <div class="flex justify-between"><dt class="text-slate-500">Sites (registered)</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.site_type_totals?.registered ?? 0 }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">With deployed device</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.site_type_totals?.actual ?? 0 }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Devices deployed</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.devices?.deployed ?? 0 }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">In stock</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.devices?.in_stock ?? 0 }}</dd></div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Warranty expiring ≤ 90d</dt>
              <dd class="font-semibold tabular-nums" :class="(stats.devices?.warranty_expiring ?? 0) > 0 ? 'text-orange-600' : 'text-slate-700'">
                {{ stats.devices?.warranty_expiring ?? 0 }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Triage: episodes + alerts -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="dict-card p-6 lg:col-span-2">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-800">Currently DOWN</h3>
            <Link :href="route('daily-ops.index')" class="text-xs font-medium text-blue-600 hover:text-blue-800">Daily Ops →</Link>
          </div>
          <table v-if="stats.down_episodes?.length" class="w-full">
            <caption class="sr-only">Sites currently reported down, longest first</caption>
            <thead>
              <tr class="dict-table-header">
                <th class="px-3 py-2 text-left">Site</th>
                <th class="px-3 py-2 text-left">Location</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-left">Down for</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="episode in stats.down_episodes" :key="episode.id" class="hover:bg-slate-50/50">
                <td class="px-3 py-2.5 text-sm font-medium text-slate-700">{{ episode.site }}</td>
                <td class="px-3 py-2.5 text-sm text-slate-500">{{ episode.where || '—' }}</td>
                <td class="px-3 py-2.5 text-sm">
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="episode.status === 'DOWN_SERVER' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'">
                    {{ episode.status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="px-3 py-2.5 text-sm tabular-nums" :class="episode.duration_h >= 24 ? 'text-red-600 font-semibold' : 'text-slate-600'">
                  {{ duration(episode.duration_h) }}
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="py-8 text-center">
            <IconClipboardCheck class="w-10 h-10 text-emerald-300 mx-auto mb-2" />
            <p class="text-sm text-slate-500">All sites operational — no open DOWN episodes.</p>
          </div>
        </div>

        <div class="dict-card p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-800">
              Active Alerts
              <span v-if="stats.alert_counts?.critical" class="ml-1 px-1.5 py-0.5 rounded text-[11px] font-bold bg-red-100 text-red-700">{{ stats.alert_counts.critical }} critical</span>
            </h3>
            <Link :href="route('alerts.index')" class="text-xs font-medium text-blue-600 hover:text-blue-800">All →</Link>
          </div>
          <div v-if="stats.active_alerts?.length" class="space-y-3">
            <div
              v-for="alert in stats.active_alerts" :key="alert.id"
              class="rounded-lg border px-4 py-3"
              :class="severityStyles[alert.severity]"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold uppercase tracking-wide" :class="severityText[alert.severity]">{{ alert.severity }}</span>
                <span class="text-[11px] text-slate-400">{{ new Date(alert.triggered_at).toLocaleDateString() }}</span>
              </div>
              <div class="mt-1 text-sm font-medium text-slate-700 truncate">{{ alert.site }}</div>
              <div class="text-xs text-slate-500 truncate">
                {{ alert.rule }}<span v-if="alert.observed !== null && alert.observed !== undefined"> · {{ alert.observed }}</span>
              </div>
            </div>
          </div>
          <div v-else class="py-8 text-center">
            <IconAlertTriangle class="w-10 h-10 text-emerald-300 mx-auto mb-2" />
            <p class="text-sm text-slate-500">No active alerts.</p>
          </div>
        </div>
      </div>

      <!-- Reach + provinces + imports + actions -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="dict-card p-6">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Network Reach</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Provinces</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.network?.provinces ?? 0 }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Municipalities</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.network?.municipalities ?? 0 }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Barangays</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ stats.network?.barangays ?? 0 }}</dd></div>
          </dl>
          <div class="mt-4 space-y-2">
            <div v-for="row in stats.network?.sites_per_province ?? []" :key="row.province">
              <div class="flex justify-between text-xs text-slate-500 mb-0.5">
                <span>{{ row.province }}</span><span class="tabular-nums">{{ row.n }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-blue-400 rounded-full" :style="{ width: (row.n / maxProvince(stats.network.sites_per_province) * 100) + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="dict-card p-6">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Recent Imports</h3>
          <ul v-if="stats.recent_imports?.length" class="divide-y divide-slate-100">
            <li v-for="batch in stats.recent_imports" :key="batch.id" class="py-2.5">
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium text-slate-700 truncate">{{ batch.original_filename || ('Batch #' + batch.id) }}</span>
                <span class="text-[11px] px-1.5 py-0.5 rounded-full font-semibold"
                  :class="batch.job_status === 'DONE' ? 'bg-emerald-100 text-emerald-700' : batch.job_status === 'FAILED' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">
                  {{ batch.job_status }}
                </span>
              </div>
              <div class="text-xs text-slate-400">{{ batch.importer?.name ?? '—' }} · {{ new Date(batch.created_at).toLocaleDateString() }}</div>
            </li>
          </ul>
          <p v-else class="text-sm text-slate-400">No imports yet.</p>
        </div>

        <div class="dict-card p-6">
          <h3 class="text-base font-semibold text-slate-800 mb-4">Quick Actions</h3>
          <div class="space-y-3">
            <Link :href="route('daily-ops.index')" class="group flex items-center gap-3 p-3 rounded-lg hover:bg-emerald-50 transition">
              <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center"><IconCalendarCheck class="w-5 h-5 text-emerald-600" /></div>
              <div><div class="text-sm font-medium text-slate-700 group-hover:text-emerald-700">Daily Ops Board</div><div class="text-xs text-slate-500">Submit today's site statuses</div></div>
            </Link>
            <Link :href="route('map.index')" class="group flex items-center gap-3 p-3 rounded-lg hover:bg-blue-50 transition">
              <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center"><IconMap class="w-5 h-5 text-blue-600" /></div>
              <div><div class="text-sm font-medium text-slate-700 group-hover:text-blue-700">Map View</div><div class="text-xs text-slate-500">Geo filters + deployed devices</div></div>
            </Link>
            <Link :href="route('devices.index')" class="group flex items-center gap-3 p-3 rounded-lg hover:bg-purple-50 transition">
              <div class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center"><IconRouter class="w-5 h-5 text-purple-600" /></div>
              <div><div class="text-sm font-medium text-slate-700 group-hover:text-purple-700">Device Registry</div><div class="text-xs text-slate-500">Inventory, assignments, warranty</div></div>
            </Link>
            <Link :href="route('reports.index')" class="group flex items-center gap-3 p-3 rounded-lg hover:bg-amber-50 transition">
              <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center"><IconReportAnalytics class="w-5 h-5 text-amber-600" /></div>
              <div><div class="text-sm font-medium text-slate-700 group-hover:text-amber-700">Reports</div><div class="text-xs text-slate-500">Coverage + summary PDFs</div></div>
            </Link>
            <Link :href="route('import.index')" class="group flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition">
              <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center"><IconUpload class="w-5 h-5 text-slate-600" /></div>
              <div><div class="text-sm font-medium text-slate-700 group-hover:text-slate-900">Import Data</div><div class="text-xs text-slate-500">Upload region workbooks</div></div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
