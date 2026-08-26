<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconCalendarCheck, IconCircleCheck, IconCircleMinus, IconCircleX } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

const props = defineProps({
    date: String,
    rows: Array,
    projects: Array,
    provinces: Array,
    counts: Object,
    filters: Object,
});

// Local edit state keyed by site_id — untouched rows are sent unchanged.
const edits = ref({});
const filterForm = useForm({ project_id: props.filters.project_id ?? '', province: props.filters.province ?? '' });

function rowState(row) {
    return edits.value[row.site_id] ?? {
        status: row.status,
        bandwidth_utilization_mbps: row.bandwidth_utilization_mbps,
        total_unique_users: row.total_unique_users,
        remarks: row.remarks,
    };
}

function setField(siteId, field, value) {
    edits.value[siteId] = { ...rowState(props.rows.find((r) => r.site_id === siteId)), ...edits.value[siteId], [field]: value };
}

function toggle(siteId, status) {
    const current = rowState(props.rows.find((r) => r.site_id === siteId));
    setField(siteId, 'status', current.status === status ? '' : status);
}

const dirtyEntries = computed(() =>
    Object.entries(edits.value)
        .filter(([, state]) => state.status)
        .map(([siteId, state]) => ({ site_id: Number(siteId), ...state })),
);

const reportedCount = computed(() => {
    const editedIds = new Set(Object.keys(edits.value).filter((id) => edits.value[id].status));
    const base = props.rows.filter((r) => r.status && !editedIds.has(String(r.site_id))).length;
    return base + editedIds.size;
});

function applyFilter() {
    router.get(route('daily-ops.index'), { date: props.date, ...filterForm.data() }, { preserveState: true });
}

function send(action) {
    if (!dirtyEntries.value.length) return;
    useForm({ date: props.date, action, entries: dirtyEntries.value }).post(route('daily-ops.batch'), {
        onSuccess: () => (edits.value = {}),
        preserveScroll: true,
    });
}

function markRemainingUp() {
    props.rows.forEach((row) => {
        if (!rowState(row).status && row.entry_status !== 'LOCKED' && row.entry_status !== 'APPROVED') {
            toggle(row.site_id, 'UP');
        }
    });
}
</script>

<template>
  <Head title="Daily Ops" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full flex-wrap gap-3">
        <h2 class="font-semibold text-lg text-slate-800 leading-tight">Daily Ops</h2>
        <form class="flex items-end gap-3" @submit.prevent="applyFilter">
          <div>
            <label for="ops-date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</label>
            <input
              id="ops-date" type="date" :max="new Date().toISOString().slice(0,10)" :value="date"
              class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200"
              @change="router.get(route('daily-ops.index'), { date: $event.target.value }, { preserveState: true })"
            />
          </div>
          <div>
            <label for="ops-project" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Project</label>
            <select
              id="ops-project" v-model="filterForm.project_id" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200"
              @change="applyFilter"
            >
              <option value="">All Projects</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label for="ops-province" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Province</label>
            <select
              id="ops-province" v-model="filterForm.province" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200"
              @change="applyFilter"
            >
              <option value="">All Provinces</option>
              <option v-for="prov in provinces" :key="prov" :value="prov">{{ prov }}</option>
            </select>
          </div>
        </form>
      </div>
    </template>

    <!-- Action bar -->
    <div class="dict-card p-4 mb-4 sticky top-16 z-10 flex flex-wrap items-center gap-3">
      <span class="text-sm font-medium text-slate-600 tabular-nums">
        Reported <span class="text-emerald-600 font-bold">{{ reportedCount }}</span> / {{ counts.total }}
      </span>
      <button
        type="button" class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg px-3 py-1.5 transition"
        @click="markRemainingUp"
      >
        <IconCircleCheck class="w-4 h-4" /> Mark remaining UP
      </button>
      <div class="ml-auto flex gap-2">
        <button
          type="button" :disabled="!dirtyEntries.length" class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 text-slate-600 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
          @click="send('save_draft')"
        >
          Save Draft
        </button>
        <button
          type="button" :disabled="!dirtyEntries.length" class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
          @click="send('submit')"
        >
          Submit
        </button>
        <button
          v-if="$page.props.auth.permissions.includes('daily.approve')" type="button" :disabled="!dirtyEntries.length"
          class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
          @click="send('approve')"
        >
          <IconCalendarCheck class="w-4 h-4" /> Approve
        </button>
      </div>
    </div>

    <!-- Rows grouped by municipality -->
    <div v-for="(groupRows, municipality) in Object.groupBy(rows, (r) => r.municipality || 'Unknown')" :key="municipality" class="mb-6">
      <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2 px-1">
        {{ municipality }} <span class="text-slate-400">({{ groupRows.length }})</span>
      </h3>
      <div class="dict-card divide-y divide-slate-100 overflow-hidden">
        <div v-for="row in groupRows" :key="row.site_id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 hover:bg-slate-50/60 transition-colors">
          <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: row.marker_color || '#94a3b8' }"></span>
          <div class="min-w-[220px] flex-1">
            <div class="text-sm font-medium text-slate-800 truncate">{{ row.location_name }}</div>
            <div class="text-[11px] text-slate-400 font-mono">{{ row.ap_site_code }}</div>
          </div>

          <template v-if="row.entry_status === 'LOCKED'">
            <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500">
              <IconCircleMinus class="w-4 h-4" /> LOCKED
            </span>
          </template>
          <template v-else-if="row.entry_status === 'APPROVED'">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2.5 py-0.5">
              <IconCircleCheck class="w-3.5 h-3.5" /> Approved · {{ rowState(row).status }}
            </span>
          </template>
          <template v-else>
            <div class="flex rounded-lg overflow-hidden border border-slate-200" role="group" :aria-label="`Set status for ${row.location_name}`">
              <button
                type="button" class="px-3 py-1.5 text-xs font-semibold transition-colors inline-flex items-center gap-1"
                :class="rowState(row).status === 'UP' ? 'bg-green-600 text-white' : 'bg-white text-slate-500 hover:bg-green-50'"
                :aria-pressed="rowState(row).status === 'UP'"
                @click="toggle(row.site_id, 'UP')"
              >
                UP
              </button>
              <button
                type="button" class="px-3 py-1.5 text-xs font-semibold transition-colors border-x border-slate-200 inline-flex items-center gap-1"
                :class="rowState(row).status === 'DOWN' ? 'bg-red-600 text-white' : 'bg-white text-slate-500 hover:bg-red-50'"
                :aria-pressed="rowState(row).status === 'DOWN'"
                @click="toggle(row.site_id, 'DOWN')"
              >
                <IconCircleX v-if="rowState(row).status === 'DOWN'" class="w-3.5 h-3.5" /> DOWN
              </button>
              <button
                type="button" class="px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="rowState(row).status === 'NO_NMS' ? 'bg-amber-500 text-white' : 'bg-white text-slate-500 hover:bg-amber-50'"
                :aria-pressed="rowState(row).status === 'NO_NMS'"
                @click="toggle(row.site_id, 'NO_NMS')"
              >
                NO NMS
              </button>
            </div>
            <input
              type="number" step="0.01" min="0" placeholder="Mbps" :value="rowState(row).bandwidth_utilization_mbps"
              class="w-20 rounded-md border-slate-200 text-xs tabular-nums focus:border-blue-400 focus:ring-blue-100"
              @input="setField(row.site_id, 'bandwidth_utilization_mbps', $event.target.value === '' ? null : Number($event.target.value))"
            />
            <input
              type="number" min="0" placeholder="Users" :value="rowState(row).total_unique_users"
              class="w-20 rounded-md border-slate-200 text-xs tabular-nums focus:border-blue-400 focus:ring-blue-100"
              @input="setField(row.site_id, 'total_unique_users', $event.target.value === '' ? null : Number($event.target.value))"
            />
            <input
              type="text" placeholder="Remarks" :value="rowState(row).remarks"
              class="flex-1 min-w-[120px] max-w-[220px] rounded-md border-slate-200 text-xs focus:border-blue-400 focus:ring-blue-100"
              @input="setField(row.site_id, 'remarks', $event.target.value)"
            />
          </template>
        </div>
      </div>
    </div>

    <div v-if="!rows.length" class="dict-card p-12 text-center">
      <IconCalendarCheck class="w-12 h-12 text-slate-300 mx-auto mb-3" />
      <p class="text-sm text-slate-500">No sites match these filters.</p>
    </div>
  </AuthenticatedLayout>
</template>
