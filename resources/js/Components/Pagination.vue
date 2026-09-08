<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    links: { type: Array, default: () => [] },
})

// Laravel's paginator labels are '« Previous', page numbers, 'Next »' —
// render them as plain text so nothing from the payload is ever interpolated
// as markup.
const cleaned = computed(() => (props.links ?? [])
    .filter((l) => l.url !== null || l.active || l.label.includes('Previous') || l.label.includes('Next'))
    .map((l) => ({ ...l, text: String(l.label).replace(/&laquo;\s*|&raquo;|«|»/g, '').trim() || '…' })))
</script>

<template>
  <nav v-if="cleaned.length > 1" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
    <template v-for="(link, i) in cleaned" :key="i">
      <Link
        v-if="link.url"
        :href="link.url"
        preserve-scroll
        class="min-w-[2.25rem] text-center px-2.5 py-1.5 text-sm rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40"
        :class="link.active
          ? 'bg-accent-500 text-white font-medium'
          : 'text-slate-600 hover:bg-slate-100'"
        :aria-current="link.active ? 'page' : undefined"
      >
        {{ link.text }}
      </Link>
      <span
        v-else
        class="min-w-[2.25rem] text-center px-2.5 py-1.5 text-sm rounded-lg text-slate-300"
        :aria-current="link.active ? 'page' : undefined"
      >{{ link.text }}</span>
    </template>
  </nav>
</template>
