<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { IconKey, IconTrash } from '@tabler/icons-vue';

const props = defineProps({
    tokens: Array,
    plainTextToken: String,
});

const form = useForm({ name: '' });
const copied = ref(false);

function create() {
    form.post(route('probe-tokens.store'), { onSuccess: () => form.reset() });
}

function revoke(id) {
    useForm({}).delete(route('probe-tokens.destroy', id), { preserveScroll: true });
}

async function copyToken() {
    await navigator.clipboard.writeText(props.plainTextToken);
    copied.value = true;
    setTimeout(() => (copied.value = false), 3000);
}
</script>

<template>
  <section class="space-y-4">
    <header>
      <h2 class="text-lg font-bold tracking-tight text-slate-900">Field Probe Tokens</h2>
      <p class="mt-1 text-sm text-slate-600">
        Bearer tokens for automated site-status probes calling
        <code class="text-xs bg-slate-100 rounded px-1.5 py-0.5">POST /api/heartbeat</code>.
        Create one per device or deployment script.
      </p>
    </header>

    <!-- One-time plaintext -->
    <div v-if="plainTextToken" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
      <p class="text-sm font-medium text-emerald-800 mb-2">Token created — copy it now, it will not be shown again:</p>
      <div class="flex items-center gap-2">
        <code class="flex-1 text-xs bg-white border border-emerald-200 rounded px-3 py-2 font-mono break-all">{{ plainTextToken }}</code>
        <button
          type="button" class="px-3 py-2 text-xs font-medium rounded-lg bg-emerald-700 text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600/40 focus-visible:ring-offset-2 active:scale-[0.98] transition"
          @click="copyToken"
        >
          {{ copied ? 'Copied!' : 'Copy' }}
        </button>
      </div>
    </div>

    <form class="flex items-end gap-2 max-w-xl" @submit.prevent="create">
      <div class="flex-1">
        <label for="token-name" class="block text-sm font-medium text-slate-700 mb-1">Token name</label>
        <input
          id="token-name" v-model="form.name" type="text" placeholder="e.g. Barangay Hall probe"
          class="w-full rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40"
        />
        <InputError :message="form.errors.name" class="mt-1" />
      </div>
      <button
        type="submit" :disabled="form.processing"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-accent-500 text-white hover:bg-accent-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40 focus-visible:ring-offset-2 active:scale-[0.98] disabled:opacity-60 transition"
      >
        <IconKey class="w-4 h-4" /> Create token
      </button>
    </form>

    <ul v-if="tokens?.length" class="divide-y divide-slate-100 max-w-xl rounded-lg border border-slate-200">
      <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between px-4 py-2.5">
        <div>
          <div class="text-sm font-medium text-slate-700">{{ token.name }}</div>
          <div class="text-xs text-slate-400">created {{ new Date(token.created_at).toLocaleDateString() }}</div>
        </div>
        <button
          type="button" class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800 hover:underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40 rounded transition-colors"
          @click="revoke(token.id)"
        >
          <IconTrash class="w-3.5 h-3.5" /> Revoke
        </button>
      </li>
    </ul>
    <p v-else class="text-sm text-slate-400">No probe tokens yet.</p>
  </section>
</template>
