<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MetricSparkline from '@/Components/MetricSparkline.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { IconRouter } from '@tabler/icons-vue';
import { ref } from 'vue';

const props = defineProps({ device: Object, deviceModels: Array, sites: Array });

const page = usePage();
const canEdit = page.props.auth.permissions?.includes('devices.edit');

const editOpen = ref(false);
const roleLabels = {
    primary_ap: 'Primary AP', backup_ap: 'Backup AP', backhaul: 'Backhaul',
    power: 'Power', surveillance: 'Surveillance', other: 'Other',
};

const form = useForm({
    device_model_id: props.device.device_model?.id ?? '',
    asset_tag: props.device.asset_tag ?? '',
    serial_number: props.device.serial_number ?? '',
    mac_address: props.device.mac_address ?? '',
    firmware_version: props.device.firmware_version ?? '',
    status: props.device.status ?? 'in_stock',
    condition: props.device.condition ?? 'good',
    purchase_order_no: props.device.purchase_order_no ?? '',
    supplier: props.device.supplier ?? '',
    unit_cost: props.device.unit_cost ?? '',
    purchased_at: props.device.purchased_at ?? '',
    warranty_until: props.device.warranty_until ?? '',
    notes: props.device.notes ?? '',
    site_id: props.device.current_deployment?.site?.id ?? '',
    role_at_site: props.device.current_deployment?.role_at_site ?? 'primary_ap',
    installed_at: props.device.current_deployment?.installed_at?.slice(0, 10) ?? '',
});

function openEdit() {
    editOpen.value = !editOpen.value;
    form.clearErrors();
}

function save() {
    form.put(route('devices.update', props.device.id), { preserveScroll: true, onSuccess: () => (editOpen.value = false) });
}

// Reshape loaded metrics into sparkline point arrays.
const series = (key) => (props.device.metrics ?? [])
    .filter((m) => m[key] !== null && m[key] !== undefined)
    .map((m) => ({ ts: m.ts, value: m[key] }));

const statusPill = {
    deployed: 'bg-green-100 text-green-700',
    in_stock: 'bg-blue-100 text-blue-700',
    under_repair: 'bg-orange-100 text-orange-700',
    retired: 'bg-slate-100 text-slate-500',
    lost: 'bg-red-100 text-red-700',
};
</script>

<template>
  <Head :title="device.asset_tag" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight font-mono">{{ device.asset_tag }}</h2>
    </template>

    <div class="mb-4 flex items-center gap-4">
      <a
        :href="`/devices-labels?device=${device.id}`" target="_blank"
        class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 transition"
      >
        🖨 Print asset label
      </a>
      <button
        v-if="canEdit" type="button"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 transition"
        @click="openEdit"
      >
        {{ editOpen ? 'Close editor' : '✏️ Edit unit' }}
      </button>
    </div>

    <!-- Edit form -->
    <div v-if="editOpen" class="dict-card p-6 mb-6 border-l-4 border-blue-500">
      <h3 class="text-base font-semibold text-slate-800 mb-4">Edit unit</h3>
      <form class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4" @submit.prevent="save">
        <div class="sm:col-span-2">
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Model</label>
          <select v-model="form.device_model_id" class="w-full rounded-lg border-slate-300 text-sm" required>
            <option v-for="model in deviceModels" :key="model.id" :value="model.id">
              {{ model.manufacturer }} {{ model.model_name }} ({{ model.model_number }})
            </option>
          </select>
        </div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Asset tag</label>
          <input v-model="form.asset_tag" type="text" class="w-full rounded-lg border-slate-300 text-sm" required /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Serial no.</label>
          <input v-model="form.serial_number" type="text" class="w-full rounded-lg border-slate-300 text-sm" required /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">MAC (optional)</label>
          <input v-model="form.mac_address" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Firmware</label>
          <input v-model="form.firmware_version" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Status</label>
          <select v-model="form.status" class="w-full rounded-lg border-slate-300 text-sm">
            <option v-for="option in ['in_stock', 'deployed', 'under_repair', 'retired', 'lost']" :key="option" :value="option">
              {{ option.replace('_', ' ') }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Condition</label>
          <select v-model="form.condition" class="w-full rounded-lg border-slate-300 text-sm">
            <option v-for="option in ['new', 'good', 'degraded', 'faulty']" :key="option" :value="option">{{ option }}</option>
          </select>
        </div>
        <template v-if="form.status === 'deployed'">
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Assigned site</label>
            <select v-model="form.site_id" class="w-full rounded-lg border-slate-300 text-sm" :required="form.status === 'deployed'">
              <option value="" disabled>Select site…</option>
              <option v-for="site in sites" :key="site.id" :value="site.id">{{ site.location_name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Role</label>
            <select v-model="form.role_at_site" class="w-full rounded-lg border-slate-300 text-sm">
              <option v-for="(label, value) in roleLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Installed</label>
            <input v-model="form.installed_at" type="date" class="w-full rounded-lg border-slate-300 text-sm" />
          </div>
        </template>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">PO no.</label>
          <input v-model="form.purchase_order_no" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Supplier</label>
          <input v-model="form.supplier" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Unit cost</label>
          <input v-model="form.unit_cost" type="number" step="any" min="0" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Purchased</label>
          <input v-model="form.purchased_at" type="date" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Warranty until</label>
          <input v-model="form.warranty_until" type="date" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Notes</label>
          <input v-model="form.notes" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
        <div class="sm:col-span-2 lg:col-span-4 flex items-center gap-3">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Save changes' }}
          </button>
          <button type="button" class="text-sm text-slate-500 underline" @click="editOpen = false">Cancel</button>
          <span v-if="Object.values(form.errors).length" class="text-sm text-red-600">{{ Object.values(form.errors)[0] }}</span>
        </div>
      </form>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Unit details -->
      <div class="dict-card p-6">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-800">
              {{ device.device_model.manufacturer }} {{ device.device_model.model_name }}
            </h3>
            <p class="text-sm text-slate-500">{{ device.device_model.model_number }}</p>
          </div>
          <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="statusPill[device.status]">
            {{ device.status.replace('_', ' ') }}
          </span>
        </div>

        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-slate-500">Serial no.</dt><dd class="font-mono text-slate-700">{{ device.serial_number }}</dd></div>
          <div v-if="device.mac_address" class="flex justify-between"><dt class="text-slate-500">MAC</dt><dd class="font-mono text-slate-700">{{ device.mac_address }}</dd></div>
          <div v-if="device.firmware_version" class="flex justify-between"><dt class="text-slate-500">Firmware</dt><dd class="font-mono text-slate-700">{{ device.firmware_version }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-500">Condition</dt><dd class="capitalize text-slate-700">{{ device.condition }}</dd></div>
          <div v-if="device.supplier" class="flex justify-between"><dt class="text-slate-500">Supplier</dt><dd class="text-slate-700">{{ device.supplier }}</dd></div>
          <div v-if="device.warranty_until" class="flex justify-between"><dt class="text-slate-500">Warranty until</dt><dd class="text-slate-700">{{ device.warranty_until }}</dd></div>
        </dl>
      </div>

      <!-- Model spec sheet -->
      <div class="dict-card p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Spec Sheet</h3>
        <p v-if="!device.device_model.specs" class="text-sm text-slate-500">No specs recorded for this model.</p>
        <dl v-else class="space-y-2 text-sm">
          <div v-for="(value, key) in device.device_model.specs" :key="key" class="flex justify-between gap-4">
            <dt class="text-slate-500 capitalize">{{ key.replaceAll('_', ' ') }}</dt>
            <dd class="text-slate-700 text-right">{{ value }}</dd>
          </div>
        </dl>
      </div>

      <!-- Current assignment -->
      <div class="dict-card p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Current Assignment</h3>
        <template v-if="device.current_deployment">
          <Link :href="route('sites.show', device.current_deployment.site.id)" class="text-blue-600 hover:text-blue-800 font-medium">
            {{ device.current_deployment.site.location_name }}
          </Link>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Role</dt><dd class="capitalize text-slate-700">{{ (device.current_deployment.role_at_site ?? '').replaceAll('_', ' ') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Installed</dt><dd class="text-slate-700">{{ new Date(device.current_deployment.installed_at).toLocaleDateString() }}</dd></div>
          </dl>
        </template>
        <p v-else class="text-sm text-slate-500">Not currently assigned to a site.</p>
      </div>
    </div>

    <!-- Telemetry (48h) -->
    <div class="dict-card p-6 mt-6">
      <div class="flex items-baseline justify-between mb-4">
        <h3 class="text-lg font-semibold text-slate-800">Telemetry — last 48h</h3>
        <span class="text-xs text-slate-400">{{ device.metrics?.length ?? 0 }} samples</span>
      </div>
      <div v-if="device.metrics?.length" class="grid sm:grid-cols-2 xl:grid-cols-4 gap-6">
        <MetricSparkline label="WAN latency" unit="ms" color="#2563eb" :points="series('latency_ms')" />
        <MetricSparkline label="Clients" color="#7c3aed" :points="series('clients')" />
        <MetricSparkline label="Throughput RX" unit=" Mbps" color="#059669" :points="series('rx_mbps')" />
        <MetricSparkline label="Battery" unit=" V" color="#d97706" :points="series('battery_v')" />
      </div>
      <p v-else class="text-sm text-slate-500">
        No telemetry received yet. Field probes POST to <code class="font-mono text-xs">/api/heartbeat</code> with this device's serial to start the time-series.
      </p>
    </div>

    <!-- Deployment history -->
    <div class="dict-card overflow-hidden mt-6">
      <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Deployment History</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <caption class="sr-only">Deployment history — sites, roles and dates</caption>
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">Site</th>
              <th class="px-6 py-3">Role</th>
              <th class="px-6 py-3">Installed</th>
              <th class="px-6 py-3">Removed</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="deployment in device.deployments" :key="deployment.id" class="hover:bg-slate-50/50">
              <td class="px-6 py-4 text-sm text-slate-700">{{ deployment.site.location_name }}</td>
              <td class="px-6 py-4 text-sm capitalize text-slate-600">{{ deployment.role_at_site.replaceAll('_', ' ') }}</td>
              <td class="px-6 py-4 text-sm text-slate-600">{{ new Date(deployment.installed_at).toLocaleDateString() }}</td>
              <td class="px-6 py-4 text-sm text-slate-600">{{ deployment.removed_at ? new Date(deployment.removed_at).toLocaleDateString() : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!device.deployments?.length" class="px-6 py-10 text-center">
        <IconRouter class="w-10 h-10 text-slate-300 mx-auto mb-2" />
        <p class="text-sm text-slate-500">Never deployed.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
