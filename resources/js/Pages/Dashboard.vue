<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconMap, IconActivity, IconUpload } from '@tabler/icons-vue';

defineProps({ stats: Object });
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-lg text-slate-800 leading-tight">Dashboard</h2>
        <Link :href="route('wallboard')" class="text-sm font-medium text-blue-600 hover:text-blue-800">
          Open NOC Wallboard →
        </Link>
      </div>
    </template>

    <div>
      <!-- Stat Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-5 mb-8">
        <div class="bg-gradient-to-r from-blue-50 to-white rounded-lg border-l-4 border-blue-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Projects</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.total_projects }}</p>
        </div>
        <div class="bg-gradient-to-r from-emerald-50 to-white rounded-lg border-l-4 border-emerald-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Sites</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.total_sites }}</p>
        </div>
        <div class="bg-gradient-to-r from-teal-50 to-white rounded-lg border-l-4 border-teal-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Active Sites</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.active_sites }}</p>
        </div>
        <div class="bg-gradient-to-r from-green-50 to-white rounded-lg border-l-4 border-green-600 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">UP Today</p>
          <p class="text-2xl font-bold text-green-700 mt-1 tabular-nums">{{ stats.total_up_today }}</p>
        </div>
        <div class="rounded-lg border-l-4 p-5 shadow-sm" :class="stats.down_today > 0 ? 'from-red-50 to-white border-red-600' : 'from-slate-50 to-white border-slate-300'">
          <p class="text-sm font-medium text-slate-500">DOWN Today</p>
          <p class="text-2xl font-bold mt-1 tabular-nums" :class="stats.down_today > 0 ? 'text-red-700' : 'text-slate-400'">{{ stats.down_today }}</p>
        </div>
        <div class="bg-gradient-to-r from-amber-50 to-white rounded-lg border-l-4 border-amber-500 p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Uptime 7d</p>
          <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ stats.uptime_pct_7d }}%</p>
        </div>
      </div>

      <!-- 14-day trend -->
      <div v-if="stats.trend?.length" class="dict-card p-6 mb-8">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Site status — last 14 days</h3>
        <div class="flex items-end gap-1 h-40">
          <div v-for="day in stats.trend" :key="day.date" class="flex-1 flex flex-col items-center gap-1 group relative">
            <div class="w-full flex flex-col justify-end h-32">
              <div
                v-if="day.up > 0" class="w-full bg-green-500/90 rounded-t-sm group-hover:bg-green-500 transition-colors"
                :style="{ height: (day.up / Math.max(...stats.trend.map(d => d.up + d.down), 1) * 100) + '%' }"
              ></div>
              <div
                v-if="day.down > 0" class="w-full bg-red-500/90 rounded-b-sm"
                :style="{ height: (day.down / Math.max(...stats.trend.map(d => d.up + d.down), 1) * 100) + '%' }"
              ></div>
            </div>
            <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ day.date }}</span>
            <div class="pointer-events-none absolute opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-xs rounded px-2 py-1 bottom-full mb-1 z-10 whitespace-nowrap">
              {{ day.date }}: {{ day.up }} UP · {{ day.down }} DOWN
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="dict-card p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Link :href="route('map.index')" class="group dict-card p-5 hover:shadow-md hover:border-blue-200 transition-all duration-200">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition">
                <IconMap class="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <div class="font-medium text-slate-700 group-hover:text-blue-700">View Map</div>
                <div class="text-sm text-slate-500">Browse sites on interactive map</div>
              </div>
            </div>
          </Link>
          <Link :href="route('import.index')" class="group dict-card p-5 hover:shadow-md hover:border-emerald-200 transition-all duration-200">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition">
                <IconUpload class="w-5 h-5 text-emerald-600" />
              </div>
              <div>
                <div class="font-medium text-slate-700 group-hover:text-emerald-700">Import Data</div>
                <div class="text-sm text-slate-500">Upload Excel files</div>
              </div>
            </div>
          </Link>
          <Link :href="route('reports.index')" class="group dict-card p-5 hover:shadow-md hover:border-amber-200 transition-all duration-200">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                <IconActivity class="w-5 h-5 text-amber-600" />
              </div>
              <div>
                <div class="font-medium text-slate-700 group-hover:text-amber-700">Reports</div>
                <div class="text-sm text-slate-500">Generate PDF reports</div>
              </div>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
