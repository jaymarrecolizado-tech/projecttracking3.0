<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ accomplishments: Object });
</script>

<template>
    <Head title="Accomplishments" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Accomplishments</h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-2">Site</th>
                                <th class="text-left py-3 px-2">Milestone</th>
                                <th class="text-left py-3 px-2">Status</th>
                                <th class="text-left py-3 px-2">% Complete</th>
                                <th class="text-left py-3 px-2">Target Date</th>
                                <th class="text-left py-3 px-2">Actual Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in accomplishments.data" :key="a.id" class="border-b hover:bg-gray-50">
                                <td class="py-3 px-2">{{ a.site?.location_name }}</td>
                                <td class="py-3 px-2">{{ a.milestone?.milestone_name }}</td>
                                <td class="py-3 px-2">
                                    <span class="px-2 py-1 rounded text-xs" :class="{
                                        'bg-green-100 text-green-700': a.status === 'COMPLETED',
                                        'bg-blue-100 text-blue-700': a.status === 'IN_PROGRESS',
                                        'bg-gray-100 text-gray-500': a.status === 'NOT_STARTED',
                                        'bg-yellow-100 text-yellow-700': a.status === 'ON_HOLD',
                                        'bg-red-100 text-red-700': a.status === 'CANCELLED',
                                    }">{{ a.status }}</span>
                                </td>
                                <td class="py-3 px-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" :style="{ width: a.pct_complete + '%' }"></div>
                                        </div>
                                        <span>{{ a.pct_complete }}%</span>
                                    </div>
                                </td>
                                <td class="py-3 px-2">{{ a.target_date }}</td>
                                <td class="py-3 px-2">{{ a.actual_date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
