<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconChevronRight, IconBuilding } from '@tabler/icons-vue';
defineProps({ sites: Object, project: Object });
</script>

<template>
  <Head title="Sites" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">
        {{ project ? `${project.name} - Sites` : 'All Sites' }}
      </h2>
    </template>

    <div class="dict-card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">
          {{ project ? project.name : 'All Sites' }}
        </h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">Location</th>
              <th class="px-6 py-3">Project</th>
              <th class="px-6 py-3">Municipality</th>
              <th class="px-6 py-3">Province</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="site in sites.data" :key="site.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ site.location_name }}</td>
              <td class="px-6 py-4 text-sm">
                <span class="text-xs font-mono font-semibold" :style="{ color: site.project?.marker_color }">{{ site.project?.code }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ site.municipality }}</td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ site.province }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                    'bg-green-100 text-green-700': site.status === 'active',
                    'bg-red-100 text-red-700': site.status === 'inactive',
                    'bg-yellow-100 text-yellow-700': site.status === 'planned',
                    'bg-slate-100 text-slate-500': site.status === 'decommissioned',
                    'bg-orange-100 text-orange-700': site.status === 'maintenance',
                  }"
                >{{ site.status }}</span>
              </td>
              <td class="px-6 py-4 text-sm">
                <Link :href="route('sites.show', site.id)" class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1">
                  View <IconChevronRight class="w-4 h-4" />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!sites.data?.length" class="px-6 py-12 text-center">
        <IconBuilding class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No sites found.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
