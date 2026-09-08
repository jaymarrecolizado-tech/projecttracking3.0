<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import SeverityChip from '@/Components/SeverityChip.vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ stats: Object });

let timer = null;
onMounted(() => {
    timer = setInterval(() => router.reload({ only: ['stats'] }), 30000);
});
onBeforeUnmount(() => clearInterval(timer));

const uptimeColor = computed(() =>
    props.stats.uptime_pct_7d >= 95 ? 'text-emerald-400' : props.stats.uptime_pct_7d >= 85 ? 'text-amber-400' : 'text-red-400',
);

// 14-day stacked bars, pure SVG — one segment per observed status.
const barWidth = computed(() => 100 / Math.max((props.stats.trend ?? []).length, 1));

const chart = computed(() => {
    const days = props.stats.trend ?? [];
    // All four statuses share the scale, so the stack height is honest.
    const max = Math.max(1, ...days.map((d) => d.up + d.down + d.no_nms + d.down_server));
    const width = barWidth.value;
    const unit = (n) => (n / max) * 52;

    return days.map((d, i) => {
        const up = unit(d.up);
        const noNms = unit(d.no_nms);
        const downServer = unit(d.down_server);
        const down = unit(d.down);

        // Stacked bottom-up from y = 58.
        return {
            ...d,
            x: i * width,
            segments: [
                { key: 'up', y: 58 - up, h: up, fill: '#34d399' },
                { key: 'no_nms', y: 58 - up - noNms, h: noNms, fill: '#fbbf24' },
                { key: 'down_server', y: 58 - up - noNms - downServer, h: downServer, fill: '#fb923c' },
                { key: 'down', y: 58 - up - noNms - downServer - down, h: down, fill: '#f87171' },
            ],
        };
    });
});
</script>

<template>
  <Head title="NOC Wallboard" />
  <div class="min-h-screen bg-ink text-white p-6 flex flex-col">
    <header class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">FreeWiFi Network Status</h1>
        <p class="text-sm text-slate-400 mt-0.5">
          FPIAP · NOC Wallboard · refreshes every 30s · last update {{ new Date(stats.generated_at).toLocaleTimeString() }}
        </p>
      </div>
      <div class="text-right">
        <div class="text-sm text-slate-400 uppercase tracking-widest">Uptime 7d</div>
        <div class="text-4xl font-bold tabular-nums" :class="uptimeColor">{{ stats.uptime_pct_7d }}%</div>
      </div>
    </header>

    <!-- Big readouts -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
      <div class="border-l-4 border-emerald-500 pl-5 py-2">
        <div class="text-sm uppercase tracking-widest text-slate-400">UP Today</div>
        <div class="text-6xl font-bold tabular-nums text-emerald-400">{{ stats.up_today }}</div>
      </div>
      <div class="border-l-4 border-red-500 pl-5 py-2">
        <div class="text-sm uppercase tracking-widest text-slate-400">DOWN Today</div>
        <div class="text-6xl font-bold tabular-nums text-red-400">{{ stats.down_today }}</div>
        <div v-if="stats.down_server_today" class="text-sm font-medium tabular-nums text-orange-400 mt-1">+{{ stats.down_server_today }} server</div>
      </div>
      <div class="border-l-4 border-slate-600 pl-5 py-2">
        <div class="text-sm uppercase tracking-widest text-slate-400">No Data</div>
        <div class="text-6xl font-bold tabular-nums text-slate-400">{{ stats.no_data_today }}</div>
        <div v-if="stats.no_nms_today" class="text-sm font-medium tabular-nums text-amber-400 mt-1">{{ stats.no_nms_today }} NO NMS</div>
      </div>
      <div class="border-l-4 border-slate-500 pl-5 py-2">
        <div class="text-sm uppercase tracking-widest text-slate-400">Active Sites</div>
        <div class="text-6xl font-bold tabular-nums text-white">{{ stats.total_sites }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 flex-1 min-h-0">
      <!-- 14-day trend -->
      <div class="lg:col-span-1">
        <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4">14-Day Trend</h2>
        <svg viewBox="0 0 100 60" preserveAspectRatio="none" class="w-full h-56">
          <g v-for="(bar, i) in chart" :key="i">
            <rect
              v-for="segment in bar.segments"
              :key="segment.key"
              :x="bar.x + 0.15"
              :y="segment.y"
              :width="barWidth - 0.3"
              :height="segment.h"
              :fill="segment.fill"
            />
            <title>{{ bar.date }}: {{ bar.up }} UP · {{ bar.down }} DOWN · {{ bar.no_nms }} NO NMS · {{ bar.down_server }} DOWN SERVER</title>
          </g>
        </svg>
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-slate-400">
          <span><span class="inline-block w-2 h-2 bg-emerald-400 mr-1"></span>UP</span>
          <span><span class="inline-block w-2 h-2 bg-red-400 mr-1"></span>DOWN</span>
          <span><span class="inline-block w-2 h-2 bg-amber-400 mr-1"></span>NO NMS</span>
          <span><span class="inline-block w-2 h-2 bg-orange-400 mr-1"></span>DOWN SERVER</span>
        </div>
      </div>

      <!-- Active alerts -->
      <div class="lg:col-span-1 min-w-0">
        <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4">
          Active Alerts — {{ stats.active_alerts?.length ?? 0 }}
        </h2>
        <div v-if="stats.active_alerts?.length" class="space-y-3 overflow-y-auto max-h-[50vh] pr-1">
          <div
            v-for="alert in stats.active_alerts" :key="alert.id"
            class="rounded-lg border px-4 py-3"
            :class="alert.severity === 'critical' ? 'border-red-900/50 bg-red-950/30' : alert.severity === 'warning' ? 'border-amber-900/50 bg-amber-950/30' : 'border-slate-700 bg-slate-900/40'"
          >
            <div class="flex items-center justify-between gap-2">
              <SeverityChip :severity="alert.severity" dark />
              <span class="text-[11px] text-slate-400">{{ new Date(alert.triggered_at).toLocaleTimeString() }}</span>
            </div>
            <div class="mt-1 text-sm font-medium truncate">{{ alert.site }}</div>
            <div class="text-xs text-slate-400 truncate">
              {{ alert.rule }}<span v-if="alert.observed !== null && alert.observed !== undefined"> · {{ alert.observed }}</span>
            </div>
          </div>
        </div>
        <div v-else class="rounded-lg border border-emerald-900/50 bg-emerald-950/30 px-6 py-6 text-center">
          <div class="text-sm font-semibold text-emerald-400">No active alerts</div>
        </div>
      </div>

      <!-- Down sites -->
      <div class="lg:col-span-2 min-w-0">
        <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4">
          Sites Down — {{ stats.down_sites?.length ?? 0 }}
        </h2>
        <div v-if="stats.down_sites?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 overflow-y-auto max-h-[50vh] pr-1">
          <div
            v-for="site in stats.down_sites"
            :key="site.id"
            class="flex items-center gap-3 rounded-lg border border-red-900/50 bg-red-950/30 px-4 py-3"
          >
            <span class="relative flex h-2.5 w-2.5 shrink-0">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span>
            <div class="min-w-0">
              <div class="font-medium truncate">{{ site.location_name }}</div>
              <div class="text-xs text-slate-400 truncate">{{ [site.municipality, site.province].filter(Boolean).join(', ') }}</div>
            </div>
          </div>
        </div>
        <div v-else class="rounded-lg border border-emerald-900/50 bg-emerald-950/30 px-6 py-10 text-center">
          <div class="text-2xl font-semibold text-emerald-400">All systems operational</div>
          <div class="text-sm text-slate-400 mt-1">No sites reporting DOWN.</div>
        </div>
      </div>
    </div>
  </div>
</template>
