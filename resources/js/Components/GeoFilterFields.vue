<script setup>
// Cascading geo filter selects shared by Map View and the Reports page
// (Plan §Map 5). Changing a parent clears its children and emits the patch.
const props = defineProps({
    projects: { type: Array, default: () => [] },
    siteTypes: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({ provinces: [], districts: [], municipalities: [], barangays: [] }) },
    filters: { type: Object, default: () => ({}) },
    showProject: { type: Boolean, default: true },
    showSiteType: { type: Boolean, default: true },
});

const emit = defineEmits(['update:filters']);

function set(patch) {
    // Clearing a parent invalidates its children.
    const next = { ...props.filters, ...patch };
    if (patch.province !== undefined) {
        next.district = '';
        next.municipality = '';
        next.barangay = '';
    }
    if (patch.district !== undefined) {
        next.municipality = '';
        next.barangay = '';
    }
    if (patch.municipality !== undefined) {
        next.barangay = '';
    }
    emit('update:filters', next);
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-3">
    <div v-if="showProject" class="flex-1 min-w-[180px]">
      <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Project</label>
      <select
        :value="filters.project_id ?? ''"
        class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
        @change="set({ project_id: $event.target.value })"
      >
        <option value="">All Projects</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div class="flex-1 min-w-[150px]">
      <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Province</label>
      <select
        :value="filters.province ?? ''"
        class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
        @change="set({ province: $event.target.value })"
      >
        <option value="">All Provinces</option>
        <option v-for="province in options.provinces" :key="province" :value="province">{{ province }}</option>
      </select>
    </div>

    <div class="flex-1 min-w-[150px]">
      <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">District</label>
      <select
        :value="filters.district ?? ''"
        :disabled="!filters.province || !options.districts?.length"
        class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400"
        @change="set({ district: $event.target.value })"
      >
        <option value="">All Districts</option>
        <option v-for="district in options.districts" :key="district" :value="district">{{ district }}</option>
      </select>
    </div>

    <div class="flex-1 min-w-[170px]">
      <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Municipality</label>
      <select
        :value="filters.municipality ?? ''"
        :disabled="!filters.province"
        class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400"
        @change="set({ municipality: $event.target.value })"
      >
        <option value="">All Municipalities</option>
        <option v-for="municipality in options.municipalities" :key="municipality" :value="municipality">{{ municipality }}</option>
      </select>
    </div>

    <div class="flex-1 min-w-[170px]">
      <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Barangay</label>
      <select
        :value="filters.barangay ?? ''"
        :disabled="!filters.municipality"
        class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400"
        @change="set({ barangay: $event.target.value })"
      >
        <option value="">All Barangays</option>
        <option v-for="barangay in options.barangays" :key="barangay" :value="barangay">{{ barangay }}</option>
      </select>
    </div>

    <div v-if="showSiteType" class="flex-1 min-w-[180px]">
      <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Site Type</label>
      <select
        :value="filters.site_type ?? ''"
        class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
        @change="set({ site_type: $event.target.value })"
      >
        <option value="">All Types</option>
        <option v-for="type in siteTypes" :key="type.code" :value="type.code">{{ type.label }}</option>
      </select>
    </div>

    <slot></slot>
  </div>
</template>
