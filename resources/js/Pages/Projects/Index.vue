<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconPlus, IconChevronRight, IconFolder } from '@tabler/icons-vue';
defineProps({ projects: Array });
</script>

<template>
  <Head title="Projects" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Projects</h2>
    </template>

    <div class="dict-card overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-800">All Projects</h3>
        <Link :href="route('projects.create')" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center gap-1.5 hover:bg-blue-700 transition">
          <IconPlus class="w-4 h-4" /> New Project
        </Link>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">Code</th>
              <th class="px-6 py-3">Name</th>
              <th class="px-6 py-3">Type</th>
              <th class="px-6 py-3">Sites</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="project in projects" :key="project.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4 text-sm">
                <span class="font-mono font-semibold" :style="{ color: project.marker_color }">{{ project.code }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-slate-700 font-medium">{{ project.name }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium"
                  :class="project.report_type === 'freewifi' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                >
                  {{ project.report_type }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-slate-700">{{ project.sites_count }}</td>
              <td class="px-6 py-4 text-sm">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium"
                  :class="project.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                >
                  {{ project.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm">
                <Link :href="route('projects.show', project.id)" class="text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1">
                  View <IconChevronRight class="w-4 h-4" />
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="!projects?.length" class="px-6 py-12 text-center">
        <IconFolder class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No projects found.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
