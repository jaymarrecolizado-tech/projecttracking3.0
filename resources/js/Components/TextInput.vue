<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
  <input
    ref="input"
    v-model="model"
    class="rounded-lg border-slate-300 focus:border-accent-500 focus:ring-accent-500/40"
  />
</template>
