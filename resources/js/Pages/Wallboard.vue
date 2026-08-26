<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
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

// 14-day UP/DOWN bars, pure SVG — no chart library.
const chart = computed(() => {
    const days = props.stats.trend ?? [];
    const max = Math.max(1, ...days.map((d) => d.up + d.down));
    const width = 100 / Math.max(days.length, 1);
    return days.map((d, i) => ({
        ...d,
        x: i * width,
        upH: (d.up / max) * 100,
        downH: (d.down / max) * 100,
    }));
});
</script>

<template>
  <Head title="NOC Wallboard" />
  <div class="min-h-screen bg-[#0F1B2D] text-white p-6 flex flex-col">
    <header class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">FreeWiFi Network Status</h1>
        <p class="text-sm text-slate-400 mt-0.5">
          NOC Wallboard · refreshes every 30s · last update {{ new Date(stats.generated_at).toLocaleTimeString() }}
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
      </div>
      <div class="border-l-4 border-slate-600 pl-5 py-2">
        <div class="text-sm uppercase tracking-widest text-slate-400">No Data</div>
        <div class="text-6xl font-bold tabular-nums text-slate-400">{{ stats.no_data_today }}</div>
      </div>
      <div class="border-l-4 border-blue-500 pl-5 py-2">
        <div class="text-sm uppercase tracking-widest text-slate-400">Active Sites</div>
        <div class="text-6xl font-bold tabular-nums text-blue-400">{{ stats.total_sites }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 flex-1 min-h-0">
      <!-- 14-day trend -->
      <div class="lg:col-span-1">
        <h2 class="text-sm uppercase tracking-widest text-slate-400 mb-4">14-Day Trend</h2>
        <svg viewBox="0 0 100 60" preserveAspectRatio="none" class="w-full h-56">
          <g v-for="(bar, i) in chart" :key="i">
            <rect :x="bar.x + 0.15" :y="58 - bar.upH * 0.55" :width="width - 0.3" :height="bar.upH * 0.55" fill="#34d399" />
            <rect
              :x="bar.x + 0.15"
              :y="59 - bar.downH * 0.25"
              :width="width - 0.3"
              :height="bar.downH * 0.25"
              fill="#f87171"
            />
          </g>
        </svg>
        <div class="flex gap-6 mt-2 text-xs text-slate-400">
          <span><span class="inline-block w-2 h-2 bg-emerald-400 mr-1"></span>UP</span>
          <span><span class="inline-block w-2 h-2 bg-red-400 mr-1"></span>DOWN</span>
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
