<script setup>
import PublicSurveyLayout from '@/Layouts/PublicSurveyLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Link not recognised" />

  <PublicSurveyLayout :site-name="null">
    <div class="rounded-xl border border-slate-200 bg-white p-6 text-center">
      <h1 class="text-[17px] font-bold text-slate-900">This link is not recognised</h1>
      <p class="mt-2 text-[13px] text-slate-500 leading-relaxed">
        The survey link you followed does not match a Free WiFi site.
        If you scanned a QR code at the site, please ask staff for the current one.
      </p>
    </div>
  </PublicSurveyLayout>
</template>
