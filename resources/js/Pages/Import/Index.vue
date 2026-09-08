<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconUpload, IconChevronRight, IconFileSpreadsheet } from '@tabler/icons-vue';
import { ref } from 'vue';

defineProps({ batches: Object });

const form = useForm({
    file: null,
    type: 'sites',
});

const fileInput = ref(null);
const fileName = ref('');
const dragging = ref(false);

function onFileChange(event) {
    form.file = event.target.files?.[0] ?? null;
    fileName.value = form.file?.name ?? '';
}

function onDrop(event) {
    dragging.value = false;
    const dropped = event.dataTransfer?.files?.[0];
    if (!dropped) return;
    form.file = dropped;
    fileName.value = dropped.name;
}

function submit() {
    form.post(route('import.upload'), {
        onSuccess: () => {
            form.reset();
            fileName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
  <Head title="Import" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">Excel Import</h2>
    </template>

    <!-- Upload Card -->
    <div class="dict-card p-6 mb-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4">Upload File</h3>
      <form enctype="multipart/form-data" @submit.prevent="submit">
        <div class="flex items-center gap-3 mb-4">
          <label for="import-type" class="text-sm font-medium text-slate-700">Import as</label>
          <select
            id="import-type" v-model="form.type"
            class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring focus:ring-accent-500/40"
          >
            <option value="region_workbook">Region status workbook (multi-sheet)</option>
            <option value="sites">Sites & daily statuses</option>
            <option value="devices">Devices (inventory)</option>
          </select>
          <div v-if="form.errors.type" class="text-xs text-red-600">{{ form.errors.type }}</div>
        </div>

        <div
          class="border-2 border-dashed rounded-lg p-8 text-center transition"
          :class="dragging ? 'border-accent-500 bg-accent-500/5' : 'border-slate-300 hover:border-accent-500/60'"
          @dragover.prevent="dragging = true"
          @dragleave.prevent="dragging = false"
          @drop.prevent="onDrop"
        >
          <IconFileSpreadsheet v-if="fileName" class="w-10 h-10 text-emerald-500 mx-auto mb-2" />
          <IconUpload v-else class="w-10 h-10 text-slate-400 mx-auto mb-2" />
          <p v-if="fileName" class="text-sm text-slate-700 font-medium">{{ fileName }}</p>
          <p v-else class="text-sm text-slate-600">
            <label for="file-upload" class="text-accent-500 font-medium cursor-pointer hover:text-accent-600 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded">Browse files</label>
            or drag and drop
          </p>
          <p class="text-xs text-slate-400 mt-1">.xlsx, .xls, .csv up to 10MB</p>
          <input id="file-upload" ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileChange" />
        </div>
        <div v-if="form.errors.file" class="text-xs text-red-600 mt-2">{{ form.errors.file }}</div>

        <button
          type="submit" :disabled="form.processing || !form.file"
          class="mt-4 inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ form.processing ? 'Uploading…' : 'Upload & Import' }}
        </button>
      </form>
    </div>

    <!-- Import History -->
    <div class="dict-card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Import History</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">File</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3">Success</th>
              <th class="px-6 py-3">Failed</th>
              <th class="px-6 py-3">Date</th>
              <th class="px-6 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="batch in batches.data" :key="batch.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4 text-sm font-medium text-slate-700">
                {{ batch.filename }}
                <span v-if="batch.type === 'devices'" class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-accent-500/10 text-accent-500 uppercase">Devices</span>
              </td>
              <td class="px-6 py-4 text-sm">
                <StatusPill :status="batch.job_status" />
              </td>
              <td class="px-6 py-4 text-sm text-green-700 font-medium tabular-nums">{{ batch.rows_success }}</td>
              <td class="px-6 py-4 text-sm text-red-600 font-medium tabular-nums">{{ batch.rows_failed }}</td>
              <td class="px-6 py-4 text-sm text-slate-500">{{ batch.created_at }}</td>
              <td class="px-6 py-4 text-sm">
                <Link :href="route('import.show', batch.id)" class="text-accent-500 hover:text-accent-600 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded font-medium inline-flex items-center gap-1 transition-colors">
                  Details <IconChevronRight class="w-4 h-4" />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!batches.data?.length" class="px-6 py-12 text-center">
        <IconUpload class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No import history yet.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
