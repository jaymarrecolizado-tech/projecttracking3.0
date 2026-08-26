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
      <h2 class="text-lg font-medium text-gray-900">Field Probe Tokens</h2>
      <p class="mt-1 text-sm text-gray-600">
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
        <button type="button" @click="copyToken"
          class="px-3 py-2 text-xs font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">
          {{ copied ? 'Copied!' : 'Copy' }}
        </button>
      </div>
    </div>

    <form @submit.prevent="create" class="flex items-end gap-2 max-w-xl">
      <div class="flex-1">
        <label for="token-name" class="block text-sm font-medium text-gray-700 mb-1">Token name</label>
        <input id="token-name" v-model="form.name" type="text" placeholder="e.g. Barangay Hall probe"
          class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-200" />
        <InputError :message="form.errors.name" class="mt-1" />
      </div>
      <button type="submit" :disabled="form.processing"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60 transition">
        <IconKey class="w-4 h-4" /> Create token
      </button>
    </form>

    <ul v-if="tokens?.length" class="divide-y divide-gray-100 max-w-xl rounded-lg border border-gray-200">
      <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between px-4 py-2.5">
        <div>
          <div class="text-sm font-medium text-gray-700">{{ token.name }}</div>
          <div class="text-xs text-gray-400">created {{ new Date(token.created_at).toLocaleDateString() }}</div>
        </div>
        <button type="button" @click="revoke(token.id)"
          class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-800">
          <IconTrash class="w-3.5 h-3.5" /> Revoke
        </button>
      </li>
    </ul>
    <p v-else class="text-sm text-gray-400">No probe tokens yet.</p>
  </section>
</template>
