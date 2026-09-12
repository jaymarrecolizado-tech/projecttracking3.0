<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GeoFilterFields from '@/Components/GeoFilterFields.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconCircleCheck, IconCircleX, IconDownload, IconFileDescription, IconLoader2, IconMapPin, IconRefresh, IconTable, IconTarget } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    projects: Array,
    exports: { type: Object, default: () => ({ data: [], links: [] }) },
    siteTypes: { type: Array, default: () => [] },
    initialOptions: { type: Object, default: () => ({ provinces: [], districts: [], municipalities: [], barangays: [] }) },
});

const provinceForm = useForm({
    province: '',
    project_id: '',
});

const projectForm = useForm({ project_id: '' });

function submitProject() {
    if (!projectForm.project_id) {
        return;
    }
    router.post(route('reports.project', projectForm.project_id), {}, {
        preserveScroll: true,
        onSuccess: () => projectForm.reset(),
    });
}

const provinceOptions = computed(() => props.initialOptions?.provinces ?? []);

const typeLabels = {
    project: 'Project summary',
    province: 'Province report',
    site_type: 'Site type coverage',
    barangay_coverage: 'Barangay coverage',
    ops_period: 'Operations period',
    fleet: 'Fleet inventory',
    incidents: 'Incidents',
    progress: 'Progress',
    combined: 'Operations pack',
};

const coverageForm = useForm({
    project_id: '',
    province: '',
    district: '',
    municipality: '',
    barangay: '',
    site_type: '',
    status: '',
});

const coverageOptions = ref(props.initialOptions);

/** Shared cascading-area loader: narrowing a parent re-fetches its children. */
function loadOptions(next, target) {
    const params = new URLSearchParams();
    if (next.project_id) params.set('project_id', next.project_id);
    if (next.province) params.set('province', next.province);
    if (next.district) params.set('district', next.district);
    if (next.municipality) params.set('municipality', next.municipality);
    fetch(`/map/filter-options?${params}`)
        .then((r) => r.json())
        .then((json) => (target.value = json));
}

function onCoverageFilters(next) {
    Object.assign(coverageForm, next);
    loadOptions(next, coverageOptions);
}

function submitCoverage() {
    coverageForm.post(route('reports.site-type'), {
        preserveScroll: true,
        // Clear the form AND the cascading options, or the selects keep a
        // narrowed list that no longer matches the (now empty) filters.
        onSuccess: () => {
            coverageForm.reset();
            coverageOptions.value = props.initialOptions;
        },
    });
}

const barangayForm = useForm({
    project_id: '',
    province: '',
    district: '',
    municipality: '',
});

const barangayOptions = ref(props.initialOptions);

function onBarangayFilters(next) {
    Object.assign(barangayForm, next);
    loadOptions(next, barangayOptions);
}

function submitBarangayCoverage() {
    barangayForm.post(route('reports.barangay-coverage'), {
        preserveScroll: true,
        onSuccess: () => {
            barangayForm.reset();
            barangayOptions.value = props.initialOptions;
        },
    });
}

function submitProvince() {
    provinceForm.post(route('reports.province'), {
        preserveScroll: true,
        onSuccess: () => provinceForm.reset('province', 'project_id'),
    });
}

const packsForm = useForm({
    project_id: '',
    province: '',
    district: '',
    municipality: '',
    barangay: '',
});

const packsOptions = ref(props.initialOptions);

function onPacksFilters(next) {
    Object.assign(packsForm, next);
    loadOptions(next, packsOptions);
}

function submitPack(routeName) {
    packsForm.post(route(routeName), {
        preserveScroll: true,
        onSuccess: () => {
            packsForm.reset();
            packsOptions.value = props.initialOptions;
        },
    });
}

const builderForm = useForm({
    from: '',
    to: '',
    project_id: '',
    province: '',
    district: '',
    municipality: '',
    barangay: '',
    sections: ['ops_period', 'fleet'],
});

const builderOptions = ref(props.initialOptions);

const packSections = [
    { code: 'ops_period', label: 'Period health' },
    { code: 'fleet', label: 'Fleet inventory' },
    { code: 'incidents', label: 'Incidents' },
    { code: 'progress', label: 'Progress' },
];

function onBuilderFilters(next) {
    Object.assign(builderForm, next);
    loadOptions(next, builderOptions);
}

function setPreset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    builderForm.from = from.toISOString().slice(0, 10);
    builderForm.to = to.toISOString().slice(0, 10);
}

function submitBuilder() {
    builderForm.post(route('reports.combined'), {
        preserveScroll: true,
        onSuccess: () => {
            builderForm.reset();
            builderForm.sections = ['ops_period', 'fleet'];
            builderOptions.value = props.initialOptions;
        },
    });
}

const hasPending = computed(() =>
    (props.exports?.data ?? []).some((e) => e.status === 'PENDING' || e.status === 'PROCESSING'),
);

let pollTimer = null;

onMounted(() => {
    pollTimer = setInterval(() => {
        if (hasPending.value) {
            router.reload({ only: ['exports'], preserveScroll: true });
        }
    }, 3000);
});

onBeforeUnmount(() => clearInterval(pollTimer));

function download(exportItem) {
    window.location = route('reports.download', exportItem.id);
}

function downloadCsv(exportItem) {
    window.location = route('reports.csv', exportItem.id);
}

const statusStyles = {
    PENDING: 'bg-amber-50 text-amber-700 border-amber-200',
    PROCESSING: 'bg-amber-50 text-amber-700 border-amber-200',
    DONE: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    FAILED: 'bg-red-50 text-red-700 border-red-200',
};
</script>

<template>
  <Head title="Reports" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">Reports</h2>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Project Summary Report -->
      <div class="dict-card overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-500 rounded-lg flex items-center justify-center shrink-0">
              <IconFileDescription class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Project Summary Report</h3>
              <p class="text-sm text-slate-500">PDF summary of all sites per project</p>
            </div>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="submitProject">
            <label for="project-summary" class="block text-sm font-medium text-slate-700 mb-1.5">Project</label>
            <select
              id="project-summary" v-model="projectForm.project_id"
              class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40 mb-4"
            >
              <option value="">Select a project…</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
            </select>
            <div v-if="!projects?.length" class="text-sm text-slate-400 mb-4">No projects available.</div>
            <button
              type="submit" :disabled="projectForm.processing || !projectForm.project_id"
              class="inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
            >
              <IconLoader2 v-if="projectForm.processing" class="w-4 h-4 animate-spin" />
              {{ projectForm.processing ? 'Submitting…' : 'Generate PDF' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Province Report -->
      <div class="dict-card overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-500 rounded-lg flex items-center justify-center shrink-0">
              <IconMapPin class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Province Report</h3>
              <p class="text-sm text-slate-500">Generate report filtered by province</p>
            </div>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="submitProvince">
            <label for="province" class="block text-sm font-medium text-slate-700 mb-1.5">Province</label>
            <select
              id="province" v-model="provinceForm.province"
              class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40 mb-1.5"
            >
              <option value="">Select a province…</option>
              <option v-for="province in provinceOptions" :key="province" :value="province">{{ province }}</option>
            </select>
            <div v-if="provinceForm.errors.province" class="text-xs text-red-600 mb-2">{{ provinceForm.errors.province }}</div>

            <label for="project-filter" class="block text-sm font-medium text-slate-700 mb-1.5">Filter by project (optional)</label>
            <select
              id="project-filter" v-model="provinceForm.project_id"
              class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40 mb-4"
            >
              <option value="">All projects</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
            </select>

            <button
              type="submit" :disabled="provinceForm.processing"
              class="inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
            >
              <IconLoader2 v-if="provinceForm.processing" class="w-4 h-4 animate-spin" />
              {{ provinceForm.processing ? 'Submitting…' : 'Generate PDF' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Barangay Coverage Report -->
      <div class="dict-card overflow-hidden lg:col-span-2">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-500 rounded-lg flex items-center justify-center shrink-0">
              <IconTarget class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Barangay Coverage — Installed vs Total</h3>
              <p class="text-sm text-slate-500">Barangays with Free WiFi vs total barangays, and what remains to install</p>
            </div>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="submitBarangayCoverage">
            <GeoFilterFields
              :projects="projects"
              :site-types="siteTypes"
              :options="barangayOptions"
              :filters="barangayForm.data()"
              :show-site-type="false"
              @update:filters="onBarangayFilters"
            />
            <button
              type="submit" :disabled="barangayForm.processing"
              class="mt-4 inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
            >
              <IconLoader2 v-if="barangayForm.processing" class="w-4 h-4 animate-spin" />
              {{ barangayForm.processing ? 'Submitting…' : 'Generate PDF' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Site Type Coverage Report -->
      <div class="dict-card overflow-hidden lg:col-span-2">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-500 rounded-lg flex items-center justify-center shrink-0">
              <IconTable class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Site Type Coverage — Actual vs Registered</h3>
              <p class="text-sm text-slate-500">Per site type: registered sites, sites with deployed devices, and the gap</p>
            </div>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="submitCoverage">
            <GeoFilterFields
              :projects="projects"
              :site-types="siteTypes"
              :options="coverageOptions"
              :filters="coverageForm.data()"
              :show-status="true"
              @update:filters="onCoverageFilters"
            />
            <button
              type="submit" :disabled="coverageForm.processing"
              class="mt-4 inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
            >
              <IconLoader2 v-if="coverageForm.processing" class="w-4 h-4 animate-spin" />
              {{ coverageForm.processing ? 'Submitting…' : 'Generate PDF' }}
            </button>
          </form>
        </div>
      </div>
      <!-- Operations packs: period health + fleet inventory -->
      <div class="dict-card overflow-hidden lg:col-span-2">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-500 rounded-lg flex items-center justify-center shrink-0">
              <IconFileDescription class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Operations packs</h3>
              <p class="text-sm text-slate-500">Period health vs the previous window, and the equipment fleet register</p>
            </div>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="submitPack('reports.ops-period')">
            <GeoFilterFields
              :projects="projects"
              :site-types="siteTypes"
              :options="packsOptions"
              :filters="packsForm.data()"
              :show-site-type="false"
              @update:filters="onPacksFilters"
            />
            <div class="mt-4 flex flex-wrap gap-3">
              <button
                type="submit" :disabled="packsForm.processing"
                class="inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
              >
                <IconLoader2 v-if="packsForm.processing" class="w-4 h-4 animate-spin" />
                {{ packsForm.processing ? 'Submitting…' : 'Period health PDF' }}
              </button>
              <button
                type="button" :disabled="packsForm.processing"
                class="inline-flex items-center gap-2 bg-white text-slate-700 border border-slate-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
                @click="submitPack('reports.fleet')"
              >
                Fleet inventory PDF
              </button>
              <button
                type="button" :disabled="packsForm.processing"
                class="inline-flex items-center gap-2 bg-white text-slate-700 border border-slate-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
                @click="submitPack('reports.incidents')"
              >
                Incidents PDF
              </button>
              <button
                type="button" :disabled="packsForm.processing"
                class="inline-flex items-center gap-2 bg-white text-slate-700 border border-slate-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
                @click="submitPack('reports.progress')"
              >
                Progress PDF
              </button>
            </div>
          </form>
        </div>
      </div>
      <!-- Report builder: period + sections in one pack -->
      <div class="dict-card overflow-hidden lg:col-span-2">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent-500 rounded-lg flex items-center justify-center shrink-0">
              <IconMapPin class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Report builder</h3>
              <p class="text-sm text-slate-500">One pack with the sections you pick, for the period and area you pick</p>
            </div>
          </div>
        </div>
        <div class="p-6">
          <form @submit.prevent="submitBuilder">
            <div class="flex flex-wrap items-end gap-3 mb-3">
              <div>
                <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Period</span>
                <div class="flex gap-2">
                  <button
                    v-for="days in [7, 14, 30]" :key="days" type="button"
                    class="px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-600 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40"
                    @click="setPreset(days)"
                  >
                    {{ days }}d
                  </button>
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5" for="builder-from">From</label>
                <input id="builder-from" v-model="builderForm.from" type="date" class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5" for="builder-to">To</label>
                <input id="builder-to" v-model="builderForm.to" type="date" class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40" />
              </div>
            </div>
            <GeoFilterFields
              :projects="projects"
              :site-types="siteTypes"
              :options="builderOptions"
              :filters="builderForm.data()"
              :show-site-type="false"
              @update:filters="onBuilderFilters"
            />
            <fieldset class="mt-4">
              <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Sections</legend>
              <div class="flex flex-wrap gap-4">
                <label v-for="section in packSections" :key="section.code" class="inline-flex items-center gap-2 text-sm text-slate-700">
                  <input v-model="builderForm.sections" type="checkbox" :value="section.code" class="rounded border-slate-300 text-accent-600 focus:ring-accent-500/40" />
                  {{ section.label }}
                </label>
              </div>
              <p v-if="builderForm.errors.sections" class="mt-1 text-xs text-red-600">{{ builderForm.errors.sections }}</p>
            </fieldset>
            <button
              type="submit" :disabled="builderForm.processing"
              class="mt-4 inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-60"
            >
              <IconLoader2 v-if="builderForm.processing" class="w-4 h-4 animate-spin" />
              {{ builderForm.processing ? 'Submitting…' : 'Generate pack' }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Recent exports -->
    <div class="dict-card mt-6 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-800">Your recent reports</h3>
        <p class="text-sm text-slate-500">Generated files stay available until cleaned up periodically.</p>
      </div>
      <ul v-if="exports?.data?.length" class="divide-y divide-slate-100">
        <li v-for="exportItem in exports.data" :key="exportItem.id" class="px-6 py-3 flex items-center gap-3 flex-wrap">
          <span
            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
            :class="statusStyles[exportItem.status] || 'border-slate-200 bg-slate-50 text-slate-600'"
          >
            <IconLoader2 v-if="exportItem.status === 'PENDING' || exportItem.status === 'PROCESSING'" class="w-3 h-3 animate-spin" />
            <IconCircleCheck v-else-if="exportItem.status === 'DONE'" class="w-3.5 h-3.5" />
            <IconCircleX v-else-if="exportItem.status === 'FAILED'" class="w-3.5 h-3.5" />
            {{ exportItem.status }}
          </span>
          <span class="text-sm text-slate-700 font-medium">
            {{ exportItem.download_name || typeLabels[exportItem.type] || 'Report' }}
          </span>
          <span v-if="exportItem.scope_line" class="text-xs text-slate-400">{{ exportItem.scope_line }}</span>
          <span class="text-xs text-slate-400">{{ new Date(exportItem.created_at).toLocaleString() }}</span>
          <span v-if="exportItem.error" class="text-xs text-red-600 w-full">{{ exportItem.error }}</span>
          <button
            v-if="exportItem.status === 'FAILED'" class="ml-auto inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-300 rounded font-medium transition-colors"
            @click="router.post(route('reports.retry', exportItem.id))"
          >
            <IconRefresh class="w-4 h-4" /> Retry
          </button>
          <button
            v-if="exportItem.status === 'DONE'" class="ml-auto inline-flex items-center gap-1.5 text-sm text-accent-500 hover:text-accent-600 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded font-medium transition-colors"
            @click="download(exportItem)"
          >
            <IconDownload class="w-4 h-4" /> Download
          </button>
          <button
            v-if="exportItem.status === 'DONE' && exportItem.type !== 'combined'" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded font-medium transition-colors"
            @click="downloadCsv(exportItem)"
          >
            CSV
          </button>
        </li>
      </ul>
      <div v-else class="px-6 py-8 text-center text-sm text-slate-400">No reports generated yet — use a report card above to queue one.</div>
      <div v-if="exports?.links" class="px-6 py-4 border-t border-slate-100">
        <Pagination :links="exports.links" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
