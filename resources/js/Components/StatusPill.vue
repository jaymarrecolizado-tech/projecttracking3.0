<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    size: { type: String, default: 'sm' },
})

// Semantic signal colors — dot + label, never filled blobs.
// One status language for the whole app: registries, boards, jobs, tickets.
// Hues: green = good/done, red = down/attention, amber = in-flight/warning,
// blue = informational states only, slate = neutral/archived.
const map = {
    UP: { dot: 'bg-green-500', text: 'text-green-700' },
    DOWN: { dot: 'bg-red-500', text: 'text-red-700' },
    DOWN_SERVER: { dot: 'bg-red-400', text: 'text-red-700' },
    NO_NMS: { dot: 'bg-amber-400', text: 'text-amber-700' },
    NO_DATA: { dot: 'bg-slate-400', text: 'text-slate-500' },
    active: { dot: 'bg-green-500', text: 'text-green-700' },
    Active: { dot: 'bg-green-500', text: 'text-green-700' },
    inactive: { dot: 'bg-red-400', text: 'text-red-700' },
    Inactive: { dot: 'bg-slate-400', text: 'text-slate-500' },
    Deactivated: { dot: 'bg-slate-400', text: 'text-slate-500' },
    planned: { dot: 'bg-amber-400', text: 'text-amber-700' },
    maintenance: { dot: 'bg-blue-400', text: 'text-blue-700' },
    decommissioned: { dot: 'bg-slate-400', text: 'text-slate-500' },
    deployed: { dot: 'bg-green-500', text: 'text-green-700' },
    in_stock: { dot: 'bg-blue-400', text: 'text-blue-700' },
    under_repair: { dot: 'bg-amber-400', text: 'text-amber-700' },
    retired: { dot: 'bg-slate-400', text: 'text-slate-500' },
    lost: { dot: 'bg-red-500', text: 'text-red-700' },
    OPEN: { dot: 'bg-red-500', text: 'text-red-700' },
    IN_PROGRESS: { dot: 'bg-blue-400', text: 'text-blue-700' },
    RESOLVED: { dot: 'bg-green-500', text: 'text-green-700' },
    CLOSED: { dot: 'bg-slate-400', text: 'text-slate-500' },
    COMPLETED: { dot: 'bg-green-500', text: 'text-green-700' },
    NOT_STARTED: { dot: 'bg-slate-400', text: 'text-slate-500' },
    ON_HOLD: { dot: 'bg-amber-400', text: 'text-amber-700' },
    CANCELLED: { dot: 'bg-red-500', text: 'text-red-700' },
    DRAFT: { dot: 'bg-amber-400', text: 'text-amber-700' },
    SUBMITTED: { dot: 'bg-amber-400', text: 'text-amber-700' },
    APPROVED: { dot: 'bg-green-500', text: 'text-green-700' },
    LOCKED: { dot: 'bg-slate-400', text: 'text-slate-500' },
    PENDING: { dot: 'bg-amber-400', text: 'text-amber-700' },
    PROCESSING: { dot: 'bg-amber-400', text: 'text-amber-700' },
    DONE: { dot: 'bg-green-500', text: 'text-green-700' },
    FAILED: { dot: 'bg-red-500', text: 'text-red-700' },
    low: { dot: 'bg-slate-400', text: 'text-slate-600' },
    medium: { dot: 'bg-blue-400', text: 'text-blue-700' },
    high: { dot: 'bg-amber-400', text: 'text-amber-700' },
    critical: { dot: 'bg-red-500', text: 'text-red-700' },
}

const style = computed(() => map[props.status] ?? { dot: 'bg-slate-400', text: 'text-slate-500' })
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5"
    :class="[style.text, size === 'sm' ? 'text-xs' : 'text-sm']"
    role="status"
    :aria-label="`Status: ${status.replace('_', ' ')}`"
  >
    <span
      class="rounded-full shrink-0"
      :class="[style.dot, size === 'sm' ? 'w-1.5 h-1.5' : 'w-2 h-2']"
      aria-hidden="true"
    ></span>
    <span class="font-medium">{{ status.replace('_', ' ') }}</span>
  </span>
</template>
