<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconFileDescription } from '@tabler/icons-vue';
defineProps({ projects: Array });
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reports</h2>
        </template>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-lg mb-4">Project Summary Report</h3>
                        <p class="text-sm text-gray-500 mb-4">Generate a PDF summary of all sites for a project.</p>
                        <form v-for="project in projects" :key="project.id" :action="route('reports.project', project.id)" method="POST" class="mb-2">
                            <input type="hidden" name="_token" :value="csrf" />
                            <button type="submit" class="w-full text-left p-3 bg-gray-50 rounded-lg hover:bg-blue-50 transition flex items-center gap-2">
                                <IconFileDescription class="w-5 h-5 text-blue-500" />
                                <span>{{ project.name }}</span>
                            </button>
                        </form>
                    </div>
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-lg mb-4">Province Report</h3>
                        <p class="text-sm text-gray-500 mb-4">Generate report filtered by province.</p>
                        <form :action="route('reports.province')" method="POST" class="space-y-3">
                            <input type="hidden" name="_token" :value="csrf" />
                            <input type="text" name="province" placeholder="Enter province name" class="w-full rounded border-gray-300" />
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Generate PDF</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
<script>
export default { data() { return { csrf: document.querySelector('meta[name="csrf-token"]')?.content || '' } } }
</script>
