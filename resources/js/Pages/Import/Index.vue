<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { IconUpload, IconChevronRight } from '@tabler/icons-vue';
import { computed } from 'vue';
defineProps({ batches: Object });
const csrf = computed(() => usePage().props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || '');
</script>

<template>
    <Head title="Import" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-lg text-slate-800 leading-tight">Excel Import</h2>
        </template>

        <!-- Upload Card -->
        <div class="dict-card p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Upload File</h3>
            <form :action="route('import.upload')" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="_token" :value="csrf" />
                <div class="border-2 border-dashed border-slate-300 rounded-lg p-8 text-center hover:border-blue-400 transition">
                    <IconUpload class="w-10 h-10 text-slate-400 mx-auto mb-2" />
                    <p class="text-sm text-slate-600">
                        <label for="file-upload" class="text-blue-600 font-medium cursor-pointer hover:text-blue-800">Browse files</label>
                        or drag and drop
                    </p>
                    <p class="text-xs text-slate-400 mt-1">.xlsx, .xls, .csv up to 10MB</p>
                    <input id="file-upload" type="file" name="file" accept=".xlsx,.xls,.csv" class="hidden" />
                </div>
                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                    Upload & Import
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
                            <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ batch.filename }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                                    'bg-green-100 text-green-700': batch.job_status === 'DONE',
                                    'bg-yellow-100 text-yellow-700': batch.job_status === 'PROCESSING',
                                    'bg-red-100 text-red-700': batch.job_status === 'FAILED',
                                    'bg-slate-100 text-slate-500': batch.job_status === 'PENDING',
                                }">{{ batch.job_status }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-green-600 font-medium">{{ batch.rows_success }}</td>
                            <td class="px-6 py-4 text-sm text-red-600 font-medium">{{ batch.rows_failed }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ batch.created_at }}</td>
                            <td class="px-6 py-4 text-sm">
                                <Link :href="route('import.show', batch.id)" class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1">
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
