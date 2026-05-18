<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({ projects: Array });

const mapContainer = ref(null);
const map = ref(null);
const geojsonLayer = ref(null);
const selectedProject = ref('');
const selectedStatus = ref('');

const loadGeoJson = async () => {
    const params = new URLSearchParams();
    if (selectedProject.value) params.append('project_id', selectedProject.value);
    if (selectedStatus.value) params.append('status', selectedStatus.value);
    const response = await fetch(`/map/geojson?${params}`);
    const data = await response.json();
    if (geojsonLayer.value) map.value.removeLayer(geojsonLayer.value);
    geojsonLayer.value = L.geoJSON(data, {
        pointToLayer: (feature, latlng) => {
            const color = feature.properties.marker_color || '#64748b';
            return L.circleMarker(latlng, {
                radius: 8, fillColor: color, color: '#fff',
                weight: 2, opacity: 1, fillOpacity: 0.8
            });
        },
        onEachFeature: (feature, layer) => {
            const p = feature.properties;
            layer.bindPopup(`
                <div class="text-sm">
                    <strong>${p.location_name}</strong><br>
                    Project: ${p.project_name}<br>
                    Status: <span class="font-semibold">${p.status}</span><br>
                    ${p.barangay ? `Barangay: ${p.barangay}<br>` : ''}
                    ${p.municipality ? `Municipality: ${p.municipality}<br>` : ''}
                    ${p.province ? `Province: ${p.province}<br>` : ''}
                    ${p.region ? `Region: ${p.region}<br>` : ''}
                    ${p.daily_status ? `Daily Status: ${p.daily_status}<br>` : ''}
                    ${p.bandwidth ? `Bandwidth: ${p.bandwidth} Mbps<br>` : ''}
                    ${p.users ? `Users: ${p.users}<br>` : ''}
                </div>
            `);
        }
    });
    geojsonLayer.value.addTo(map.value);
};

onMounted(async () => {
    if (!window.L) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = initMap;
        document.head.appendChild(script);
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);
    } else {
        initMap();
    }
});

function initMap() {
    map.value = L.map(mapContainer.value).setView([12.8797, 121.7740], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map.value);
    loadGeoJson();
}
</script>

<template>
    <Head title="Map View" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-lg text-slate-800 leading-tight">Map View</h2>
        </template>

        <div>
            <!-- Filters -->
            <div class="dict-card p-4 mb-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Project</label>
                        <select v-model="selectedProject" @change="loadGeoJson"
                                class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Projects</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                        <select v-model="selectedStatus" @change="loadGeoJson"
                                class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="planned">Planned</option>
                            <option value="decommissioned">Decommissioned</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Map -->
            <div ref="mapContainer" class="rounded-lg overflow-hidden shadow-sm border border-slate-200" style="height: 600px;"></div>
        </div>
    </AuthenticatedLayout>
</template>
