<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconCircleCheck, IconCircleX, IconDownload, IconFileDescription, IconLoader2, IconMapPin } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
    projects: Array,
    exports: Array,
});

const provinceForm = useForm({
    province: '',
    project_id: '',
});

function submitProject(project) {
    router.post(route('reports.project', project.id), {}, { preserveScroll: true });
}

function submitProvince() {
    provinceForm.post(route('reports.province'), {
        preserveScroll: true,
        onSuccess: () => provinceForm.reset('province', 'project_id'),
    });
}

const hasPending = computed(() =>
    props.exports.some((e) => e.status === 'PENDING' || e.status === 'PROCESSING'),
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

const statusStyles = {
    PENDING: 'bg-amber-50 text-amber-700 border-amber-200',
    PROCESSING: 'bg-blue-50 text-blue-700 border-blue-200',
    DONE: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    FAILED: 'bg-red-50 text-red-700 border-red-200',
};
</script>

<template>
  <Head title="Reports" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Reports</h2>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Project Summary Report -->
      <div class="dict-card overflow-hidden">
        <div class="bg-gradient-to-r from-blue-50 to-blue-100/50 px-6 py-4 border-b border-blue-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
              <IconFileDescription class="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 class="font-semibold text-slate-800">Project Summary Report</h3>
              <p class="text-sm text-slate-500">PDF summary of all sites per project</p>
            </div>
          </div>
        </div>
        <div class="p-6 space-y-2">
          <button
            v-for="project in projects" :key="project.id" type="button" class="w-full text-left p-3 rounded-lg hover:bg-blue-50 transition flex items-center gap-3 group disabled:opacity-60"
            :disabled="hasPending && false"
            @click="submitProject(project)"
          >
            <div class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: project.marker_color || '#64748b' }"></div>
            <span class="text-sm text-slate-700 group-hover:text-blue-700 font-medium">{{ project.name }}</span>
          </button>
          <div v-if="!projects?.length" class="text-sm text-slate-400 text-center py-4">No projects available.</div>
        </div>
      </div>

      <!-- Province Report -->
      <div class="dict-card overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-50 to-emerald-100/50 px-6 py-4 border-b border-emerald-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-600 rounded-lg flex items-center justify-center">
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
            <label for="province" class="block text-sm font-medium text-slate-700 mb-1.5">Province Name</label>
            <input
              id="province" v-model="provinceForm.province" type="text" placeholder="Enter province name"
              class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 mb-1.5"
            />
            <div v-if="provinceForm.errors.province" class="text-xs text-red-600 mb-2">{{ provinceForm.errors.province }}</div>

            <label for="project-filter" class="block text-sm font-medium text-slate-700 mb-1.5">Filter by project (optional)</label>
            <select
              id="project-filter" v-model="provinceForm.project_id"
              class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 mb-4"
            >
              <option value="">All projects</option>
              <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
            </select>

            <button
              type="submit" :disabled="provinceForm.processing"
              class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-700 transition disabled:opacity-60"
            >
              <IconLoader2 v-if="provinceForm.processing" class="w-4 h-4 animate-spin" />
              {{ provinceForm.processing ? 'Submitting…' : 'Generate PDF' }}
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
      <ul v-if="exports?.length" class="divide-y divide-slate-100">
        <li v-for="exportItem in exports" :key="exportItem.id" class="px-6 py-3 flex items-center gap-3 flex-wrap">
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
            {{ exportItem.download_name || (exportItem.type === 'project' ? 'Project report' : 'Province report') }}
          </span>
          <span class="text-xs text-slate-400">{{ new Date(exportItem.created_at).toLocaleString() }}</span>
          <span v-if="exportItem.error" class="text-xs text-red-600 w-full">{{ exportItem.error }}</span>
          <button
            v-if="exportItem.status === 'DONE'" class="ml-auto inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 font-medium"
            @click="download(exportItem)"
          >
            <IconDownload class="w-4 h-4" /> Download
          </button>
        </li>
      </ul>
      <div v-else class="px-6 py-8 text-center text-sm text-slate-400">No reports generated yet.</div>
    </div>
  </AuthenticatedLayout>
</template>
