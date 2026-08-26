<script setup>
import { computed } from 'vue'

const props = defineProps({
    links: { type: Array, default: () => [] },
})

// Laravel's paginator ships prev/next with « » labels and null urls.
const visible = computed(() => (props.links ?? []).filter((l) => l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;'))
</script>

<template>
  <nav v-if="links?.length > 1" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
    <template v-for="(link, i) in links" :key="i">
      <component
        :is="link.url ? 'a' : 'span'"
        v-if="link.label.includes('Previous') || link.label.includes('Next')"
        :href="link.url"
        class="px-3 py-1.5 text-sm rounded-lg border transition-colors"
        :class="link.active
          ? 'bg-blue-600 border-blue-600 text-white'
          : link.url
            ? 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
            : 'border-slate-100 bg-white text-slate-300 cursor-not-allowed'"
        @click="link.url && $event.preventDefault()"
        v-html="link.label"
      />
      <span
        v-else-if="visible.length <= 7 || !isNaN(link.label)"
        class="min-w-[2.25rem] text-center px-2 py-1.5 text-sm rounded-lg"
        :class="link.active
          ? 'bg-blue-600 text-white font-medium'
          : link.url
            ? 'text-slate-600 hover:bg-slate-100 cursor-pointer'
            : 'text-slate-400'"
        v-html="link.label"
      ></span>
    </template>
  </nav>
</template>
