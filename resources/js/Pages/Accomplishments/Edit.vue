<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusPill from '@/Components/StatusPill.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconArrowLeft, IconChecklist } from '@tabler/icons-vue';
defineProps({ site: Object });

const progressBarColor = (status) => {
    if (status === 'COMPLETED') return 'bg-green-500';
    if (status === 'IN_PROGRESS') return 'bg-blue-500';
    return 'bg-slate-300';
};
</script>

<template>
  <Head :title="'Accomplishments - ' + site.location_name" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">Accomplishments</h2>
    </template>

    <div>
      <!-- Back Link -->
      <Link :href="route('accomplishments.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 rounded mb-4 transition-colors">
        <IconArrowLeft class="w-4 h-4" /> Back to Accomplishments
      </Link>

      <!-- Header Card -->
      <div class="dict-card p-6 mb-6">
        <h1 class="text-lg font-bold text-slate-800">Accomplishments: {{ site.location_name }}</h1>
      </div>

      <!-- Accomplishments List -->
      <div class="space-y-4">
        <div v-for="a in site.accomplishments" :key="a.id" class="dict-card p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-slate-800">{{ a.milestone?.milestone_name }}</h3>
            <StatusPill :status="a.status" />
          </div>
          <div class="flex items-center gap-3">
            <div class="flex-1 bg-slate-100 rounded-full h-2.5">
              <div
                class="h-2.5 rounded-full transition-all duration-300"
                :class="progressBarColor(a.status)"
                :style="{ width: a.pct_complete + '%' }"
              ></div>
            </div>
            <span class="text-sm font-semibold text-slate-600 w-12 text-right">{{ a.pct_complete }}%</span>
          </div>
          <p v-if="a.remarks" class="text-sm text-slate-500 mt-2 italic">{{ a.remarks }}</p>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!site.accomplishments?.length" class="dict-card p-12 text-center">
        <IconChecklist class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-slate-500">No accomplishments recorded yet.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
