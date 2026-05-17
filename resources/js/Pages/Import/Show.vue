<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ batch: Object });
</script>

<template>
    <Head title="Import Details" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import Details</h2>
        </template>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <dl class="grid grid-cols-2 gap-4">
                        <div><dt class="text-sm text-gray-500">File</dt><dd>{{ batch.filename }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Status</dt><dd>{{ batch.job_status }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Total Rows</dt><dd>{{ batch.rows_total }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Success</dt><dd class="text-green-600">{{ batch.rows_success }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Failed</dt><dd class="text-red-600">{{ batch.rows_failed }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Imported By</dt><dd>{{ batch.importer?.name }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Started</dt><dd>{{ batch.started_at }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Completed</dt><dd>{{ batch.completed_at }}</dd></div>
                    </dl>
                    <div v-if="batch.error_log && batch.error_log.length" class="mt-6">
                        <h4 class="font-semibold mb-2">Errors ({{ batch.error_log.length }})</h4>
                        <div class="bg-red-50 rounded p-3 text-sm space-y-1">
                            <div v-for="(error, i) in batch.error_log" :key="i" class="text-red-700">
                                Row {{ error.row }}: {{ error.message }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
