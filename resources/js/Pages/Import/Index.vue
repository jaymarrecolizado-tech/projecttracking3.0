<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ batches: Object });
</script>

<template>
    <Head title="Import" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Excel Import</h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <form :action="route('import.upload')" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="_token" :value="csrf" />
                        <label class="block mb-2 text-sm font-medium">Upload Excel File</label>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm border rounded" />
                        <p class="text-xs text-gray-500 mt-1">Accepted: .xlsx, .xls, .csv (Max 10MB)</p>
                        <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Upload & Import</button>
                    </form>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-lg mb-4">Import History</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-2">File</th>
                                <th class="text-left py-3 px-2">Status</th>
                                <th class="text-left py-3 px-2">Success</th>
                                <th class="text-left py-3 px-2">Failed</th>
                                <th class="text-left py-3 px-2">Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="batch in batches.data" :key="batch.id" class="border-b hover:bg-gray-50">
                                <td class="py-3 px-2">{{ batch.filename }}</td>
                                <td class="py-3 px-2">
                                    <span class="px-2 py-1 rounded text-xs" :class="{
                                        'bg-green-100 text-green-700': batch.job_status === 'DONE',
                                        'bg-yellow-100 text-yellow-700': batch.job_status === 'PROCESSING',
                                        'bg-red-100 text-red-700': batch.job_status === 'FAILED',
                                        'bg-gray-100 text-gray-500': batch.job_status === 'PENDING',
                                    }">{{ batch.job_status }}</span>
                                </td>
                                <td class="py-3 px-2">{{ batch.rows_success }}</td>
                                <td class="py-3 px-2">{{ batch.rows_failed }}</td>
                                <td class="py-3 px-2">{{ batch.created_at }}</td>
                                <td><Link :href="route('import.show', batch.id)" class="text-blue-600 hover:underline text-xs">Details</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
<script>
export default { data() { return { csrf: document.querySelector('meta[name="csrf-token"]')?.content || '' } } }
</script>
