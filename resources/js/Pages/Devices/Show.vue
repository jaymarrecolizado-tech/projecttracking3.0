<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconRouter } from '@tabler/icons-vue';

defineProps({ device: Object });

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

    <div class="mb-4">
      <a
        :href="`/devices-labels?device=${device.id}`" target="_blank"
        class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 transition"
      >
        🖨 Print asset label
      </a>
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

    <!-- Deployment history -->
    <div class="dict-card overflow-hidden mt-6">
      <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Deployment History</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
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
