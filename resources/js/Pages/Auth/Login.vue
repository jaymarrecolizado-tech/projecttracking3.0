<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
  canResetPassword: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  });
};

</script>

<template>
  <Head title="Sign in" />

  <div class="min-h-screen w-full lg:grid lg:grid-cols-2">
    <!-- ═══ Identity panel ═══ -->
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
        <div class="flex items-center gap-3.5">
          <div class="w-11 h-11 bg-white rounded-lg flex items-center justify-center">
            <span class="text-ink font-extrabold text-base tracking-tight">FW</span>
          </div>
          <div>
            <div class="font-bold text-[15px] leading-tight tracking-tight">Free Public Internet Access Program <span class="text-slate-400">(FPIAP)</span></div>
            <div class="text-[11px] text-slate-400 uppercase tracking-[0.14em] mt-0.5">FreeWiFi · Device Operations</div>
          </div>
        </div>
      </div>

      <!-- Mission + live ledger -->
      <div class="relative max-w-xl">
        <h1 class="text-[2.6rem] leading-[1.12] font-bold tracking-tight text-white">
          Every public WiFi site,<br />
          <span class="text-slate-400">accounted for — every day.</span>
        </h1>
        <p class="mt-5 text-[15px] leading-relaxed text-slate-400 max-w-md">
          The operations console for the FPIAP FreeWiFi program: daily site uptime,
          field equipment, and reports for every barangay we connect.
        </p>
      </div>

      <!-- Program footer -->
      <div class="relative">
        <div class="border-t border-white/10 pt-5 text-xs text-slate-500 flex items-center justify-between gap-4">
          <span>Department of Information and Communications Technology</span>
          <span class="hidden lg:inline text-right">Free Public Internet Access Program · FreeWiFi · Broadband ng Masa</span>
        </div>
      </div>
    </div>

    <!-- ═══ Sign-in panel ═══ -->
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

        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Operations console</h2>
        <p class="mt-2 text-sm text-slate-500">Sign in with your DICT account to continue.</p>

        <div v-if="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
          {{ status }}
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
          <div>
            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Email</label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              autofocus
              autocomplete="username"
              class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-none focus:border-accent-500 focus:ring-accent-500/20"
            />
            <InputError class="mt-2" :message="form.errors.email" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Password</label>
              <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="text-xs font-medium text-accent-500 hover:text-accent-600 hover:underline"
              >
                Forgot password?
              </Link>
            </div>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              autocomplete="current-password"
              class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-none focus:border-accent-500 focus:ring-accent-500/20"
            />
            <InputError class="mt-2" :message="form.errors.password" />
          </div>

          <label class="flex items-center gap-2.5 text-sm text-slate-600">
            <Checkbox v-model:checked="form.remember" name="remember" />
            Keep me signed in on this device
          </label>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full inline-flex justify-center items-center rounded-lg bg-accent-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-accent-600 focus:outline-none focus:ring-2 focus:ring-accent-500/40 focus:ring-offset-2 disabled:opacity-60 transition-colors"
          >
            {{ form.processing ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="mt-10 text-[11px] leading-relaxed text-slate-400 border-t border-slate-200 pt-4">
          Authorized DICT personnel only. All sign-ins and actions are logged and subject to audit.
        </p>
      </div>
    </div>
  </div>
</template>
