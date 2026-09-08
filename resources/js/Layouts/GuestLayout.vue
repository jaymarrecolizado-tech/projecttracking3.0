<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen w-full lg:grid lg:grid-cols-2">
    <!-- ═══ Identity panel (compact mirror of Login) ═══ -->
    <div class="relative hidden lg:flex min-h-screen flex-col justify-between overflow-hidden bg-ink text-white p-12">
      <!-- Blueprint grid -->
      <div
        class="absolute inset-0 pointer-events-none" :style="{
          backgroundImage: 'linear-gradient(rgba(255,255,255,0.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.045) 1px, transparent 1px)',
          backgroundSize: '36px 36px',
        }" aria-hidden="true"
      ></div>

      <!-- Wordmark -->
      <div class="relative">
        <Link :href="route('login')" class="flex items-center gap-3.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40 rounded-lg">
          <div class="w-11 h-11 bg-white rounded-lg flex items-center justify-center">
            <span class="text-ink font-extrabold text-base tracking-tight">FW</span>
          </div>
          <div>
            <div class="font-bold text-[15px] leading-tight tracking-tight">Free Public Internet Access Program <span class="text-slate-400">(FPIAP)</span></div>
            <div class="text-[11px] text-slate-400 uppercase tracking-[0.14em] mt-0.5">FreeWiFi · Device Operations</div>
          </div>
        </Link>
      </div>

      <!-- Mission -->
      <div class="relative max-w-xl">
        <h1 class="text-[2.6rem] leading-[1.12] font-bold tracking-tight text-white">
          Secure sign-in<br />
          <span class="text-slate-400">for field operations.</span>
        </h1>
        <p class="mt-5 text-[15px] leading-relaxed text-slate-400 max-w-md">
          Authorized DICT personnel only. Every sign-in is logged and subject to audit.
        </p>
      </div>

      <!-- Program footer -->
      <div class="relative border-t border-white/10 pt-5 text-xs text-slate-500 flex items-center justify-between gap-4">
        <span>Department of Information and Communications Technology</span>
        <span class="hidden xl:inline text-right">Free Public Internet Access Program · Broadband ng Masa</span>
      </div>
    </div>

    <!-- ═══ Form panel ═══ -->
    <div class="flex min-h-screen items-center justify-center bg-[#FAFAF8] px-6 py-12 sm:px-12">
      <div class="w-full max-w-sm">
        <!-- Compact brand for mobile -->
        <div class="lg:hidden flex items-center gap-3 mb-10">
          <div class="w-10 h-10 bg-ink rounded-lg flex items-center justify-center">
            <span class="text-white font-extrabold text-sm">FW</span>
          </div>
          <div>
            <div class="font-bold text-slate-900 text-sm leading-tight">Free Public Internet Access Program</div>
            <div class="text-[11px] text-slate-500 uppercase tracking-[0.14em]">FPIAP · FreeWiFi · Device Operations</div>
          </div>
        </div>

        <slot></slot>

        <p class="mt-10 text-[11px] leading-relaxed text-slate-400 border-t border-slate-200 pt-4">
          Authorized DICT personnel only. All sign-ins and actions are logged and subject to audit.
        </p>
      </div>
    </div>
  </div>
</template>
