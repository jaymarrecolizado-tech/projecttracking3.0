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
            <h2 class="font-semibold text-lg text-slate-800 leading-tight">Dashboard</h2>
        </template>

        <div>
            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div class="bg-gradient-to-r from-blue-50 to-white rounded-lg border-l-4 border-blue-500 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Total Projects</p>
                            <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total_projects }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-2.5 shadow-sm">
                            <IconBuilding class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-emerald-50 to-white rounded-lg border-l-4 border-emerald-500 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Total Sites</p>
                            <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total_sites }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-2.5 shadow-sm">
                            <IconMap class="w-6 h-6 text-emerald-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-teal-50 to-white rounded-lg border-l-4 border-teal-500 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Active Sites</p>
                            <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.active_sites }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-2.5 shadow-sm">
                            <IconActivity class="w-6 h-6 text-teal-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-amber-50 to-white rounded-lg border-l-4 border-amber-500 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">UP Today</p>
                            <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total_up_today }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-2.5 shadow-sm">
                            <IconUpload class="w-6 h-6 text-amber-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dict-card p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link :href="route('map.index')" class="group dict-card p-5 hover:shadow-md hover:border-blue-200 transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition">
                                <IconMap class="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <div class="font-medium text-slate-700 group-hover:text-blue-700">View Map</div>
                                <div class="text-sm text-slate-500">Browse sites on interactive map</div>
                            </div>
                        </div>
                    </Link>
                    <Link :href="route('import.index')" class="group dict-card p-5 hover:shadow-md hover:border-emerald-200 transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition">
                                <IconUpload class="w-5 h-5 text-emerald-600" />
                            </div>
                            <div>
                                <div class="font-medium text-slate-700 group-hover:text-emerald-700">Import Data</div>
                                <div class="text-sm text-slate-500">Upload Excel files</div>
                            </div>
                        </div>
                    </Link>
                    <Link :href="route('reports.index')" class="group dict-card p-5 hover:shadow-md hover:border-amber-200 transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                                <IconActivity class="w-5 h-5 text-amber-600" />
                            </div>
                            <div>
                                <div class="font-medium text-slate-700 group-hover:text-amber-700">Reports</div>
                                <div class="text-sm text-slate-500">Generate PDF reports</div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
