<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { IconCircleCheck, IconCircleX, IconX } from '@tabler/icons-vue'

const page = usePage()
const toasts = ref([])
let seq = 0

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (success) push(success, 'success')
        if (error) push(error, 'error')
    },
    { immediate: true },
)

function push(message, type) {
    const toast = { id: ++seq, message, type }
    toasts.value.push(toast)
    setTimeout(() => dismiss(toast.id), 6000)
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
}
</script>

<template>
  <div class="fixed bottom-4 right-4 z-[1000] flex flex-col gap-2 w-80 max-w-[calc(100vw-2rem)]" aria-live="polite">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="flex items-start gap-2.5 rounded-lg border p-3 shadow-lg bg-white"
        :class="toast.type === 'error' ? 'border-red-200' : 'border-emerald-200'"
        role="status"
      >
        <IconCircleCheck v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600 shrink-0 mt-px" />
        <IconCircleX v-else class="w-5 h-5 text-red-600 shrink-0 mt-px" />
        <p class="text-sm text-slate-700 flex-1">{{ toast.message }}</p>
        <button class="text-slate-400 hover:text-slate-600 shrink-0" aria-label="Dismiss notification" @click="dismiss(toast.id)">
          <IconX class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
