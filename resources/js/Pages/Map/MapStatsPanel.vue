<script setup>
// Coverage readout beside the map (Plan §Map 5, stats panel card).
defineProps({
    coverage: { type: Object, default: null },
});
const emit = defineEmits(['generate-pdf']);
</script>

<template>
  <div class="dict-card p-5">
    <div v-if="!coverage" class="text-sm text-slate-400">Loading coverage…</div>
    <template v-else>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
        <div>
          <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ coverage.totals.registered }}</div>
          <div class="text-xs uppercase tracking-wide text-slate-500">Registered sites</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-emerald-600 tabular-nums">{{ coverage.totals.actual }}</div>
          <div class="text-xs uppercase tracking-wide text-slate-500">Actual (deployed)</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-slate-800 tabular-nums">{{ coverage.totals.devices }}</div>
          <div class="text-xs uppercase tracking-wide text-slate-500">Devices</div>
        </div>
        <div>
          <div class="text-2xl font-bold text-blue-600 tabular-nums">{{ coverage.totals.coverage_pct }}%</div>
          <div class="text-xs uppercase tracking-wide text-slate-500">Coverage</div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <caption class="sr-only">Site coverage breakdown by site type</caption>
          <thead>
            <tr class="dict-table-header">
              <th class="px-3 py-2 text-left">Site Type</th>
              <th class="px-3 py-2 text-right">Registered</th>
              <th class="px-3 py-2 text-right">Actual</th>
              <th class="px-3 py-2 text-right">Devices</th>
              <th class="px-3 py-2 text-right">Coverage</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in coverage.rows" :key="row.site_type">
              <td class="px-3 py-2 text-sm text-slate-700">{{ row.label }}</td>
              <td class="px-3 py-2 text-sm text-right tabular-nums">{{ row.registered }}</td>
              <td class="px-3 py-2 text-sm text-right tabular-nums">{{ row.actual }}</td>
              <td class="px-3 py-2 text-sm text-right tabular-nums">{{ row.devices }}</td>
              <td class="px-3 py-2 text-sm text-right tabular-nums" :class="row.gap > 0 ? 'text-orange-600' : 'text-emerald-600'">
                {{ row.coverage_pct }}%
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <button
        type="button"
        class="mt-4 inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition"
        @click="emit('generate-pdf')"
      >
        Generate PDF report
      </button>
    </template>
  </div>
</template>
