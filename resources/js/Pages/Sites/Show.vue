<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { IconArrowLeft, IconCirclePlus } from '@tabler/icons-vue';
import { ref } from 'vue';

const props = defineProps({ site: Object, deviceModels: Array, stockDevices: Array });

const page = usePage();
const can = (permission) => page.props.auth.permissions?.includes(permission);

const canAttach = can('devices.create');
const canDetach = can('devices.edit');
const canEditSite = can('sites.edit');

const editOpen = ref(false);
const editForm = useForm({
    location_name: props.site.location_name ?? '',
    site_type: props.site.site_type ?? '',
    barangay: props.site.barangay ?? '',
    municipality: props.site.municipality ?? '',
    province: props.site.province ?? '',
    district: props.site.district ?? '',
    region: props.site.region ?? '',
    island_group: props.site.island_group ?? '',
    latitude: props.site.latitude ?? '',
    longitude: props.site.longitude ?? '',
    date_of_activation: props.site.date_of_activation ?? '',
    status: props.site.status ?? 'active',
    isp_provider: props.site.isp_provider ?? '',
    last_mile_tech: props.site.last_mile_tech ?? '',
    bw_download_cir: props.site.bw_download_cir ?? '',
});

function openEdit() {
    editOpen.value = !editOpen.value;
    Object.keys(editForm.data()).forEach((key) => {
        editForm[key] = props.site[key] ?? editForm[key];
    });
    editForm.clearErrors();
}

function saveEdit() {
    editForm.put(route('sites.update', props.site.id), { preserveScroll: true, onSuccess: () => (editOpen.value = false) });
}

const showForm = ref(false);
const mode = ref('existing');
const roleLabels = {
    primary_ap: 'Primary AP', backup_ap: 'Backup AP', backhaul: 'Backhaul',
    power: 'Power', surveillance: 'Surveillance', other: 'Other',
};

const form = useForm({
    mode: 'existing',
    device_id: '',
    device_model_id: '',
    asset_tag: '',
    serial_number: '',
    mac_address: '',
    firmware_version: '',
    role_at_site: 'primary_ap',
    installed_at: new Date().toISOString().slice(0, 10),
});

function openForm() {
    showForm.value = true;
    mode.value = props.stockDevices?.length ? 'existing' : 'new';
    form.mode = mode.value;
}

function attach() {
    form.mode = mode.value;
    form.post(route('sites.equipment.store', props.site.id), {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
}

function detach(deployment) {
    if (confirm('Detach this unit? The deployment closes and the device returns to stock.')) {
        router.delete(route('sites.equipment.destroy', { site: props.site.id, deployment: deployment.id }), { preserveScroll: true });
    }
}
</script>

<template>
  <Head :title="site.location_name" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-lg text-slate-800 leading-tight">{{ site.location_name }}</h2>
    </template>

    <div>
      <!-- Back Link -->
      <Link :href="route('sites.index')" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 mb-4 transition">
        <IconArrowLeft class="w-4 h-4" /> Back to Sites
      </Link>

      <!-- Header Card -->
      <div class="dict-card p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-xl font-bold text-slate-800">{{ site.location_name }}</h1>
            <span class="text-sm text-slate-500">{{ site.project?.name }}</span>
          </div>
          <div class="flex items-center gap-3">
            <span
              class="px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                'bg-green-100 text-green-700': site.status === 'active',
                'bg-red-100 text-red-700': site.status === 'inactive',
                'bg-yellow-100 text-yellow-700': site.status === 'planned',
                'bg-slate-100 text-slate-500': site.status === 'decommissioned',
                'bg-orange-100 text-orange-700': site.status === 'maintenance',
              }"
            >{{ site.status }}</span>
            <button
              v-if="canEditSite" type="button"
              class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-300 text-slate-600 hover:bg-slate-100 transition"
              @click="openEdit"
            >
              {{ editOpen ? 'Close editor' : 'Edit details' }}
            </button>
            <Link :href="route('sites.daily-grid', site.id)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">Daily Status</Link>
            <Link :href="route('sites.accomplishments', site.id)" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-700 transition">Accomplishments</Link>
          </div>
        </div>
      </div>

      <!-- Edit details -->
      <div v-if="editOpen" class="dict-card p-6 mb-6 border-l-4 border-blue-500">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Edit site details</h3>
        <form class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4" @submit.prevent="saveEdit">
          <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Location name</label>
            <input v-model="editForm.location_name" type="text" class="w-full rounded-lg border-slate-300 text-sm" required /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site type</label>
            <input v-model="editForm.site_type" type="text" class="w-full rounded-lg border-slate-300 text-sm" placeholder="PES, PHS, LGU-BRGY…" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Status</label>
            <select v-model="editForm.status" class="w-full rounded-lg border-slate-300 text-sm">
              <option v-for="option in ['planned', 'active', 'inactive', 'decommissioned', 'maintenance']" :key="option" :value="option">{{ option }}</option>
            </select></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Barangay</label>
            <input v-model="editForm.barangay" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Municipality</label>
            <input v-model="editForm.municipality" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Province</label>
            <input v-model="editForm.province" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">District</label>
            <input v-model="editForm.district" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Region</label>
            <input v-model="editForm.region" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Island group</label>
            <select v-model="editForm.island_group" class="w-full rounded-lg border-slate-300 text-sm">
              <option value="">—</option>
              <option v-for="option in ['Luzon', 'Visayas', 'Mindanao']" :key="option" :value="option">{{ option }}</option>
            </select></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Latitude</label>
            <input v-model="editForm.latitude" type="number" step="any" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Longitude</label>
            <input v-model="editForm.longitude" type="number" step="any" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date of activation</label>
            <input v-model="editForm.date_of_activation" type="date" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">ISP</label>
            <input v-model="editForm.isp_provider" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Last mile tech</label>
            <input v-model="editForm.last_mile_tech" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Bandwidth CIR (Mbps)</label>
            <input v-model="editForm.bw_download_cir" type="number" step="any" min="0" class="w-full rounded-lg border-slate-300 text-sm" /></div>
          <div class="sm:col-span-2 lg:col-span-4 flex items-center gap-3">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition" :disabled="editForm.processing">
              {{ editForm.processing ? 'Saving…' : 'Save changes' }}
            </button>
            <button type="button" class="text-sm text-slate-500 underline" @click="editOpen = false">Cancel</button>
            <span v-if="Object.values(editForm.errors).length" class="text-sm text-red-600">{{ Object.values(editForm.errors)[0] }}</span>
          </div>
        </form>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="dict-card p-6">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Location Details</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-slate-500">Barangay</dt>
              <dd class="text-sm text-slate-700">{{ site.barangay }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Municipality</dt>
              <dd class="text-sm text-slate-700">{{ site.municipality }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Province</dt>
              <dd class="text-sm text-slate-700">{{ site.province }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Region</dt>
              <dd class="text-sm text-slate-700">{{ site.region }}</dd>
            </div>
          </dl>
        </div>

        <div class="dict-card p-6">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Technical Details</h3>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-slate-500">Latitude</dt>
              <dd class="text-sm font-mono text-slate-700">{{ site.latitude }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Longitude</dt>
              <dd class="text-sm font-mono text-slate-700">{{ site.longitude }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">ISP</dt>
              <dd class="text-sm text-slate-700">{{ site.isp_provider }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3">
              <dt class="text-sm text-slate-500">Bandwidth</dt>
              <dd class="text-sm text-slate-700">{{ site.bw_download_cir }} Mbps</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- Installed Equipment -->
      <div class="dict-card overflow-hidden mt-6">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Installed Equipment</h3>
          <button
            v-if="canAttach && !showForm" type="button"
            class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition"
            @click="openForm"
          >
            <IconCirclePlus class="w-4 h-4" /> Attach equipment
          </button>
        </div>

        <!-- Attach form -->
        <div v-if="showForm" class="px-6 py-4 border-b border-slate-200 bg-slate-50/60">
          <div class="flex gap-2 mb-4">
            <button
              v-for="option in ['existing', 'new']" :key="option" type="button"
              class="px-3 py-1.5 rounded-lg text-sm font-medium transition"
              :class="mode === option ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'"
              @click="mode = option; form.mode = option"
            >
              {{ option === 'existing' ? 'Assign from stock' : 'Register new unit' }}
            </button>
          </div>
          <form class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" @submit.prevent="attach">
            <template v-if="mode === 'existing'">
              <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">In-stock device</label>
                <select v-model="form.device_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                  <option value="" disabled>Select an in-stock unit…</option>
                  <option v-for="device in stockDevices" :key="device.id" :value="device.id">
                    {{ device.asset_tag }} — {{ device.device_model?.manufacturer }} {{ device.device_model?.model_name }} (S/N {{ device.serial_number }})
                  </option>
                </select>
              </div>
            </template>
            <template v-else>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Model</label>
                <select v-model="form.device_model_id" class="w-full rounded-lg border-slate-300 text-sm" required>
                  <option value="" disabled>Select model…</option>
                  <option v-for="model in deviceModels" :key="model.id" :value="model.id">
                    {{ model.manufacturer }} {{ model.model_name }} ({{ model.model_number }})
                  </option>
                </select>
              </div>
              <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Asset tag</label>
                <input v-model="form.asset_tag" type="text" class="w-full rounded-lg border-slate-300 text-sm" required /></div>
              <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Serial no.</label>
                <input v-model="form.serial_number" type="text" class="w-full rounded-lg border-slate-300 text-sm" required /></div>
              <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">MAC (optional)</label>
                <input v-model="form.mac_address" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
              <div><label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Firmware (optional)</label>
                <input v-model="form.firmware_version" type="text" class="w-full rounded-lg border-slate-300 text-sm" /></div>
            </template>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Role at site</label>
              <select v-model="form.role_at_site" class="w-full rounded-lg border-slate-300 text-sm">
                <option v-for="(label, value) in roleLabels" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Installed</label>
              <input v-model="form.installed_at" type="date" class="w-full rounded-lg border-slate-300 text-sm" />
            </div>
            <div class="flex items-end gap-3">
              <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition" :disabled="form.processing">
                {{ form.processing ? 'Attaching…' : 'Attach' }}
              </button>
              <button type="button" class="text-sm text-slate-500 underline" @click="showForm = false">Cancel</button>
            </div>
            <div v-if="Object.values(form.errors).length" class="sm:col-span-2 lg:col-span-3 text-sm text-red-600">
              {{ Object.values(form.errors)[0] }}
            </div>
          </form>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <caption class="sr-only">Equipment currently deployed at this site</caption>
            <thead>
              <tr class="dict-table-header">
                <th class="px-6 py-3">Asset Tag</th>
                <th class="px-6 py-3">Model</th>
                <th class="px-6 py-3">Role</th>
                <th class="px-6 py-3">Installed</th>
                <th class="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="deployment in site.active_deployments" :key="deployment.id" class="hover:bg-slate-50/50">
                <td class="px-6 py-4 text-sm font-mono font-semibold text-slate-700">{{ deployment.device?.asset_tag }}</td>
                <td class="px-6 py-4 text-sm text-slate-700">
                  {{ deployment.device?.device_model?.manufacturer }} {{ deployment.device?.device_model?.model_name }}
                </td>
                <td class="px-6 py-4 text-sm capitalize text-slate-600">{{ deployment.role_at_site?.replaceAll('_', ' ') }}</td>
                <td class="px-6 py-4 text-sm text-slate-600">{{ deployment.installed_at ? new Date(deployment.installed_at).toLocaleDateString() : '—' }}</td>
                <td class="px-6 py-4 text-sm">
                  <div class="flex gap-2">
                    <Link v-if="deployment.device" :href="route('devices.show', deployment.device.id)" class="text-blue-600 hover:text-blue-800 font-medium">View</Link>
                    <button
                      v-if="canDetach" type="button"
                      class="text-red-600 hover:text-red-800 font-medium"
                      @click="confirm('Detach this unit? It returns to stock.') && detach(deployment)"
                    >
                      Detach
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!site.active_deployments?.length" class="px-6 py-10 text-center">
          <p class="text-sm text-slate-500">No equipment registered at this site yet.</p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
