<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { Head } from '@inertiajs/vue3';
import { IconActivity } from '@tabler/icons-vue';
defineProps({ site: Object, statuses: Object });
</script>

<template>
  <Head title="Daily Status" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">
        {{ site ? `${site.location_name} - Daily Status` : 'Daily Statuses' }}
      </h2>
    </template>

    <DataTable caption="Daily status records with date, status, bandwidth, users, uptime and entry workflow state">
      <template #header>
        <h3 class="text-lg font-semibold text-slate-800">
          {{ site ? site.location_name : 'Daily Statuses' }}
        </h3>
      </template>

      <template #head>
            <th class="px-6 py-3">Date</th>
            <th class="px-6 py-3">Status</th>
            <th class="px-6 py-3">Bandwidth (Mbps)</th>
            <th class="px-6 py-3">Users</th>
            <th class="px-6 py-3">Uptime %</th>
            <th class="px-6 py-3">Entry Status</th>
      </template>
            <tr v-for="status in statuses?.data || statuses" :key="status.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ status.date }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-semibold" :class="{
                    'bg-green-100 text-green-700': status.status === 'UP',
                    'bg-red-100 text-red-700': status.status === 'DOWN' || status.status === 'DOWN_SERVER',
                    'bg-amber-100 text-amber-700': status.status === 'NO_NMS',
                    'bg-slate-100 text-slate-500': status.status === 'NO_DATA',
                  }"
                >{{ status.status.replace('_', ' ') }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ status.bandwidth_utilization_mbps }}</td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ status.total_unique_users }}</td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ status.uptime_percent }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                    'bg-yellow-100 text-yellow-700': status.entry_status === 'DRAFT',
                    'bg-blue-100 text-blue-700': status.entry_status === 'SUBMITTED',
                    'bg-green-100 text-green-700': status.entry_status === 'APPROVED',
                    'bg-slate-100 text-slate-500': status.entry_status === 'LOCKED',
                  }"
                >{{ status.entry_status }}</span>
              </td>
            </tr>
      <template #footer>
        <div v-if="!(statuses?.data || statuses)?.length" class="px-6 py-12 text-center">
          <IconActivity class="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <p class="text-sm text-slate-500">No daily statuses found.</p>
        </div>
      </template>
    </DataTable>
  </AuthenticatedLayout>
</template>
