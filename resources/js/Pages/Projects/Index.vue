<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconFolder, IconPlus } from '@tabler/icons-vue';
defineProps({ projects: Array });
</script>

<template>
    <Head title="Projects" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Projects</h2>
                <Link :href="route('projects.create')" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-1">
                    <IconPlus class="w-4 h-4" /> New Project
                </Link>
            </div>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-2">Code</th>
                                    <th class="text-left py-3 px-2">Name</th>
                                    <th class="text-left py-3 px-2">Type</th>
                                    <th class="text-left py-3 px-2">Sites</th>
                                    <th class="text-left py-3 px-2">Status</th>
                                    <th class="text-left py-3 px-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in projects" :key="project.id" class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-2">
                                        <span class="font-mono font-semibold" :style="{ color: project.marker_color }">{{ project.code }}</span>
                                    </td>
                                    <td class="py-3 px-2">{{ project.name }}</td>
                                    <td class="py-3 px-2">
                                        <span class="px-2 py-1 rounded text-xs font-medium"
                                              :class="project.report_type === 'freewifi' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                                            {{ project.report_type }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2">{{ project.sites_count }}</td>
                                    <td class="py-3 px-2">
                                        <span class="px-2 py-1 rounded text-xs"
                                              :class="project.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                            {{ project.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-2">
                                        <Link :href="route('projects.show', project.id)" class="text-blue-600 hover:underline text-xs">View</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
