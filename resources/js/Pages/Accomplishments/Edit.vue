<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
defineProps({ site: Object });

const submitAccomplishment = (milestoneId, data) => {
    router.post(route('accomplishments.store'), {
        site_id: site.id,
        milestone_id: milestoneId,
        ...data,
    });
};
</script>

<template>
    <Head :title="'Accomplishments - ' + site.location_name" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Accomplishments: {{ site.location_name }}</h2>
        </template>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div v-for="a in site.accomplishments" :key="a.id" class="border-b pb-4 mb-4">
                        <h3 class="font-semibold">{{ a.milestone?.milestone_name }}</h3>
                        <div class="mt-2 flex items-center gap-4">
                            <span class="px-2 py-1 rounded text-xs" :class="{
                                'bg-green-100 text-green-700': a.status === 'COMPLETED',
                                'bg-blue-100 text-blue-700': a.status === 'IN_PROGRESS',
                                'bg-gray-100 text-gray-500': a.status === 'NOT_STARTED',
                            }">{{ a.status }}</span>
                            <div class="flex-1">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" :style="{ width: a.pct_complete + '%' }"></div>
                                </div>
                            </div>
                            <span>{{ a.pct_complete }}%</span>
                        </div>
                        <p v-if="a.remarks" class="text-sm text-gray-500 mt-1">{{ a.remarks }}</p>
                    </div>
                    <p v-if="!site.accomplishments?.length" class="text-gray-500 text-sm">No accomplishments recorded yet.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
