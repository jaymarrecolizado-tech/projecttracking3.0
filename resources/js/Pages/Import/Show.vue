<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconArrowLeft } from '@tabler/icons-vue';
defineProps({ batch: Object });
</script>

<template>
  <Head title="Import Details" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Import Details</h2>
    </template>

    <div>
      <!-- Back Link -->
      <Link :href="route('import.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 mb-4 transition">
        <IconArrowLeft class="w-4 h-4" /> Back to Imports
      </Link>

      <!-- Summary Header -->
      <div class="dict-card p-6 mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-bold text-slate-800">{{ batch.filename }}</h1>
            <p class="text-sm text-slate-500">Imported by {{ batch.importer?.name }}</p>
          </div>
          <span
            class="px-3 py-1 rounded-full text-sm font-medium" :class="{
              'bg-green-100 text-green-700': batch.job_status === 'DONE',
              'bg-yellow-100 text-yellow-700': batch.job_status === 'PROCESSING',
              'bg-red-100 text-red-700': batch.job_status === 'FAILED',
              'bg-slate-100 text-slate-500': batch.job_status === 'PENDING',
            }"
          >{{ batch.job_status }}</span>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="dict-card p-4 text-center">
          <p class="text-2xl font-bold text-slate-800">{{ batch.rows_total }}</p>
          <p class="text-xs text-slate-500 mt-1">Total Rows</p>
        </div>
        <div class="dict-card p-4 text-center">
          <p class="text-2xl font-bold text-green-600">{{ batch.rows_success }}</p>
          <p class="text-xs text-slate-500 mt-1">Success</p>
        </div>
        <div class="dict-card p-4 text-center">
          <p class="text-2xl font-bold text-red-600">{{ batch.rows_failed }}</p>
          <p class="text-xs text-slate-500 mt-1">Failed</p>
        </div>
        <div class="dict-card p-4 text-center">
          <p class="text-sm font-bold text-slate-800">{{ batch.started_at || 'N/A' }}</p>
          <p class="text-xs text-slate-500 mt-1">Started</p>
        </div>
      </div>

      <!-- Details -->
      <div class="dict-card p-6 mb-6">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Details</h3>
        <dl class="grid grid-cols-2 gap-4">
          <div>
            <dt class="text-sm text-slate-500">Completed</dt>
            <dd class="text-sm text-slate-700 mt-0.5">{{ batch.completed_at || 'N/A' }}</dd>
          </div>
          <div>
            <dt class="text-sm text-slate-500">Imported By</dt>
            <dd class="text-sm text-slate-700 mt-0.5">{{ batch.importer?.name || 'N/A' }}</dd>
          </div>
        </dl>
      </div>

      <!-- Errors -->
      <div v-if="batch.error_log && batch.error_log.length" class="dict-card p-6">
        <h3 class="text-sm font-semibold text-red-600 uppercase tracking-wider mb-3">
          Errors ({{ batch.error_log.length }})
        </h3>
        <div class="bg-red-50 rounded-lg divide-y divide-red-100">
          <div v-for="(error, i) in batch.error_log" :key="i" class="px-4 py-3 text-sm text-red-700 flex gap-2">
            <span class="font-mono text-red-400 shrink-0">Row {{ error.row }}:</span>
            <span>{{ error.message }}</span>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
