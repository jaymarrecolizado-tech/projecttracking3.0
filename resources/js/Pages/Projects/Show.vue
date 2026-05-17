<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconBuilding, IconMap, IconChecklist } from '@tabler/icons-vue';
defineProps({ project: Object });
</script>

<template>
    <Head :title="project.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ project.name }}</h2>
                <div class="flex gap-2">
                    <Link :href="route('projects.sites', project.id)" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm flex items-center gap-1">
                        <IconBuilding class="w-4 h-4" /> View Sites
                    </Link>
                    <Link :href="route('map.index') + '?project_id=' + project.id" class="bg-green-600 text-white px-3 py-2 rounded-lg text-sm flex items-center gap-1">
                        <IconMap class="w-4 h-4" /> View on Map
                    </Link>
                </div>
            </div>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                    <dl class="grid grid-cols-2 gap-4">
                        <div><dt class="text-sm text-gray-500">Code</dt><dd class="font-mono font-semibold" :style="{ color: project.marker_color }">{{ project.code }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Report Type</dt><dd>{{ project.report_type }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Total Sites</dt><dd>{{ project.sites?.length || 0 }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Status</dt><dd>{{ project.is_active ? 'Active' : 'Inactive' }}</dd></div>
                    </dl>
                </div>

                <div v-if="project.report_type === 'milestone' && project.milestones?.length" class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-lg mb-4">Milestones</h3>
                    <div class="space-y-3">
                        <div v-for="m in project.milestones" :key="m.id" class="p-3 bg-gray-50 rounded-lg flex justify-between items-center">
                            <div>
                                <span class="font-medium">{{ m.milestone_name }}</span>
                                <span class="text-xs text-gray-500 ml-2">Order: {{ m.milestone_order }}</span>
                            </div>
                            <span class="text-sm font-semibold">{{ m.weight_pct }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
