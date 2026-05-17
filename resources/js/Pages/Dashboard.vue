<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconMap, IconBuilding, IconActivity, IconUpload } from '@tabler/icons-vue';

defineProps({ stats: Object });
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <IconBuilding class="w-8 h-8 text-blue-500" />
                            <div class="ml-4">
                                <div class="text-2xl font-bold">{{ stats.total_projects }}</div>
                                <div class="text-sm text-gray-500">Total Projects</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <IconMap class="w-8 h-8 text-green-500" />
                            <div class="ml-4">
                                <div class="text-2xl font-bold">{{ stats.total_sites }}</div>
                                <div class="text-sm text-gray-500">Total Sites</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <IconActivity class="w-8 h-8 text-emerald-500" />
                            <div class="ml-4">
                                <div class="text-2xl font-bold">{{ stats.active_sites }}</div>
                                <div class="text-sm text-gray-500">Active Sites</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <IconUpload class="w-8 h-8 text-amber-500" />
                            <div class="ml-4">
                                <div class="text-2xl font-bold">{{ stats.total_up_today }}</div>
                                <div class="text-sm text-gray-500">UP Today</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Link :href="route('map.index')" class="p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                <div class="font-medium text-blue-700">View Map</div>
                                <div class="text-sm text-blue-500">Browse sites on interactive map</div>
                            </Link>
                            <Link :href="route('import.index')" class="p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                                <div class="font-medium text-green-700">Import Data</div>
                                <div class="text-sm text-green-500">Upload Excel files</div>
                            </Link>
                            <Link :href="route('reports.index')" class="p-4 bg-amber-50 rounded-lg hover:bg-amber-100 transition">
                                <div class="font-medium text-amber-700">Reports</div>
                                <div class="text-sm text-amber-500">Generate PDF reports</div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
