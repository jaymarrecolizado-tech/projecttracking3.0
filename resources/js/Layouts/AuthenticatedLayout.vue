<script setup>
import { ref, watch, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    IconDashboard, IconMap, IconFolder, IconBuilding, IconActivity,
    IconChecklist, IconUpload, IconReportAnalytics, IconRouter,
    IconMenu2, IconX, IconLogout, IconUser, IconTool, IconCalendarCheck,
} from '@tabler/icons-vue'
import ToastStack from '@/Components/ToastStack.vue'

const page = usePage()
const can = (permission) => !permission || page.props.auth.permissions?.includes(permission)

const sidebarOpen = ref(false)

const navGroups = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', route: 'dashboard', icon: IconDashboard, pattern: 'dashboard' },
            { label: 'Map View', route: 'map.index', icon: IconMap, pattern: 'map.*' },
        ],
    },
    {
        label: 'Management',
        items: [
            { label: 'Projects', route: 'projects.index', icon: IconFolder, pattern: 'projects.*' },
            { label: 'Sites', route: 'sites.index', icon: IconBuilding, pattern: 'sites.*' },
            { label: 'Devices', route: 'devices.index', icon: IconRouter, pattern: 'devices.*' },
        ],
    },
    {
        label: 'Monitoring',
        items: [
            { label: 'Daily Ops', route: 'daily-ops.index', icon: IconCalendarCheck, pattern: 'daily-ops.*', permission: 'daily.view' },
            { label: 'Daily Statuses', route: 'daily-statuses.index', icon: IconActivity, pattern: 'daily-statuses.*' },
            { label: 'Accomplishments', route: 'accomplishments.index', icon: IconChecklist, pattern: 'accomplishments.*' },
            { label: 'Tickets', route: 'tickets.index', icon: IconTool, pattern: 'tickets.*', permission: 'tickets.manage' },
        ],
    },
    {
        label: 'Data',
        items: [
            { label: 'Import', route: 'import.index', icon: IconUpload, pattern: 'import.*' },
            { label: 'Reports', route: 'reports.index', icon: IconReportAnalytics, pattern: 'reports.*' },
        ],
    },
]

// Hide entries the user has no permission for.
const visibleGroups = computed(() =>
    navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => can(item.permission)),
        }))
        .filter((group) => group.items.length > 0),
)

watch(sidebarOpen, (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
})
</script>

<template>
  <div class="flex min-h-screen bg-slate-50">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-[600] focus:top-2 focus:left-2 focus:bg-white focus:text-blue-700 focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg">
      Skip to content
    </a>
    <!-- Desktop Sidebar -->
    <aside class="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:w-sidebar bg-gradient-to-b from-blue-800 to-blue-900 text-white z-30" aria-label="Primary navigation">
      <!-- Logo -->
      <div class="px-4 py-5 border-b border-blue-700/50">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center shrink-0">
            <span class="text-blue-800 font-extrabold text-sm">FW</span>
          </div>
          <div>
            <div class="font-bold text-white text-sm leading-tight">FreeWiFi Monitor</div>
            <div class="text-[11px] text-blue-300 leading-tight">Device Operations</div>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <div v-for="group in visibleGroups" :key="group.label">
          <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-blue-300/60">
            {{ group.label }}
          </div>
          <div class="space-y-1">
            <Link
              v-for="item in group.items"
              :key="item.route"
              :href="route(item.route)"
              :class="[
                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150',
                route().current(item.pattern)
                  ? 'bg-blue-700/50 text-white'
                  : 'text-blue-100 hover:bg-blue-700/30 hover:text-white',
              ]"
            >
              <component :is="item.icon" class="w-5 h-5 shrink-0" />
              {{ item.label }}
            </Link>
          </div>
        </div>
      </nav>

      <!-- User Section -->
      <div class="border-t border-blue-700/50 p-4">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold shrink-0">
            {{ $page.props.auth.user.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="min-w-0">
            <div class="text-sm font-medium text-white truncate">{{ $page.props.auth.user.name }}</div>
            <div class="text-xs text-blue-300 truncate">{{ $page.props.auth.user.email }}</div>
          </div>
        </div>
        <div class="flex gap-2">
          <Link :href="route('profile.edit')" class="flex-1 flex items-center justify-center gap-1.5 text-xs text-blue-200 hover:text-white py-1.5 rounded hover:bg-blue-700/30 transition">
            <IconUser class="w-3.5 h-3.5" /> Profile
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="flex-1 flex items-center justify-center gap-1.5 text-xs text-blue-200 hover:text-white py-1.5 rounded hover:bg-blue-700/30 transition">
            <IconLogout class="w-3.5 h-3.5" /> Log Out
          </Link>
        </div>
      </div>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div v-if="sidebarOpen" class="fixed inset-0 z-[500] lg:hidden">
      <div class="fixed inset-0 bg-black/50" aria-hidden="true" @click="sidebarOpen = false"></div>
      <div class="fixed inset-y-0 left-0 w-sidebar bg-gradient-to-b from-blue-800 to-blue-900 text-white flex flex-col" role="dialog" aria-modal="true" aria-label="Navigation menu">
        <!-- Mobile Logo + Close -->
        <div class="px-4 py-5 border-b border-blue-700/50 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center shrink-0">
              <span class="text-blue-800 font-extrabold text-sm">D</span>
            </div>
            <div>
              <div class="font-bold text-white text-sm leading-tight">FreeWiFi Monitor</div>
              <div class="text-[11px] text-blue-300 leading-tight">Device Operations</div>
            </div>
          </div>
          <button class="text-blue-200 hover:text-white" aria-label="Close navigation menu" @click="sidebarOpen = false">
            <IconX class="w-6 h-6" />
          </button>
        </div>

        <!-- Mobile Nav -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
          <div v-for="group in visibleGroups" :key="group.label">
            <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-blue-300/60">
              {{ group.label }}
            </div>
            <div class="space-y-1">
              <Link
                v-for="item in group.items"
                :key="item.route"
                :href="route(item.route)"
                :class="[
                  'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150',
                  route().current(item.pattern)
                    ? 'bg-blue-700/50 text-white'
                    : 'text-blue-100 hover:bg-blue-700/30 hover:text-white',
                ]"
                @click="sidebarOpen = false"
              >
                <component :is="item.icon" class="w-5 h-5 shrink-0" />
                {{ item.label }}
              </Link>
            </div>
          </div>
        </nav>

        <!-- Mobile User Section -->
        <div class="border-t border-blue-700/50 p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold shrink-0">
              {{ $page.props.auth.user.name?.charAt(0)?.toUpperCase() }}
            </div>
            <div class="min-w-0">
              <div class="text-sm font-medium text-white truncate">{{ $page.props.auth.user.name }}</div>
              <div class="text-xs text-blue-300 truncate">{{ $page.props.auth.user.email }}</div>
            </div>
          </div>
          <div class="flex gap-2">
            <Link :href="route('profile.edit')" class="flex-1 flex items-center justify-center gap-1.5 text-xs text-blue-200 hover:text-white py-1.5 rounded hover:bg-blue-700/30 transition" @click="sidebarOpen = false">
              <IconUser class="w-3.5 h-3.5" /> Profile
            </Link>
            <Link :href="route('logout')" method="post" as="button" class="flex-1 flex items-center justify-center gap-1.5 text-xs text-blue-200 hover:text-white py-1.5 rounded hover:bg-blue-700/30 transition">
              <IconLogout class="w-3.5 h-3.5" /> Log Out
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Area -->
    <div class="flex-1 lg:ml-sidebar">
      <!-- Top Bar -->
      <header class="sticky top-0 z-20 bg-white/80 backdrop-blur border-b border-slate-200 h-16 flex items-center px-4 sm:px-6">
        <button class="lg:hidden mr-3 text-slate-500 hover:text-slate-700" aria-label="Open navigation menu" :aria-expanded="sidebarOpen" @click="sidebarOpen = true">
          <IconMenu2 class="w-6 h-6" />
        </button>
        <slot name="header"></slot>
      </header>

      <!-- Page Content -->
      <main id="main-content" class="p-4 sm:p-6">
        <slot></slot>
      </main>
    </div>

    <ToastStack />
  </div>
</template>
