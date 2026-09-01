<script setup>
// Shared table chrome for registry listings (Sites, Devices, DailyGrid).
// Slots: header (title/actions row), caption (sr-only table description),
// head (the <th> cells), default (body rows), footer (empty state/pagination).
</script>

<template>
  <div class="dict-card overflow-hidden">
    <div v-if="$slots.header" class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
      <slot name="header" ></slot>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <caption v-if="$slots.caption" class="sr-only">
          <slot name="caption" ></slot>
        </caption>
        <thead>
          <tr class="dict-table-header">
            <slot name="head" ></slot>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <slot ></slot>
        </tbody>
      </table>
    </div>
    <slot name="footer" ></slot>
  </div>
</template>
