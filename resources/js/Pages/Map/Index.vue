<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GeoFilterFields from '@/Components/GeoFilterFields.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import MapStatsPanel from './MapStatsPanel.vue';
import { useLeafletMap } from './useLeafletMap';

const props = defineProps({
    projects: Array,
    siteTypes: Array,
    initialOptions: Object,
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    project_id: props.filters?.project_id ?? '',
    status: props.filters?.status ?? '',
    province: props.filters?.province ?? '',
    district: props.filters?.district ?? '',
    municipality: props.filters?.municipality ?? '',
    barangay: props.filters?.barangay ?? '',
    site_type: props.filters?.site_type ?? '',
    deployed_only: props.filters?.deployed_only ?? '1',
});

const options = ref(props.initialOptions);
const coverage = ref(null);
const busy = ref(false);

const mapContainer = ref(null);
const leaflet = useLeafletMap(mapContainer);

const typeLabel = computed(() =>
    Object.fromEntries((props.siteTypes ?? []).map((t) => [t.code, t.label])));

// Polygon drill level follows the deepest chosen filter: province →
// district → municipality → barangay. `filter` is the key a polygon click
// sets at that tier.
function boundaryScope() {
    if (filters.municipality) {
        return { level: 'barangay', params: { province: filters.province, district: filters.district, municipality: filters.municipality }, selected: filters.barangay, filter: 'barangay' };
    }
    if (filters.district) {
        return { level: 'municipality', params: { province: filters.province, district: filters.district }, selected: filters.municipality, filter: 'municipality' };
    }
    if (filters.province) {
        return { level: 'district', params: { province: filters.province }, selected: filters.district, filter: 'district' };
    }
    return { level: 'province', params: {}, selected: filters.province, filter: 'province' };
}

function apiParams(extra = {}) {
    const params = new URLSearchParams();
    for (const key of ['project_id', 'status', 'province', 'district', 'municipality', 'barangay', 'site_type']) {
        if (filters[key]) {
            params.set(key, filters[key]);
        }
    }
    for (const [key, value] of Object.entries(extra)) {
        params.set(key, value);
    }
    return params;
}

async function fetchJson(url, params) {
    const response = await fetch(`${url}?${params}`);
    return response.json();
}

async function refresh({ syncUrl = false } = {}) {
    busy.value = true;
    try {
        if (syncUrl) {
            router.get(route('map.index'), { ...filters }, { preserveState: true, replace: true });
        }
        const geo = apiParams({ deployed_only: filters.deployed_only });

        const [markerData, boundaryData, coverageData] = await Promise.all([
            fetchJson('/map/geojson', geo),
            fetchJson('/map/boundaries', apiParams({ level: boundaryScope().level, ...boundaryScope().params })),
            fetchJson('/map/coverage', apiParams()),
        ]);

        leaflet.renderMarkers(markerData, { typeLabel: typeLabel.value });

        const scope = boundaryScope();
        leaflet.renderBoundaries(boundaryData, {
            level: scope.level,
            selectedName: scope.selected,
            onPick: (name) => pickBoundary(scope.level, name),
        });
        leaflet.fitToBoundaries();

        coverage.value = coverageData;
    } finally {
        busy.value = false;
    }
}

function pickBoundary(level, name) {
    apply({ [level]: name });
}

function apply(patch, { syncUrl = true } = {}) {
    Object.assign(filters, patch);
    refresh({ syncUrl });
}

async function loadOptionsForParents() {
    const params = new URLSearchParams();
    if (filters.province) {
        params.set('province', filters.province);
    }
    if (filters.district) {
        params.set('district', filters.district);
    }
    if (filters.municipality) {
        params.set('municipality', filters.municipality);
    }
    options.value = await fetchJson('/map/filter-options', params);
}

function onFiltersChange(next) {
    Object.assign(filters, next);
    loadOptionsForParents();
    refresh({ syncUrl: true });
}

function clearFilters() {
    apply({ province: '', district: '', municipality: '', barangay: '', site_type: '', project_id: '' });
    loadOptionsForParents();
}

function toggleDeployedOnly() {
    apply({ deployed_only: filters.deployed_only === '1' ? '' : '1' });
}

function generatePdf() {
    router.post(route('reports.site-type'), {
        project_id: filters.project_id || null,
        province: filters.province || null,
        district: filters.district || null,
        municipality: filters.municipality || null,
        barangay: filters.barangay || null,
    });
}

onMounted(() => {
    leaflet.init();
    setTimeout(() => refresh(), 100);
});

onBeforeUnmount(() => leaflet.destroy());
</script>

<template>
  <Head title="Map View" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">Map View</h2>
    </template>

    <div class="space-y-4">
      <!-- Geo filters (cascading) -->
      <div class="dict-card p-4">
        <GeoFilterFields
          :projects="projects"
          :site-types="siteTypes"
          :options="options"
          :filters="filters"
          @update:filters="onFiltersChange"
        >
          <div class="flex items-center gap-3">
            <select
              :value="filters.status"
              class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
              @change="apply({ status: $event.target.value })"
            >
              <option value="">All Site Statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="planned">Planned</option>
              <option value="decommissioned">Decommissioned</option>
              <option value="maintenance">Maintenance</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600 select-none">
              <input
                type="checkbox"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-200"
                :checked="filters.deployed_only === '1'"
                @change="toggleDeployedOnly"
              />
              Deployed devices
            </label>
            <button
              type="button"
              class="text-sm text-slate-500 hover:text-slate-700 underline"
              @click="clearFilters"
            >
              Clear filters
            </button>
            <span v-if="busy" class="text-xs text-slate-400" aria-live="polite">Updating…</span>
          </div>
        </GeoFilterFields>
      </div>

      <!-- Map -->
      <div ref="mapContainer" class="rounded-lg overflow-hidden shadow-sm border border-slate-200" style="height: 600px;"></div>

      <MapStatsPanel :coverage="coverage" @generate-pdf="generatePdf" />
    </div>
  </AuthenticatedLayout>
</template>
