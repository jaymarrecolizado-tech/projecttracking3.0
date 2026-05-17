<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ site: Object, statuses: Object });
</script>

<template>
    <Head title="Daily Status" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ site ? `${site.location_name} - Daily Status` : 'Daily Statuses' }}
            </h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-3 px-2">Date</th>
                                <th class="text-left py-3 px-2">Status</th>
                                <th class="text-left py-3 px-2">Bandwidth (Mbps)</th>
                                <th class="text-left py-3 px-2">Users</th>
                                <th class="text-left py-3 px-2">Uptime %</th>
                                <th class="text-left py-3 px-2">Entry Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="status in statuses?.data || statuses" :key="status.id" class="border-b hover:bg-gray-50">
                                <td class="py-3 px-2">{{ status.date }}</td>
                                <td class="py-3 px-2">
                                    <span class="px-2 py-1 rounded text-xs font-semibold" :class="{
                                        'bg-green-100 text-green-700': status.status === 'UP',
                                        'bg-red-100 text-red-700': status.status === 'DOWN',
                                        'bg-gray-100 text-gray-500': status.status === 'NO_DATA',
                                    }">{{ status.status }}</span>
                                </td>
                                <td class="py-3 px-2">{{ status.bandwidth_utilization_mbps }}</td>
                                <td class="py-3 px-2">{{ status.total_unique_users }}</td>
                                <td class="py-3 px-2">{{ status.uptime_percent }}</td>
                                <td class="py-3 px-2">
                                    <span class="text-xs" :class="{
                                        'text-yellow-600': status.entry_status === 'DRAFT',
                                        'text-blue-600': status.entry_status === 'SUBMITTED',
                                        'text-green-600': status.entry_status === 'APPROVED',
                                        'text-gray-500': status.entry_status === 'LOCKED',
                                    }">{{ status.entry_status }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
