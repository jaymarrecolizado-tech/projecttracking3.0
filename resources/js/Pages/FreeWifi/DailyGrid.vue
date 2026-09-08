<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { IconActivity, IconLock, IconCheck } from '@tabler/icons-vue';

defineProps({ site: Object, statuses: Object });

const page = usePage();
const canApprove = page.props.auth.permissions?.includes('daily.approve');

// The API serialises date-cast attributes as ISO-8601; humans read Y-m-d.
const fmtDate = (value) => (value ? new Date(value).toISOString().slice(0, 10) : '—');

const transition = (status, action) =>
  router.post(route(`daily-statuses.${action}`, status.id), {}, { preserveScroll: true });
</script>

<template>
  <Head title="Daily Status" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">
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
        <th v-if="canApprove" class="px-6 py-3"><span class="sr-only">Workflow</span></th>
      </template>
      <tr v-for="status in statuses?.data || statuses" :key="status.id" class="hover:bg-slate-50/50 transition-colors">
        <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ fmtDate(status.date) }}</td>
        <td class="px-6 py-4 text-sm">
          <StatusPill :status="status.status" />
        </td>
        <td class="px-6 py-4 text-sm text-slate-700">{{ status.bandwidth_utilization_mbps ?? '—' }}</td>
        <td class="px-6 py-4 text-sm text-slate-700">{{ status.total_unique_users ?? '—' }}</td>
        <td class="px-6 py-4 text-sm text-slate-700">{{ status.uptime_percent ?? '—' }}</td>
        <td class="px-6 py-4 text-sm">
          <StatusPill :status="status.entry_status" />
        </td>
        <td v-if="canApprove" class="px-6 py-4 text-sm">
          <div v-if="status.entry_status !== 'LOCKED'" class="flex items-center gap-2 justify-end">
            <button
              v-if="status.entry_status !== 'APPROVED'"
              class="inline-flex items-center gap-1 text-accent-500 hover:text-accent-600 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded transition-colors"
              @click="transition(status, 'approve')"
            >
              <IconCheck class="w-4 h-4" /> Approve
            </button>
            <button
              class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-700 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded transition-colors"
              @click="transition(status, 'lock')"
            >
              <IconLock class="w-4 h-4" /> Lock
            </button>
          </div>
        </td>
      </tr>
      <template #footer>
        <div v-if="!(statuses?.data || statuses)?.length" class="px-6 py-12 text-center">
          <IconActivity class="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <p class="text-sm text-slate-500">No daily statuses found.</p>
        </div>
      </template>
    </DataTable>

    <Pagination v-if="statuses?.links" :links="statuses.links" class="mt-4" />
  </AuthenticatedLayout>
</template>
