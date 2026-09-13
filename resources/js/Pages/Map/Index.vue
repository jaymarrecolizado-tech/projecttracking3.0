<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GeoFilterFields from '@/Components/GeoFilterFields.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import MapStatsPanel from './MapStatsPanel.vue';
import { useLeafletMap, healthBucket } from './useLeafletMap';
import { STATUS_COLORS } from '../../theme';

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
    deployed_only: props.filters?.deployed_only ?? '',
});

const options = ref(props.initialOptions);
const coverage = ref(null);
const plotted = ref(0);
const truncated = ref(false);
const busy = ref(false);
const loadError = ref('');

// Legend + slider state (Slice 8): raw fetch cache, client-side health
// filter, cluster merge radius, live zoom.
const markerData = ref(null);
const health = ref(null);
const clusterRadius = ref(80);
const zoom = ref(7);

const mapContainer = ref(null);
const leaflet = useLeafletMap(mapContainer);

const typeLabel = computed(() =>
    Object.fromEntries((props.siteTypes ?? []).map((t) => [t.code, t.label])));

// Live legend chips counted from the fetched GeoJSON — no new endpoint.
// NOT LOCATED adds the unplotted remainder so the four chips reconcile
// with the stats panel's registered total.
const healthCounts = computed(() => {
    const counts = { UP: 0, DOWN: 0, NO_NMS: 0, NO_DATA: 0 };
    for (const f of markerData.value?.features ?? []) {
        counts[healthBucket(f.properties?.daily_status)]++;
    }
    return counts;
});
const fetchedTotal = computed(() => markerData.value?.features?.length ?? 0);
const unplotted = computed(() => filters.deployed_only === '1'
    ? 0
    : Math.max(0, (coverage.value?.totals?.registered ?? 0) - fetchedTotal.value));
const chips = computed(() => ([
    { key: 'UP', label: 'Online', color: STATUS_COLORS.UP, count: healthCounts.value.UP },
    { key: 'DOWN', label: 'Offline', color: STATUS_COLORS.DOWN, count: healthCounts.value.DOWN },
    { key: 'NO_NMS', label: 'Unmonitored', color: STATUS_COLORS.NO_NMS, count: healthCounts.value.NO_NMS },
    { key: 'NO_DATA', label: 'Not located', color: STATUS_COLORS.NO_DATA, count: healthCounts.value.NO_DATA + unplotted.value },
]));

// One draw path for fetch, chip filter, and slider — no refetch.
function drawMarkers() {
    const features = markerData.value?.features ?? [];
    const shown = health.value
        ? features.filter((f) => healthBucket(f.properties?.daily_status) === health.value)
        : features;
    leaflet.renderMarkers({ type: 'FeatureCollection', features: shown }, {
        typeLabel: typeLabel.value,
        maxClusterRadius: clusterRadius.value,
    });
    plotted.value = shown.length;
}

function toggleHealth(key) {
    health.value = health.value === key ? null : key;
    drawMarkers();
}

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
    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }
    return response.json();
}

async function refresh({ syncUrl = false } = {}) {
    busy.value = true;
    loadError.value = '';
    try {
        if (syncUrl) {
            router.get(route('map.index'), { ...filters }, { preserveState: true, replace: true });
        }
        const geo = apiParams({ deployed_only: filters.deployed_only });

        const [markerJson, boundaryData, coverageData] = await Promise.all([
            fetchJson('/map/geojson', geo),
            fetchJson('/map/boundaries', apiParams({ level: boundaryScope().level, ...boundaryScope().params })),
            fetchJson('/map/coverage', apiParams()),
        ]);

        truncated.value = markerJson.truncated === true;
        markerData.value = markerJson;
        drawMarkers();

        const scope = boundaryScope();
        leaflet.renderBoundaries(boundaryData, {
            level: scope.level,
            selectedName: scope.selected,
            onPick: (name) => pickBoundary(scope.level, name),
        });
        // Focus on the polygons; fall back to the markers if boundaries lag.
        if (!leaflet.fitToBoundaries()) {
            leaflet.fitToMarkers();
        }

        coverage.value = coverageData;
    } catch {
        loadError.value = 'Map data failed to load — adjust the filters and try again.';
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
    leaflet.mapRef.value?.on('zoomend', () => {
        zoom.value = leaflet.mapRef.value.getZoom();
    });
    setTimeout(() => refresh(), 100);
});

onBeforeUnmount(() => leaflet.destroy());
</script>

<template>
  <Head title="Map View" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-bold text-xl text-slate-900 tracking-tight leading-tight">Map View</h2>
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
          <div class="flex flex-wrap items-center gap-3">
            <select
              :value="filters.status"
              class="rounded-lg border-slate-300 text-sm focus:border-accent-500 focus:ring-accent-500/40"
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
                class="rounded border-slate-300 text-accent-500 focus:ring-accent-500/40"
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
      <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ loadError }}</div>
      <div class="dict-card px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
        <span class="text-sm text-slate-600">
          <strong class="font-semibold text-slate-800 tabular-nums">{{ plotted }}</strong>
          plotted · <span class="tabular-nums">Z{{ zoom }}</span>
        </span>
        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter by site health">
          <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            :aria-pressed="health === chip.key"
            :title="health === chip.key ? `Showing ${chip.label} only — click to clear` : `Show ${chip.label} only`"
            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40"
            :class="health === chip.key ? 'border-slate-700 bg-slate-100 text-slate-900' : 'border-slate-300 text-slate-600 hover:border-slate-400'"
            @click="toggleHealth(chip.key)"
          >
            <span class="inline-block size-2 rounded-full" :style="{ background: chip.color }" aria-hidden="true"></span>
            {{ chip.label }} <span class="tabular-nums">{{ chip.count }}</span>
          </button>
        </div>
        <label class="ml-auto flex items-center gap-2 text-[11px] uppercase tracking-wide text-slate-500">
          Merge
          <input
            v-model.number="clusterRadius"
            type="range"
            min="0"
            max="160"
            step="20"
            class="w-28 accent-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/40"
            aria-label="Cluster merge radius"
            @input="drawMarkers"
          />
          Detail
        </label>
      </div>
      <div ref="mapContainer" class="rounded-lg overflow-hidden border border-slate-200" style="height: 600px;"></div>

      <MapStatsPanel
        :coverage="coverage"
        :plotted="plotted"
        :deployed-only="filters.deployed_only === '1'"
        :truncated="truncated"
        @generate-pdf="generatePdf"
      />
    </div>
  </AuthenticatedLayout>
</template>
