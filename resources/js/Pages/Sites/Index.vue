<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ sites: Object, project: Object });
</script>

<template>
    <Head title="Sites" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ project ? `${project.name} - Sites` : 'All Sites' }}
            </h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3 px-2">Location</th>
                                    <th class="text-left py-3 px-2">Project</th>
                                    <th class="text-left py-3 px-2">Municipality</th>
                                    <th class="text-left py-3 px-2">Province</th>
                                    <th class="text-left py-3 px-2">Status</th>
                                    <th class="text-left py-3 px-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="site in sites.data" :key="site.id" class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-2">{{ site.location_name }}</td>
                                    <td class="py-3 px-2">
                                        <span class="text-xs font-mono" :style="{ color: site.project?.marker_color }">{{ site.project?.code }}</span>
                                    </td>
                                    <td class="py-3 px-2">{{ site.municipality }}</td>
                                    <td class="py-3 px-2">{{ site.province }}</td>
                                    <td class="py-3 px-2">
                                        <span class="px-2 py-1 rounded text-xs" :class="{
                                            'bg-green-100 text-green-700': site.status === 'active',
                                            'bg-red-100 text-red-700': site.status === 'inactive',
                                            'bg-yellow-100 text-yellow-700': site.status === 'planned',
                                            'bg-gray-100 text-gray-500': site.status === 'decommissioned',
                                            'bg-orange-100 text-orange-700': site.status === 'maintenance',
                                        }">{{ site.status }}</span>
                                    </td>
                                    <td class="py-3 px-2">
                                        <Link :href="route('sites.show', site.id)" class="text-blue-600 hover:underline text-xs">View</Link>
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
