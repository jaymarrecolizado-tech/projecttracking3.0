<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconBuilding, IconMap, IconArrowLeft } from '@tabler/icons-vue';
defineProps({ project: Object });
</script>

<template>
  <Head :title="project.name" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">{{ project.name }}</h2>
    </template>

    <div>
      <!-- Back Link -->
      <Link :href="route('projects.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded mb-4 transition-colors">
        <IconArrowLeft class="w-4 h-4" /> Back to Projects
      </Link>

      <!-- Header Card -->
      <div class="dict-card p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-xl font-bold text-slate-800">{{ project.name }}</h1>
            <span class="font-mono font-semibold text-sm" :style="{ color: project.marker_color }">{{ project.code }}</span>
          </div>
          <div class="flex gap-2">
            <Link :href="route('projects.sites', project.id)" class="inline-flex items-center gap-2 bg-accent-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition">
              <IconBuilding class="w-4 h-4" /> View Sites
            </Link>
            <Link :href="route('map.index') + '?project_id=' + project.id" class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] transition">
              <IconMap class="w-4 h-4" /> View on Map
            </Link>
          </div>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="dict-card p-6">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Project Information</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-slate-500">Code</dt>
              <dd class="font-mono font-semibold" :style="{ color: project.marker_color }">{{ project.code }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Report Type</dt>
              <dd>
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-medium"
                  :class="project.report_type === 'freewifi' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                >
                  {{ project.report_type }}
                </span>
              </dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Status</dt>
              <dd>
                <StatusPill :status="project.is_active ? 'Active' : 'Inactive'" />
              </dd>
            </div>
          </dl>
        </div>

        <div class="dict-card p-6">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Statistics</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-slate-500">Total Sites</dt>
              <dd class="font-semibold text-slate-800 text-lg">{{ project.sites?.length || 0 }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Milestones -->
      <div v-if="project.report_type === 'milestone' && project.milestones?.length" class="dict-card p-6">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Milestones</h3>
        <div class="space-y-3">
          <div v-for="m in project.milestones" :key="m.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
            <div>
              <span class="font-medium text-slate-800">{{ m.milestone_name }}</span>
              <span class="text-xs text-slate-400 ml-2">Order: {{ m.milestone_order }}</span>
            </div>
            <span class="text-sm font-bold text-accent-500 bg-accent-500/10 px-2.5 py-1 rounded">{{ m.weight_pct }}%</span>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
