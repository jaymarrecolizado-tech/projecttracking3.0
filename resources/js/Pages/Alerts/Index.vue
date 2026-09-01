<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconAlertTriangle, IconCircleCheck, IconShieldCheck } from '@tabler/icons-vue';
import { ref } from 'vue';

const props = defineProps({
    alerts: Object,
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
    rules: { type: Array, default: () => [] },
    canManageRules: { type: Boolean, default: false },
});

const state = ref(props.filters.state ?? 'active');
const severity = ref(props.filters.severity ?? '');

function applyFilters() {
    router.get(route('alerts.index'), { state: state.value, severity: severity.value }, { preserveState: true });
}

function acknowledge(alert) {
    router.post(route('alerts.acknowledge', alert.id), {}, { preserveScroll: true });
}

function resolve(alert) {
    router.post(route('alerts.resolve', alert.id), {}, { preserveScroll: true });
}

const severityStyles = {
    critical: 'bg-red-100 text-red-700',
    warning: 'bg-amber-100 text-amber-700',
    info: 'bg-blue-100 text-blue-700',
};

const metricLabels = {
    offline_minutes: 'Offline (min since last beat)',
    latency_ms: 'WAN latency (ms)',
    cpu_pct: 'CPU %',
    mem_pct: 'Memory %',
    clients: 'Clients',
    rx_mbps: 'Throughput RX (Mbps)',
    tx_mbps: 'Throughput TX (Mbps)',
    battery_v: 'Battery (V)',
    bandwidth_pct: 'Bandwidth (% of CIR)',
};

const blankRule = {
    id: null, name: '', metric: 'latency_ms', operator: '>', threshold: 0,
    duration_minutes: 0, severity: 'warning', notify_roles: [], is_active: true,
};
const ruleForm = useForm({ ...blankRule });
const editingId = ref(null);

function editRule(rule) {
    editingId.value = rule.id;
    Object.assign(ruleForm, { ...rule, notify_roles: rule.notify_roles ?? [] });
    ruleForm.clearErrors();
}

function saveRule() {
    const options = { preserveScroll: true, onSuccess: () => cancelEdit() };
    ruleForm.transform((data) => ({ ...data, is_active: !!data.is_active }));
    if (editingId.value) {
        ruleForm.put(route('alert-rules.update', editingId.value), options);
    } else {
        ruleForm.post(route('alert-rules.store'), options);
    }
}

function deleteRule(rule) {
    if (confirm(`Delete rule "${rule.name}"? Its alerts stay for the record.`)) {
        router.delete(route('alert-rules.destroy', rule.id), { preserveScroll: true });
    }
}

function cancelEdit() {
    editingId.value = null;
    Object.assign(ruleForm, { ...blankRule });
    ruleForm.clearErrors();
}
</script>

<template>
  <Head title="Alerts" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Alerts</h2>
    </template>

    <!-- Counters -->
    <div class="grid grid-cols-3 gap-4 mb-4">
      <div class="dict-card px-4 py-3">
        <div class="text-2xl font-bold text-slate-800">{{ counts.active }}</div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Active alerts</div>
      </div>
      <div class="dict-card px-4 py-3">
        <div class="text-2xl font-bold text-red-600">{{ counts.critical }}</div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Critical</div>
      </div>
      <div class="dict-card px-4 py-3">
        <div class="text-2xl font-bold text-amber-600">{{ counts.unacknowledged }}</div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Unacknowledged</div>
      </div>
    </div>

    <!-- Active alerts -->
    <DataTable caption="Alerts fired by the rules engine">
      <template #header>
        <h3 class="text-lg font-semibold text-slate-800">Alerts</h3>
        <div class="flex gap-2">
          <select v-model="state" class="rounded-lg border-slate-300 text-sm" @change="applyFilters">
            <option value="active">Active</option>
            <option value="resolved">Resolved</option>
          </select>
          <select v-model="severity" class="rounded-lg border-slate-300 text-sm" @change="applyFilters">
            <option value="">All severities</option>
            <option value="critical">Critical</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
          </select>
        </div>
      </template>

      <template #head>
        <th class="px-6 py-3">Severity</th>
        <th class="px-6 py-3">Rule / Site</th>
        <th class="px-6 py-3">Observed</th>
        <th class="px-6 py-3">Triggered</th>
        <th class="px-6 py-3">Status</th>
        <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
      </template>
      <tr v-for="alert in alerts.data" :key="alert.id" class="hover:bg-slate-50/50">
        <td class="px-6 py-4 text-sm">
          <span class="px-2.5 py-1 rounded-full text-xs font-semibold uppercase" :class="severityStyles[alert.rule.severity]">
            {{ alert.rule.severity }}
          </span>
        </td>
        <td class="px-6 py-4 text-sm">
          <div class="font-medium text-slate-700">{{ alert.rule.name }}</div>
          <div class="text-slate-500">{{ alert.site?.location_name ?? '—' }}
            <span v-if="alert.device" class="font-mono text-xs">· {{ alert.device.asset_tag }}</span>
          </div>
        </td>
        <td class="px-6 py-4 text-sm text-slate-700 tabular-nums">
          {{ alert.context?.observed ?? '—' }}
          <span class="text-slate-400">{{ alert.rule.operator }} {{ alert.rule.threshold }}</span>
        </td>
        <td class="px-6 py-4 text-sm text-slate-600">{{ new Date(alert.triggered_at).toLocaleString() }}</td>
        <td class="px-6 py-4 text-sm">
          <template v-if="alert.resolved_at">
            <span class="inline-flex items-center gap-1 text-emerald-600"><IconCircleCheck class="w-4 h-4" /> Resolved</span>
          </template>
          <template v-else-if="alert.acknowledged_at">
            <span class="inline-flex items-center gap-1 text-blue-600"><IconShieldCheck class="w-4 h-4" /> Ack · {{ alert.acknowledger?.name }}</span>
          </template>
          <span v-else class="text-amber-600">Needs attention</span>
        </td>
        <td class="px-6 py-4 text-sm">
          <div class="flex gap-2">
            <button
              v-if="!alert.acknowledged_at && !alert.resolved_at" type="button"
              class="text-blue-600 hover:text-blue-800 font-medium" @click="acknowledge(alert)"
            >
              Acknowledge
            </button>
            <button
              v-if="!alert.resolved_at" type="button"
              class="text-emerald-600 hover:text-emerald-800 font-medium" @click="resolve(alert)"
            >
              Resolve
            </button>
          </div>
        </td>
      </tr>
      <template #footer>
        <div v-if="!alerts.data?.length" class="px-6 py-10 text-center">
          <IconCircleCheck class="w-10 h-10 text-emerald-300 mx-auto mb-2" />
          <p class="text-sm text-slate-500">{{ state === 'active' ? 'No active alerts — all quiet.' : 'No resolved alerts yet.' }}</p>
        </div>
      </template>
    </DataTable>

    <!-- Rules management (admins) -->
    <div v-if="canManageRules" class="dict-card p-6 mt-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-slate-800">Alert Rules</h3>
        <button
          v-if="!editingId" type="button"
          class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition"
          @click="editingId = 0; Object.assign(ruleForm, { ...blankRule })"
        >
          <IconAlertTriangle class="w-4 h-4" /> New rule
        </button>
      </div>

      <!-- editor -->
      <form v-if="editingId !== null" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" @submit.prevent="saveRule">
        <div class="sm:col-span-2 lg:col-span-1">
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Name</label>
          <input v-model="ruleForm.name" type="text" class="w-full rounded-lg border-slate-300 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Metric</label>
          <select v-model="ruleForm.metric" class="w-full rounded-lg border-slate-300 text-sm">
            <option v-for="(label, metric) in metricLabels" :key="metric" :value="metric">{{ label }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Operator</label>
            <select v-model="ruleForm.operator" class="w-full rounded-lg border-slate-300 text-sm">
              <option v-for="op in ['<', '<=', '>', '>=', '==']" :key="op" :value="op">{{ op }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Threshold</label>
            <input v-model="ruleForm.threshold" type="number" step="any" class="w-full rounded-lg border-slate-300 text-sm" required />
          </div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Held for (minutes, 0 = instant)</label>
          <input v-model="ruleForm.duration_minutes" type="number" min="0" max="1440" class="w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Severity</label>
          <select v-model="ruleForm.severity" class="w-full rounded-lg border-slate-300 text-sm">
            <option value="critical">Critical</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Notify roles (permissions)</label>
          <select v-model="ruleForm.notify_roles" class="w-full rounded-lg border-slate-300 text-sm" multiple>
            <option value="daily.approve">daily.approve (approvers)</option>
            <option value="users.manage">users.manage (admins)</option>
          </select>
        </div>
        <div class="flex items-end gap-3">
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="ruleForm.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600" />
            Active
          </label>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">Save</button>
          <button type="button" class="text-sm text-slate-500 underline" @click="cancelEdit">Cancel</button>
        </div>
        <InputError class="sm:col-span-2 lg:col-span-3" :message="typeof ruleForm.errors === 'object' ? Object.values(ruleForm.errors)[0] : ''" />
      </form>

      <div class="overflow-x-auto">
        <table class="w-full">
          <caption class="sr-only">Configured alert rules</caption>
          <thead>
            <tr class="dict-table-header">
              <th class="px-4 py-2 text-left">Rule</th>
              <th class="px-4 py-2 text-left">Condition</th>
              <th class="px-4 py-2 text-left">Severity</th>
              <th class="px-4 py-2 text-left">State</th>
              <th class="px-4 py-2 text-left"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="rule in rules" :key="rule.id">
              <td class="px-4 py-2 text-sm text-slate-700">{{ rule.name }}</td>
              <td class="px-4 py-2 text-sm text-slate-600 tabular-nums">
                {{ metricLabels[rule.metric] ?? rule.metric }} {{ rule.operator }} {{ rule.threshold }}
                <span v-if="rule.duration_minutes > 0" class="text-slate-400">held {{ rule.duration_minutes }}m</span>
              </td>
              <td class="px-4 py-2 text-sm">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold uppercase" :class="severityStyles[rule.severity]">{{ rule.severity }}</span>
              </td>
              <td class="px-4 py-2 text-sm" :class="rule.is_active ? 'text-emerald-600' : 'text-slate-400'">
                {{ rule.is_active ? 'Active' : 'Paused' }}
              </td>
              <td class="px-4 py-2 text-sm">
                <div class="flex gap-2">
                  <button type="button" class="text-blue-600 hover:text-blue-800 font-medium" @click="editRule(rule)">Edit</button>
                  <button type="button" class="text-red-600 hover:text-red-800 font-medium" @click="deleteRule(rule)">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
