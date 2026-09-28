<script setup>
import PublicSurveyLayout from '@/Layouts/PublicSurveyLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Survey unavailable" />

  <PublicSurveyLayout :site-name="null">
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
      <h1 class="text-[17px] font-bold text-slate-900">The survey is not available right now</h1>
      <p class="mt-2 text-[13px] text-slate-500 leading-relaxed">
        Please try again later. We are not collecting feedback at the moment.
      </p>
    </div>
  </PublicSurveyLayout>
</template>
