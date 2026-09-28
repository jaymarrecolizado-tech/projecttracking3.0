<script setup>
/**
 * Public respondent layout. Deliberately separate from GuestLayout, which is
 * the staff sign-in shell and says "Authorized DICT personnel only" — a
 * member of the public must not land on a page that tells them that.
 *
 * Mobile-first: respondents are on a phone, on a captive portal, one-handed.
 */
defineProps({
    siteName: { type: String, default: null },
});
</script>

<template>
  <div class="min-h-screen bg-[#FAFAF8] flex flex-col">
    <header class="w-full border-b border-slate-200 bg-white">
      <div class="mx-auto max-w-lg px-5 py-4 flex items-center gap-3">
        <div class="w-9 h-9 bg-ink rounded-lg flex items-center justify-center shrink-0">
          <span class="text-white font-extrabold text-xs tracking-tight">FW</span>
        </div>
        <div class="min-w-0">
          <div class="font-bold text-slate-900 text-[13px] leading-tight">Free Public Internet Access Program</div>
          <div class="text-[10px] text-slate-500 uppercase tracking-[0.14em]">FPIAP · FreeWiFi</div>
        </div>
      </div>
    </header>

    <main class="flex-1 w-full">
      <div class="mx-auto max-w-lg px-5 py-7">
        <div v-if="siteName" class="mb-6">
          <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">You are connected at</div>
          <div class="text-lg font-bold text-slate-900 leading-snug">{{ siteName }}</div>
        </div>

        <slot></slot>
      </div>
    </main>

    <footer class="w-full border-t border-slate-200 bg-white mt-8">
      <div class="mx-auto max-w-lg px-5 py-5 text-[11px] leading-relaxed text-slate-400">
        Your answers are anonymous — we do not ask for your name or contact details.
        Department of Information and Communications Technology · Broadband ng Masa
      </div>
    </footer>
  </div>
</template>
