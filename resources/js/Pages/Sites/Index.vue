<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { IconChevronRight, IconBuilding, IconSearch, IconArrowLeft } from '@tabler/icons-vue';
import { watch } from 'vue';

const props = defineProps({
    sites: Object,
    project: Object,
    filters: Object,
    projects: Array,
    provinces: Array,
});

const form = useForm({
    search: props.filters.search ?? '',
    project_id: props.filters.project_id ?? '',
    status: props.filters.status ?? '',
    province: props.filters.province ?? '',
    today: props.filters.today ?? '',
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

const statusStyles = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-red-100 text-red-700',
    planned: 'bg-yellow-100 text-yellow-700',
    decommissioned: 'bg-slate-100 text-slate-500',
    maintenance: 'bg-orange-100 text-orange-700',
};
</script>

<template>
  <Head title="Sites" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3 w-full">
        <Link v-if="project" :href="route('sites.index')" class="text-slate-400 hover:text-slate-600">
          <IconArrowLeft class="w-5 h-5" />
        </Link>
        <h2 class="font-semibold text-lg text-slate-800 leading-tight">
          {{ project ? `${project.name} - Sites` : 'All Sites' }}
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
            <input id="site-search" v-model="form.search" type="text" placeholder="Name, site code, municipality, barangay…"
              class="w-full rounded-lg border-slate-300 text-sm pl-9 focus:border-blue-500 focus:ring-blue-200" />
          </div>
        </div>
        <div v-if="!project">
          <label for="site-project" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Project</label>
          <select id="site-project" v-model="form.project_id" @change="apply"
            class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200 max-w-[200px]">
            <option value="">All Projects</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label for="site-status" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
          <select id="site-status" v-model="form.status" @change="apply"
            class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200">
            <option value="">All</option>
            <option v-for="label in ['active', 'inactive', 'planned', 'maintenance', 'decommissioned']" :key="label" :value="label">{{ label }}</option>
          </select>
        </div>
        <div>
          <label for="site-province" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Province</label>
          <select id="site-province" v-model="form.province" @change="apply"
            class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200 max-w-[180px]">
            <option value="">All</option>
            <option v-for="prov in provinces" :key="prov" :value="prov">{{ prov }}</option>
          </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600 pb-1.5 cursor-pointer">
          <input type="checkbox" v-model="form.today" true-value="down" false-value=""
            class="rounded border-slate-300 text-blue-600 focus:ring-blue-200" />
          Down today
        </label>
      </div>
    </div>

    <div class="dict-card overflow-hidden">
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
                <span class="inline-flex items-center gap-2">
                  <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="statusStyles[site.status] || 'bg-slate-100 text-slate-500'">{{ site.status }}</span>
                  <span v-if="site.latest_daily_status" class="text-[11px] font-medium" :class="{
                    'text-green-600': site.latest_daily_status.status === 'UP',
                    'text-red-600': ['DOWN', 'DOWN_SERVER'].includes(site.latest_daily_status.status),
                    'text-amber-600': site.latest_daily_status.status === 'NO_NMS',
                    'text-slate-400': site.latest_daily_status.status === 'NO_DATA',
                  }">{{ site.latest_daily_status.status.replace('_', ' ') }}</span>
                </span>
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
        <p class="text-sm text-slate-500">No sites match these filters.</p>
      </div>
    </div>

    <div v-if="sites.links" class="mt-4 flex gap-1 flex-wrap">
      <component
        :is="link.url ? 'button' : 'span'"
        v-for="(link, i) in sites.links"
        :key="i"
        @click="link.url && router.get(link.url, {}, { preserveState: true })"
        class="min-w-[2.25rem] text-center px-2 py-1.5 text-sm rounded-lg"
        :class="link.active
            ? 'bg-blue-600 text-white font-medium'
            : link.url
                ? 'dict-card text-slate-600 hover:bg-slate-100 cursor-pointer'
                : 'text-slate-300'"
        v-html="link.label"
      />
    </div>
  </AuthenticatedLayout>
</template>
