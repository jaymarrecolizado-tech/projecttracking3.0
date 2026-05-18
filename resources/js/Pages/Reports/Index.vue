<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconFileDescription, IconMapPin } from '@tabler/icons-vue';
defineProps({ projects: Array });
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-lg text-slate-800 leading-tight">Reports</h2>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Project Summary Report -->
            <div class="dict-card overflow-hidden">
                <div class="bg-gradient-to-r from-blue-50 to-blue-100/50 px-6 py-4 border-b border-blue-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                            <IconFileDescription class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-800">Project Summary Report</h3>
                            <p class="text-sm text-slate-500">PDF summary of all sites per project</p>
                        </div>
                    </div>
                </div>
                <div class="p-6 space-y-2">
                    <form v-for="project in projects" :key="project.id" :action="route('reports.project', project.id)" method="POST">
                        <input type="hidden" name="_token" :value="csrf" />
                        <button type="submit"
                            class="w-full text-left p-3 rounded-lg hover:bg-blue-50 transition flex items-center gap-3 group">
                            <div class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: project.marker_color || '#64748b' }"></div>
                            <span class="text-sm text-slate-700 group-hover:text-blue-700 font-medium">{{ project.name }}</span>
                        </button>
                    </form>
                    <div v-if="!projects?.length" class="text-sm text-slate-400 text-center py-4">No projects available.</div>
                </div>
            </div>

            <!-- Province Report -->
            <div class="dict-card overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-50 to-emerald-100/50 px-6 py-4 border-b border-emerald-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-emerald-600 rounded-lg flex items-center justify-center">
                            <IconMapPin class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-800">Province Report</h3>
                            <p class="text-sm text-slate-500">Generate report filtered by province</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <form :action="route('reports.province')" method="POST">
                        <input type="hidden" name="_token" :value="csrf" />
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Province Name</label>
                        <input type="text" name="province" placeholder="Enter province name"
                            class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 mb-4" />
                        <button type="submit"
                            class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-700 transition">
                            Generate PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
<script>
export default { data() { return { csrf: document.querySelector('meta[name="csrf-token"]')?.content || '' } } }
</script>
