<script setup>
import { computed } from 'vue'

// Single severity language for alert chips, light and dark.
// Severity is data, never decoration: critical red, warning amber, info blue.
const props = defineProps({
    severity: { type: String, required: true },
    dark: { type: Boolean, default: false },
})

const lightStyles = {
    critical: 'border-red-200 bg-red-50 text-red-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-700',
    info: 'border-blue-200 bg-blue-50 text-blue-700',
}
const darkStyles = {
    critical: 'border-red-900/50 bg-red-950/30 text-red-400',
    warning: 'border-amber-900/50 bg-amber-950/30 text-amber-400',
    info: 'border-slate-700 bg-slate-900/40 text-blue-400',
}

const style = computed(
    () => (props.dark ? darkStyles : lightStyles)[props.severity] ?? (props.dark ? darkStyles.info : lightStyles.info),
)
</script>

<template>
  <span
    class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide"
    :class="style"
    role="status"
    :aria-label="`Severity: ${severity}`"
  >{{ severity }}</span>
</template>
