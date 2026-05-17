<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
defineProps({ site: Object });
</script>

<template>
    <Head :title="site.location_name" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ site.location_name }}</h2>
        </template>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <dl class="grid grid-cols-2 gap-4">
                        <div><dt class="text-sm text-gray-500">Project</dt><dd>{{ site.project?.name }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Status</dt><dd>{{ site.status }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Barangay</dt><dd>{{ site.barangay }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Municipality</dt><dd>{{ site.municipality }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Province</dt><dd>{{ site.province }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Region</dt><dd>{{ site.region }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Latitude</dt><dd>{{ site.latitude }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Longitude</dt><dd>{{ site.longitude }}</dd></div>
                        <div><dt class="text-sm text-gray-500">ISP</dt><dd>{{ site.isp_provider }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Bandwidth</dt><dd>{{ site.bw_download_cir }} Mbps</dd></div>
                    </dl>
                    <div class="mt-6 flex gap-3">
                        <Link :href="route('sites.daily-grid', site.id)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Daily Status</Link>
                        <Link :href="route('sites.accomplishments', site.id)" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">Accomplishments</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
