<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { IconChevronRight, IconBuilding, IconSearch, IconArrowLeft } from '@tabler/icons-vue';
import { watch } from 'vue';

const props = defineProps({
    sites: Object,
    project: Object,
    filters: { type: Object, default: () => ({}) },
    projects: Array,
    provinces: Array,
});

const form = useForm({
    search: props.filters?.search ?? '',
    project_id: props.filters?.project_id ?? '',
    status: props.filters?.status ?? '',
    province: props.filters?.province ?? '',
    today: props.filters?.today ?? '',
});

let debounce = null;
watch(() => form.search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => apply(), 350);
});

watch(() => form.today, () => apply());

function apply() {
    router.get(route('sites.index'), form.data(), { preserveState: true });
}
</script>

<template>
  <Head title="Sites" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 w-full">
        <Link v-if="project" :href="route('sites.index')" class="text-slate-400 hover:text-slate-600">
          <IconArrowLeft class="w-5 h-5" />
        </Link>
        <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">
          {{ project ? `${project.name} - Sites` : 'All Sites' }}
          <span v-if="sites.total != null" class="ml-2 text-sm font-normal text-slate-400 tabular-nums">({{ sites.total }})</span>
        </h2>
      </div>
    </template>

    <!-- Filters -->
    <div class="dict-card p-4 mb-4">
      <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
          <label for="site-search" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Search</label>
          <div class="relative">
            <IconSearch class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              id="site-search" v-model="form.search" type="text" placeholder="Name, site code, municipality, barangay…"
              class="w-full rounded-lg border-slate-300 text-sm pl-9 focus:border-accent-500 focus:ring-accent-500/40"
            />
          </div>
        </div>
        <div v-if="!project">
          <label for="site-project" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Project</label>
          <select
            id="site-project" v-model="form.project_id" class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40 max-w-[200px]"
            @change="apply"
          >
            <option value="">All Projects</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label for="site-status" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
          <select
            id="site-status" v-model="form.status" class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40"
            @change="apply"
          >
            <option value="">All</option>
            <option v-for="label in ['active', 'inactive', 'planned', 'maintenance', 'decommissioned']" :key="label" :value="label">{{ label }}</option>
          </select>
        </div>
        <div>
          <label for="site-province" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Province</label>
          <select
            id="site-province" v-model="form.province" class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40 max-w-[180px]"
            @change="apply"
          >
            <option value="">All</option>
            <option v-for="prov in provinces" :key="prov" :value="prov">{{ prov }}</option>
          </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600 pb-1.5 cursor-pointer">
          <input
            v-model="form.today" type="checkbox" true-value="down" false-value=""
            class="rounded border-slate-300 text-accent-500 focus:ring-accent-500/40"
          />
          Down today
        </label>
      </div>
    </div>

    <DataTable caption="Sites listed with project, municipality, province and current status">
      <template #head>
        <th class="px-6 py-3">Location</th>
        <th class="px-6 py-3">Project</th>
        <th class="px-6 py-3">Municipality</th>
        <th class="px-6 py-3">Province</th>
        <th class="px-6 py-3">Status</th>
        <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
      </template>
      <tr
        v-for="site in sites.data" :key="site.id"
        class="hover:bg-slate-50/50 transition-colors cursor-pointer"
        @click="router.visit(route('sites.show', site.id))"
      >
        <td class="px-6 py-4 text-sm font-medium text-slate-700">
          {{ site.location_name }}
          <span v-if="site.ap_site_code" class="block text-[11px] text-slate-400 font-mono">{{ site.ap_site_code }}</span>
        </td>
        <td class="px-6 py-4 text-sm">
          <span class="text-xs font-mono font-semibold" :style="{ color: site.project?.marker_color }">{{ site.project?.code }}</span>
        </td>
        <td class="px-6 py-4 text-sm text-slate-700">{{ site.municipality }}</td>
        <td class="px-6 py-4 text-sm text-slate-700">{{ site.province }}</td>
        <td class="px-6 py-4 text-sm">
          <span class="inline-flex items-center gap-3">
            <StatusPill :status="site.status" />
            <StatusPill v-if="site.latest_daily_status" :status="site.latest_daily_status.status" />
          </span>
        </td>
        <td class="px-6 py-4 text-sm">
          <Link
            :href="route('sites.show', site.id)"
            class="text-accent-500 hover:text-accent-600 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded font-medium inline-flex items-center gap-1 transition-colors"
            @click.stop
          >
            View <IconChevronRight class="w-4 h-4" />
          </Link>
        </td>
      </tr>
      <template #footer>
        <div v-if="!sites.data?.length" class="px-6 py-12 text-center">
          <IconBuilding class="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <p class="text-sm text-slate-500">No sites match these filters.</p>
        </div>
      </template>
    </DataTable>

    <Pagination v-if="sites.links" :links="sites.links" class="mt-4" />
  </AuthenticatedLayout>
</template>
