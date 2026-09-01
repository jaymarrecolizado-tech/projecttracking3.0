<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconArrowLeft } from '@tabler/icons-vue';
defineProps({ site: Object });
</script>

<template>
  <Head :title="site.location_name" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">{{ site.location_name }}</h2>
    </template>

    <div>
      <!-- Back Link -->
      <Link :href="route('sites.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 mb-4 transition">
        <IconArrowLeft class="w-4 h-4" /> Back to Sites
      </Link>

      <!-- Header Card -->
      <div class="dict-card p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-xl font-bold text-slate-800">{{ site.location_name }}</h1>
            <span class="text-sm text-slate-500">{{ site.project?.name }}</span>
          </div>
          <div class="flex items-center gap-3">
            <span
              class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                'bg-green-100 text-green-700': site.status === 'active',
                'bg-red-100 text-red-700': site.status === 'inactive',
                'bg-yellow-100 text-yellow-700': site.status === 'planned',
                'bg-slate-100 text-slate-500': site.status === 'decommissioned',
                'bg-orange-100 text-orange-700': site.status === 'maintenance',
              }"
            >{{ site.status }}</span>
            <Link :href="route('sites.daily-grid', site.id)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">Daily Status</Link>
            <Link :href="route('sites.accomplishments', site.id)" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-700 transition">Accomplishments</Link>
          </div>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="dict-card p-6">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Location Details</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-slate-500">Barangay</dt>
              <dd class="text-sm text-slate-700">{{ site.barangay }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Municipality</dt>
              <dd class="text-sm text-slate-700">{{ site.municipality }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Province</dt>
              <dd class="text-sm text-slate-700">{{ site.province }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Region</dt>
              <dd class="text-sm text-slate-700">{{ site.region }}</dd>
            </div>
          </dl>
        </div>

        <div class="dict-card p-6">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Technical Details</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-slate-500">Latitude</dt>
              <dd class="text-sm font-mono text-slate-700">{{ site.latitude }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Longitude</dt>
              <dd class="text-sm font-mono text-slate-700">{{ site.longitude }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">ISP</dt>
              <dd class="text-sm text-slate-700">{{ site.isp_provider }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Bandwidth</dt>
              <dd class="text-sm text-slate-700">{{ site.bw_download_cir }} Mbps</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Installed Equipment -->
      <div class="dict-card overflow-hidden mt-6">
        <div class="px-6 py-4 border-b border-slate-200">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Installed Equipment</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="dict-table-header">
                <th class="px-6 py-3">Asset Tag</th>
                <th class="px-6 py-3">Model</th>
                <th class="px-6 py-3">Role</th>
                <th class="px-6 py-3">Installed</th>
                <th class="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="deployment in site.active_deployments" :key="deployment.id" class="hover:bg-slate-50/50">
                <td class="px-6 py-4 text-sm font-mono font-semibold text-slate-700">{{ deployment.device?.asset_tag }}</td>
                <td class="px-6 py-4 text-sm text-slate-700">
                  {{ deployment.device?.device_model?.manufacturer }} {{ deployment.device?.device_model?.model_name }}
                </td>
                <td class="px-6 py-4 text-sm capitalize text-slate-600">{{ deployment.role_at_site?.replaceAll('_', ' ') }}</td>
                <td class="px-6 py-4 text-sm text-slate-600">{{ deployment.installed_at ? new Date(deployment.installed_at).toLocaleDateString() : '—' }}</td>
                <td class="px-6 py-4 text-sm">
                  <Link v-if="deployment.device" :href="route('devices.show', deployment.device.id)" class="text-blue-600 hover:text-blue-800 font-medium">View</Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!site.active_deployments?.length" class="px-6 py-10 text-center">
          <p class="text-sm text-slate-500">No equipment registered at this site yet.</p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
