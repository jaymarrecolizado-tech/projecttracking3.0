<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { IconChevronRight, IconRouter, IconSearch } from '@tabler/icons-vue';

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
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Devices</h2>
    </template>

    <!-- Inventory counters -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <button
        v-for="(value, key) in counts" :key="key" type="button"
        class="dict-card px-4 py-3 text-left"
        :class="{ 'ring-2 ring-blue-500': filters.status === key }"
        @click="key !== 'total' && filterStatus(key)"
      >
        <div class="text-2xl font-bold text-slate-800">{{ value }}</div>
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ key.replace('_', ' ') }}</div>
      </button>
    </div>

    <!-- Inventory views -->
    <div class="grid md:grid-cols-3 gap-4 mb-6">
      <div class="dict-card p-4 md:col-span-2">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Stock by type</h3>
        <div v-if="stockByType?.length" class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1.5 text-sm">
          <template v-for="row in stockByType" :key="row.type">
            <div class="text-slate-600">{{ typeLabels[row.type] ?? row.type }}</div>
            <div class="text-right text-slate-800">{{ row.deployed }} deployed</div>
            <div class="text-right text-blue-600">{{ row.in_stock }} in stock</div>
          </template>
        </div>
        <p v-else class="text-sm text-slate-400">No devices registered yet.</p>
      </div>
      <div class="dict-card p-4">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Warranty</h3>
        <button
          type="button" class="w-full flex justify-between items-center rounded-lg px-3 py-2 text-sm hover:bg-orange-50 transition"
          :class="{ 'ring-1 ring-orange-400 bg-orange-50': filters.warranty === 'expiring' }"
          @click="filterWarranty('expiring')"
        >
          <span class="text-slate-600">Expiring ≤ 90 days</span>
          <span class="font-bold text-orange-600">{{ warranty?.expiring ?? 0 }}</span>
        </button>
        <button
          type="button" class="w-full flex justify-between items-center rounded-lg px-3 py-2 text-sm hover:bg-red-50 transition"
          :class="{ 'ring-1 ring-red-400 bg-red-50': filters.warranty === 'expired' }"
          @click="filterWarranty('expired')"
        >
          <span class="text-slate-600">Expired</span>
          <span class="font-bold text-red-600">{{ warranty?.expired ?? 0 }}</span>
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
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border-slate-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
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
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                    'bg-green-100 text-green-700': device.status === 'deployed',
                    'bg-blue-100 text-blue-700': device.status === 'in_stock',
                    'bg-orange-100 text-orange-700': device.status === 'under_repair',
                    'bg-slate-100 text-slate-500': device.status === 'retired',
                    'bg-red-100 text-red-700': device.status === 'lost',
                  }"
                >{{ device.status.replace('_', ' ') }}</span>
              </td>
              <td class="px-6 py-4 text-sm">
                <Link :href="route('devices.show', device.id)" class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1">
                  View <IconChevronRight class="w-4 h-4" />
                </Link>
              </td>
            </tr>
      <template #footer>
        <div v-if="!devices.data?.length" class="px-6 py-12 text-center">
          <IconRouter class="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <p class="text-sm text-slate-500">No devices found.</p>
        </div>

        <div v-if="devices.last_page > 1" class="px-6 py-4 border-t border-slate-200 flex gap-1">
          <Link
            v-for="link in devices.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1.5 text-sm rounded-md border border-slate-200"
            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-slate-50'"
            v-html="link.label"
          />
        </div>
      </template>
    </DataTable>
  </AuthenticatedLayout>
</template>
