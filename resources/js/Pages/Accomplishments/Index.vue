<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { IconChecklist } from '@tabler/icons-vue';
defineProps({ accomplishments: Object });
</script>

<template>
  <Head title="Accomplishments" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Accomplishments</h2>
    </template>

    <div class="dict-card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">All Accomplishments</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">Site</th>
              <th class="px-6 py-3">Milestone</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3">% Complete</th>
              <th class="px-6 py-3">Target Date</th>
              <th class="px-6 py-3">Actual Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="a in accomplishments.data" :key="a.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ a.site?.location_name }}</td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ a.milestone?.milestone_name }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                    'bg-green-100 text-green-700': a.status === 'COMPLETED',
                    'bg-blue-100 text-blue-700': a.status === 'IN_PROGRESS',
                    'bg-slate-100 text-slate-500': a.status === 'NOT_STARTED',
                    'bg-yellow-100 text-yellow-700': a.status === 'ON_HOLD',
                    'bg-red-100 text-red-700': a.status === 'CANCELLED',
                  }"
                >{{ a.status }}</span>
              </td>
              <td class="px-6 py-4 text-sm">
                <div class="flex items-center gap-3">
                  <div class="w-32 bg-slate-100 rounded-full h-2.5">
                    <div
                      class="h-2.5 rounded-full transition-all duration-300" :class="{
                        'bg-green-500': a.status === 'COMPLETED',
                        'bg-blue-500': a.status === 'IN_PROGRESS',
                        'bg-slate-300': a.status === 'NOT_STARTED',
                        'bg-yellow-500': a.status === 'ON_HOLD',
                        'bg-red-500': a.status === 'CANCELLED',
                      }" :style="{ width: a.pct_complete + '%' }"
                    ></div>
                  </div>
                  <span class="text-sm font-medium text-slate-600 w-12 text-right">{{ a.pct_complete }}%</span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-slate-500">{{ a.target_date }}</td>
              <td class="px-6 py-4 text-sm text-slate-500">{{ a.actual_date }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!accomplishments.data?.length" class="px-6 py-12 text-center">
        <IconChecklist class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No accomplishments found.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
