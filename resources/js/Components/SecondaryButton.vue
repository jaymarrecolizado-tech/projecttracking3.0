<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
  <button
    :type="type"
    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition duration-150 ease-in-out hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent-500/40 focus:ring-offset-2 active:scale-[0.98] disabled:opacity-50"
  >
    <slot></slot>
  </button>
</template>
