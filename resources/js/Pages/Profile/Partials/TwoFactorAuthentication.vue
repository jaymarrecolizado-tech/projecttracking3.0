<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { usePage, useForm } from '@inertiajs/vue3';

defineProps({
    twoFactor: { type: Object, default: () => ({ enabled: false, setup: null }) },
});

const page = usePage();
const canManage = page.props.auth.permissions?.includes('users.manage');

const confirmForm = useForm({ code: '' });
const disableForm = useForm({ code: '' });

const confirm = () => confirmForm.post(route('two-factor.confirm'), { onFinish: () => confirmForm.reset() });
const disable = () => disableForm.delete(route('two-factor.disable'), { onFinish: () => disableForm.reset() });
const start = () => useForm({}).post(route('two-factor.enable'));
</script>

<template>
  <section v-if="canManage" aria-labelledby="two-factor-heading">
    <h2 id="two-factor-heading" class="text-lg font-medium text-gray-900">Two-Factor Authentication</h2>

    <!-- Enabled -->
    <template v-if="twoFactor.enabled">
      <p class="mt-1 text-sm text-gray-600">
        Your account requires a 6-digit code from your authenticator app at every sign-in.
      </p>
      <form class="mt-4 max-w-sm space-y-3" @submit.prevent="disable">
        <p class="text-sm text-gray-500">To turn it off, enter a current code:</p>
        <div>
          <InputLabel for="disable-code" value="Authentication code" />
          <TextInput
            id="disable-code" v-model="disableForm.code" type="text" inputmode="numeric"
            autocomplete="one-time-code" class="mt-1 block w-full font-mono tracking-widest" maxlength="6"
          />
          <InputError class="mt-2" :message="disableForm.errors.code" />
        </div>
        <PrimaryButton type="submit" class="bg-red-600 hover:bg-red-700" :disabled="disableForm.processing">
          Disable two-factor
        </PrimaryButton>
      </form>
    </template>

    <!-- Setup in progress: show secret + QR, confirm with a live code -->
    <template v-else-if="twoFactor.setup">
      <p class="mt-1 text-sm text-gray-600">
        Scan the QR code with your authenticator app (Google Authenticator, Authy, 1Password…).
        Can't scan? Enter the secret manually. Then confirm with the 6-digit code it shows.
      </p>
      <div class="mt-4 flex flex-col sm:flex-row gap-6 items-start">
        <div class="rounded-lg bg-white p-2 border border-slate-200 shrink-0" v-html="twoFactor.setup.qr"></div>
        <div class="min-w-0">
          <p class="text-xs uppercase tracking-wide text-gray-400">Manual entry secret</p>
          <p class="font-mono text-sm font-semibold text-slate-800 break-all">{{ twoFactor.setup.secret }}</p>
          <form class="mt-4 max-w-xs space-y-3" @submit.prevent="confirm">
            <div>
              <InputLabel for="confirm-code" value="Confirm code" />
              <TextInput
                id="confirm-code" v-model="confirmForm.code" type="text" inputmode="numeric"
                autocomplete="one-time-code" class="mt-1 block w-full font-mono tracking-widest" maxlength="6"
              />
              <InputError class="mt-2" :message="confirmForm.errors.code" />
            </div>
            <PrimaryButton :disabled="confirmForm.processing">Confirm and enable</PrimaryButton>
          </form>
          <p class="mt-3 text-xs text-gray-400">
            Codes rotate every 30 seconds — use the code currently shown in your app.
          </p>
        </div>
      </div>
    </template>

    <!-- Not enabled -->
    <template v-else>
      <p class="mt-1 text-sm text-gray-600">
        Add a second factor to your sign-in: a 6-digit code from an authenticator app.
        Recommended for administrator accounts.
      </p>
      <PrimaryButton class="mt-4" @click="start">Enable two-factor authentication</PrimaryButton>
    </template>
  </section>
</template>
