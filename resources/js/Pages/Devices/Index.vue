<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { IconChevronRight, IconRouter, IconSearch } from '@tabler/icons-vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({ devices: Object, filters: Object, deviceModels: Array, counts: Object, stockByType: Array, warranty: Object });

const search = ref(props.filters.search ?? '');

function applyFilters(extra = {}) {
    router.get(route('devices.index'), {
        search: search.value || null,
        status: props.filters.status,
        ...extra,
    }, { preserveState: true });
}

function filterStatus(status) {
    const next = props.filters.status === status ? null : status;
    applyFilters({ status: next });
}

function filterWarranty(which) {
    const next = props.filters.warranty === which ? null : which;
    applyFilters({ warranty: next });
}

const typeLabels = {
    outdoor_ap: 'Outdoor AP', router: 'Router', switch: 'Switch', cpe: 'CPE',
    solar_panel: 'Solar Panel', charge_controller: 'Charge Controller', battery: 'Battery',
    ups: 'UPS', poe_injector: 'PoE Injector', antenna: 'Antenna', camera: 'Camera', other: 'Other',
};
</script>

<template>
  <Head title="Devices" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">Devices</h2>
    </template>

    <!-- Inventory counters -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-6 mb-6">
      <button
        v-for="(value, key) in counts" :key="key" type="button"
        class="dict-card px-4 py-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 active:scale-[0.99] transition disabled:cursor-default"
        :class="{ 'ring-2 ring-accent-500': filters.status === key }"
        :disabled="key === 'total'"
        :aria-pressed="key !== 'total' ? filters.status === key : undefined"
        @click="key !== 'total' && filterStatus(key)"
      >
        <div class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ value }}</div>
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ key.replace('_', ' ') }}</div>
      </button>
    </div>

    <!-- Inventory views -->
    <div class="grid md:grid-cols-3 gap-6 mb-6">
      <div class="dict-card p-6 md:col-span-2">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Stock by type</h3>
        <div v-if="stockByType?.length" class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1.5 text-sm">
          <template v-for="row in stockByType" :key="row.type">
            <div class="text-slate-600">{{ typeLabels[row.type] ?? row.type }}</div>
            <div class="text-right text-slate-800">{{ row.deployed }} deployed</div>
            <div class="text-right font-medium text-slate-800">{{ row.in_stock }} in stock</div>
          </template>
        </div>
        <p v-else class="text-sm text-slate-400">No devices registered yet.</p>
      </div>
      <div class="dict-card p-6">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Warranty</h3>
        <button
          type="button" class="w-full flex justify-between items-center rounded-lg px-3 py-2 text-sm hover:bg-orange-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-400/60 active:scale-[0.99] transition"
          :class="{ 'ring-1 ring-orange-400 bg-orange-50': filters.warranty === 'expiring' }"
          :aria-pressed="filters.warranty === 'expiring'"
          @click="filterWarranty('expiring')"
        >
          <span class="text-slate-600">Expiring ≤ 90 days</span>
          <span class="font-bold text-orange-700 tabular-nums">{{ warranty?.expiring ?? 0 }}</span>
        </button>
        <button
          type="button" class="w-full flex justify-between items-center rounded-lg px-3 py-2 text-sm hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400/60 active:scale-[0.99] transition"
          :class="{ 'ring-1 ring-red-400 bg-red-50': filters.warranty === 'expired' }"
          :aria-pressed="filters.warranty === 'expired'"
          @click="filterWarranty('expired')"
        >
          <span class="text-slate-600">Expired</span>
          <span class="font-bold text-red-600 tabular-nums">{{ warranty?.expired ?? 0 }}</span>
        </button>
      </div>
    </div>

    <DataTable caption="Device registry with asset tag, model, serial, assigned site and status">
      <template #header>
        <h3 class="text-lg font-semibold text-slate-800">Device Registry</h3>
        <form class="relative w-full max-w-xs" @submit.prevent="applyFilters()">
          <IconSearch class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            v-model="search" type="text" placeholder="Search tag, serial, MAC… (Enter)"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border-slate-300 focus:border-accent-500 focus:ring focus:ring-accent-500/40"
          />
        </form>
      </template>

      <template #head>
        <th class="px-6 py-3">Asset Tag</th>
        <th class="px-6 py-3">Model</th>
        <th class="px-6 py-3">Type</th>
        <th class="px-6 py-3">Serial No.</th>
        <th class="px-6 py-3">Assigned Site</th>
        <th class="px-6 py-3">Status</th>
        <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
      </template>
      <tr v-for="device in devices.data" :key="device.id" class="hover:bg-slate-50/50 transition-colors">
        <td class="px-6 py-4 text-sm font-mono font-semibold text-slate-700">{{ device.asset_tag }}</td>
        <td class="px-6 py-4 text-sm text-slate-700">
          {{ device.device_model?.manufacturer }} {{ device.device_model?.model_name }}
        </td>
        <td class="px-6 py-4 text-sm text-slate-600">{{ typeLabels[device.device_model?.type] }}</td>
        <td class="px-6 py-4 text-sm font-mono text-slate-600">{{ device.serial_number }}</td>
        <td class="px-6 py-4 text-sm text-slate-700">{{ device.current_deployment?.site?.location_name ?? '—' }}</td>
        <td class="px-6 py-4 text-sm">
          <StatusPill :status="device.status" />
        </td>
        <td class="px-6 py-4 text-sm">
          <Link :href="route('devices.show', device.id)" class="text-accent-500 hover:text-accent-600 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded font-medium inline-flex items-center gap-1 transition-colors">
            View <IconChevronRight class="w-4 h-4" />
          </Link>
        </td>
      </tr>
      <template #footer>
        <div v-if="!devices.data?.length" class="px-6 py-12 text-center">
          <IconRouter class="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <p class="text-sm text-slate-500">No devices found.</p>
          <p class="mt-1 text-xs text-slate-400">Register new units from any site's equipment section.</p>
        </div>

        <div v-if="devices.last_page > 1" class="px-6 py-4 border-t border-slate-200">
          <Pagination :links="devices.links" />
        </div>
      </template>
    </DataTable>
  </AuthenticatedLayout>
</template>
