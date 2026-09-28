<script setup>
import PublicSurveyLayout from '@/Layouts/PublicSurveyLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    siteName: { type: String, default: null },
});
</script>

<template>
  <Head title="Thank you" />

  <PublicSurveyLayout :site-name="null">
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
      <div class="mx-auto w-12 h-12 rounded-full bg-accent-50 flex items-center justify-center">
        <svg class="w-6 h-6 text-accent-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20 6L9 17l-5-5" />
        </svg>
      </div>
      <h1 class="mt-4 text-[17px] font-bold text-slate-900">Thank you</h1>
      <p class="mt-2 text-[13px] text-slate-500 leading-relaxed">
        Your feedback helps us improve Free WiFi in your area.
        <span v-if="siteName">It has been recorded for <span class="font-medium text-slate-700">{{ siteName }}</span>.</span>
      </p>
      <p class="mt-4 text-[12px] text-slate-400">You may now close this page and continue browsing.</p>
    </div>
  </PublicSurveyLayout>
</template>
