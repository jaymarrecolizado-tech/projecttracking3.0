<script setup>
import { computed } from 'vue';

// Dependency-free SVG sparkline for device telemetry (docs §Phase 2 charts).
// points: [{ ts, value }] ordered oldest → newest.
const props = defineProps({
    points: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    unit: { type: String, default: '' },
    color: { type: String, default: '#2563eb' },
});

const clean = computed(() => props.points
    .filter((p) => p.value !== null && p.value !== undefined && Number.isFinite(Number(p.value)))
    .map((p) => ({ ...p, value: Number(p.value) })));

const stats = computed(() => {
    const vals = clean.value.map((p) => p.value);
    if (! vals.length) {
        return null;
    }

    return {
        min: Math.min(...vals),
        max: Math.max(...vals),
        avg: vals.reduce((a, b) => a + b, 0) / vals.length,
        latest: vals[vals.length - 1],
        count: vals.length,
    };
});

const geometry = computed(() => {
    if (! stats.value) {
        return null;
    }
    const span = stats.value.max - stats.value.min || 1;
    const step = 100 / Math.max(clean.value.length - 1, 1);

    return clean.value.map((p, i) => ({
        x: i * step,
        y: 100 - ((p.value - stats.value.min) / span) * 90 - 5,
    }));
});

const linePath = computed(() => geometry.value
    ? geometry.value.map((c, i) => `${i ? 'L' : 'M'}${c.x.toFixed(2)},${c.y.toFixed(2)}`).join(' ')
    : '');

const areaPath = computed(() => (linePath.value ? `${linePath.value} L100,100 L0,100 Z` : ''));

const fmt = (n) => (n >= 100 ? Math.round(n) : Number(n.toFixed(1)));
</script>

<template>
  <figure class="min-w-0">
    <figcaption class="flex items-baseline justify-between mb-1">
      <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ label }}</span>
      <span v-if="stats" class="text-sm font-semibold tabular-nums" :style="{ color }">
        {{ fmt(stats.latest) }}{{ unit }}
      </span>
    </figcaption>
    <svg
      v-if="geometry" viewBox="0 0 100 100" preserveAspectRatio="none" class="w-full h-20"
      role="img" :aria-label="`${label} sparkline, ${stats.count} samples`"
    >
      <path :d="areaPath" :fill="color" opacity="0.1" />
      <path :d="linePath" fill="none" :stroke="color" stroke-width="2" vector-effect="non-scaling-stroke" />
    </svg>
    <div v-else class="h-20 flex items-center justify-center text-xs text-slate-400 rounded-lg bg-slate-50">
      No samples
    </div>
    <p v-if="stats" class="mt-1 text-[11px] text-slate-400 tabular-nums">
      min {{ fmt(stats.min) }} · avg {{ fmt(stats.avg) }} · max {{ fmt(stats.max) }}{{ unit }}
    </p>
  </figure>
</template>
