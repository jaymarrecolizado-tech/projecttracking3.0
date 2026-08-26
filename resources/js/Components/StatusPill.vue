<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
    size: { type: String, default: 'sm' },
})

// Semantic signal colors — dot + label, never filled blobs.
const map = {
    UP: { dot: 'bg-green-500', text: 'text-green-700' },
    DOWN: { dot: 'bg-red-500', text: 'text-red-700' },
    DOWN_SERVER: { dot: 'bg-red-400', text: 'text-red-700' },
    NO_NMS: { dot: 'bg-amber-400', text: 'text-amber-700' },
    NO_DATA: { dot: 'bg-slate-400', text: 'text-slate-500' },
    active: { dot: 'bg-green-500', text: 'text-green-700' },
    inactive: { dot: 'bg-red-400', text: 'text-red-700' },
    planned: { dot: 'bg-amber-400', text: 'text-amber-700' },
    maintenance: { dot: 'bg-blue-400', text: 'text-blue-700' },
    decommissioned: { dot: 'bg-slate-400', text: 'text-slate-500' },
}

const style = computed(() => map[props.status] ?? { dot: 'bg-slate-400', text: 'text-slate-500' })
</script>

<template>
  <span class="inline-flex items-center gap-1.5" :class="[style.text, size === 'sm' ? 'text-xs' : 'text-sm']">
    <span class="rounded-full shrink-0" :class="[style.dot, size === 'sm' ? 'w-1.5 h-1.5' : 'w-2 h-2']"></span>
    <span class="font-medium">{{ status.replace('_', ' ') }}</span>
  </span>
</template>
