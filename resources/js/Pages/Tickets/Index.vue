<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { IconCirclePlus, IconTicket, IconTool } from '@tabler/icons-vue';
import { ref } from 'vue';

defineProps({
    tickets: Object,
    filters: Object,
    counts: Object,
    users: Array,
    sites: Array,
    devices: Array,
});

const showCreate = ref(false);

const createForm = useForm({
    title: '',
    description: '',
    site_id: '',
    device_id: '',
    priority: 'medium',
    category: 'connectivity',
    assigned_to: '',
});

function create() {
    createForm.post(route('tickets.store'), {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
        },
    });
}

function transition(ticket, status) {
    useForm({ status, resolution_notes: '' }).put(route('tickets.update', ticket.id), { preserveScroll: true });
}
</script>

<template>
  <Head title="Maintenance Tickets" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">Maintenance Tickets</h2>
        <button
          class="inline-flex items-center gap-1.5 bg-accent-500 text-white px-3.5 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition"
          @click="showCreate = true"
        >
          <IconCirclePlus class="w-4 h-4" /> New Ticket
        </button>
      </div>
    </template>

    <!-- Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-6 mb-6">
      <div class="dict-card p-6 flex items-center gap-4">
        <IconTicket class="w-8 h-8 text-slate-400" />
        <div>
          <p class="text-sm text-slate-500">Open / In Progress</p>
          <p class="text-2xl font-bold text-slate-800 tabular-nums">{{ counts.open }}</p>
        </div>
      </div>
      <div class="dict-card p-6 flex items-center gap-4" :class="counts.critical_open > 0 ? 'ring-1 ring-red-200' : ''">
        <IconTool class="w-8 h-8" :class="counts.critical_open > 0 ? 'text-red-500' : 'text-slate-300'" />
        <div>
          <p class="text-sm text-slate-500">Critical Open</p>
          <p class="text-2xl font-bold tabular-nums" :class="counts.critical_open > 0 ? 'text-red-600' : 'text-slate-400'">{{ counts.critical_open }}</p>
        </div>
      </div>
    </div>

    <!-- Ticket table -->
    <div class="dict-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">Ticket</th>
              <th class="px-6 py-3">Site / Device</th>
              <th class="px-6 py-3">Priority</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3">Assignee</th>
              <th class="px-6 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4 max-w-xs">
                <div class="text-sm font-medium text-slate-800 truncate">{{ ticket.title }}</div>
                <div class="text-xs text-slate-400">{{ ticket.category }} · by {{ ticket.reporter?.name }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-slate-600">
                {{ ticket.site?.location_name || '—' }}
                <span v-if="ticket.device" class="block text-xs font-mono text-slate-400">{{ ticket.device.asset_tag }}</span>
              </td>
              <td class="px-6 py-4">
                <StatusPill :status="ticket.priority" />
              </td>
              <td class="px-6 py-4">
                <StatusPill :status="ticket.status" />
              </td>
              <td class="px-6 py-4 text-sm text-slate-600">{{ ticket.assignee?.name || 'Unassigned' }}</td>
              <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                <button
                  v-if="ticket.status === 'OPEN'" class="text-xs font-medium text-accent-500 hover:text-accent-600 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded transition-colors"
                  @click="transition(ticket, 'IN_PROGRESS')"
                >
                  Start
                </button>
                <button
                  v-if="['OPEN', 'IN_PROGRESS'].includes(ticket.status)" class="text-xs font-medium text-emerald-700 hover:text-emerald-800 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600/40 rounded transition-colors"
                  @click="transition(ticket, 'RESOLVED')"
                >
                  Resolve
                </button>
                <button
                  v-if="ticket.status === 'RESOLVED'" class="text-xs font-medium text-slate-500 hover:text-slate-700"
                  @click="transition(ticket, 'CLOSED')"
                >
                  Close
                </button>
                <button
                  v-if="ticket.status === 'CLOSED'" class="text-xs font-medium text-amber-600 hover:text-amber-800"
                  @click="transition(ticket, 'IN_PROGRESS')"
                >
                  Reopen
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!tickets.data?.length" class="px-6 py-12 text-center">
        <IconTicket class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No tickets — everything's running.</p>
      </div>
    </div>

    <Pagination :links="tickets.links" class="mt-4" />

    <!-- Create modal -->
    <Modal :show="showCreate" max-width="2xl" @close="showCreate = false">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">New maintenance ticket</h3>
        <form class="space-y-4" @submit.prevent="create">
          <div>
            <label for="t-title" class="block text-sm font-medium text-slate-700 mb-1">Title</label>
            <input id="t-title" v-model="createForm.title" type="text" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40" />
            <InputError :message="createForm.errors.title" class="mt-1" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="t-site" class="block text-sm font-medium text-slate-700 mb-1">Site</label>
              <select id="t-site" v-model="createForm.site_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40">
                <option value="">— none —</option>
                <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.location_name }}</option>
              </select>
            </div>
            <div>
              <label for="t-device" class="block text-sm font-medium text-slate-700 mb-1">Device</label>
              <select id="t-device" v-model="createForm.device_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40">
                <option value="">— none —</option>
                <option v-for="d in devices" :key="d.id" :value="d.id">{{ d.asset_tag }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label for="t-priority" class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
              <select id="t-priority" v-model="createForm.priority" class="w-full rounded-lg border-slate-300 text-sm capitalize">
                <option v-for="p in ['low', 'medium', 'high', 'critical']" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label for="t-category" class="block text-sm font-medium text-slate-700 mb-1">Category</label>
              <select id="t-category" v-model="createForm.category" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40">
                <option v-for="c in ['connectivity', 'hardware', 'power', 'firmware', 'other']" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label for="t-assignee" class="block text-sm font-medium text-slate-700 mb-1">Assign to</label>
              <select id="t-assignee" v-model="createForm.assigned_to" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40">
                <option value="">— unassigned —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label for="t-desc" class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea id="t-desc" v-model="createForm.description" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40"></textarea>
            <InputError :message="createForm.errors.description" class="mt-1" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded transition" @click="showCreate = false">Cancel</button>
            <button
              type="submit" :disabled="createForm.processing"
              class="bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] disabled:opacity-60 transition"
            >
              {{ createForm.processing ? 'Creating…' : 'Create ticket' }}
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
