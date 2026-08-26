<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconCirclePlus, IconSearch, IconUsersGroup, IconPencil, IconTrash, IconCircleCheck, IconCircleX } from '@tabler/icons-vue';
import { ref, watch } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
    projects: Array,
});

const searchForm = useForm({ search: props.filters.search ?? '', status: props.filters.status ?? '' });
let debounce = null;
watch(() => searchForm.search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => searchForm.get(route('users.index'), { preserveState: true }), 350);
});
watch(() => searchForm.status, () => searchForm.get(route('users.index'), { preserveState: true }));

const showCreate = ref(false);
const editing = ref(null);

const createForm = useForm({
    name: '', email: '', password: '',
    roles: [{ role_id: '', project_id: '' }],
});

function addRole() {
    createForm.roles.push({ role_id: '', project_id: '' });
}
function removeRole(i) {
    createForm.roles.splice(i, 1);
}

function create() {
    createForm.post(route('users.store'), {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
            createForm.roles = [{ role_id: '', project_id: '' }];
        },
    });
}

function openEdit(user) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.is_active = Boolean(user.is_active);
    editForm.roles = user.roles.map((r) => ({
        role_id: String(r.id),
        project_id: r.pivot?.project_id ? String(r.pivot.project_id) : '',
    }));
    if (!editForm.roles.length) editForm.roles = [{ role_id: '', project_id: '' }];
    editForm.clearErrors();
}

const editForm = useForm({
    name: '', email: '', password: '', is_active: true,
    roles: [{ role_id: '', project_id: '' }],
});

function saveEdit() {
    editForm.put(route('users.update', editing.value.id), {
        onSuccess: () => (editing.value = null),
        preserveScroll: true,
    });
}

function removeUser(user) {
    if (confirm(`Delete ${user.email}? Their audit history is preserved.`)) {
        router.delete(route('users.destroy', user.id), { preserveScroll: true });
    }
}
</script>

<template>
  <Head title="Users" />
  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between w-full flex-wrap gap-3">
        <h2 class="font-semibold text-lg text-slate-800 leading-tight">User Administration</h2>
        <button @click="showCreate = true"
          class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-3.5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">
          <IconCirclePlus class="w-4 h-4" /> New User
        </button>
      </div>
    </template>

    <!-- Filters -->
    <div class="dict-card p-4 mb-4">
      <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
          <label for="user-search" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Search</label>
          <div class="relative">
            <IconSearch class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input id="user-search" v-model="searchForm.search" type="text" placeholder="Name or email…"
              class="w-full rounded-lg border-slate-300 text-sm pl-9 focus:border-blue-500 focus:ring-blue-200" />
          </div>
        </div>
        <div>
          <label for="user-status" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
          <select id="user-status" v-model="searchForm.status"
            class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200">
            <option value="">All</option>
            <option value="1">Active</option>
            <option value="0">Deactivated</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="dict-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="dict-table-header">
              <th class="px-6 py-3">User</th>
              <th class="px-6 py-3">Roles</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4">
                <div class="text-sm font-medium text-slate-800">{{ user.name }}</div>
                <div class="text-xs text-slate-400">{{ user.email }}</div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="role in user.roles" :key="role.id"
                    class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200">
                    {{ role.name }}
                  </span>
                  <span v-if="!user.roles?.length" class="text-xs text-slate-400">No roles</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium"
                  :class="user.is_active ? 'text-emerald-700' : 'text-slate-400'">
                  <IconCircleCheck v-if="user.is_active" class="w-4 h-4" />
                  <IconCircleX v-else class="w-4 h-4" />
                  {{ user.is_active ? 'Active' : 'Deactivated' }}
                </span>
              </td>
              <td class="px-6 py-4 text-right whitespace-nowrap space-x-3">
                <button @click="openEdit(user)" class="text-sm text-blue-600 hover:text-blue-800 font-medium inline-flex items-center gap-1">
                  <IconPencil class="w-4 h-4" /> Edit
                </button>
                <button @click="removeUser(user)" class="text-sm text-red-600 hover:text-red-800 font-medium inline-flex items-center gap-1">
                  <IconTrash class="w-4 h-4" /> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!users.data?.length" class="px-6 py-12 text-center">
        <IconUsersGroup class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm text-slate-500">No users match.</p>
      </div>
    </div>

    <div v-if="users.links" class="mt-4 flex gap-1 flex-wrap">
      <component
        :is="link.url ? 'button' : 'span'"
        v-for="(link, i) in users.links"
        :key="i"
        @click="link.url && router.get(link.url, {}, { preserveState: true })"
        class="min-w-[2.25rem] text-center px-2 py-1.5 text-sm rounded-lg"
        :class="link.active
            ? 'bg-blue-600 text-white font-medium'
            : link.url
                ? 'dict-card text-slate-600 hover:bg-slate-100 cursor-pointer'
                : 'text-slate-300'"
        v-html="link.label"
      />
    </div>

    <!-- Create modal -->
    <Modal :show="showCreate" @close="showCreate = false" max-width="2xl">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">New user</h3>
        <form @submit.prevent="create" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="nu-name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
              <input id="nu-name" v-model="createForm.name" type="text" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200" />
              <InputError :message="createForm.errors.name" class="mt-1" />
            </div>
            <div>
              <label for="nu-email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
              <input id="nu-email" v-model="createForm.email" type="email" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200" />
              <InputError :message="createForm.errors.email" class="mt-1" />
            </div>
          </div>
          <div>
            <label for="nu-pass" class="block text-sm font-medium text-slate-700 mb-1">Temporary password</label>
            <input id="nu-pass" v-model="createForm.password" type="text" class="w-full rounded-lg border-slate-300 text-sm font-mono focus:border-blue-500 focus:ring-blue-200" />
            <InputError :message="createForm.errors.password" class="mt-1" />
            <p class="text-xs text-slate-400 mt-1">Share securely; the user should change it after first sign-in.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Role assignments</label>
            <div v-for="(assignment, i) in createForm.roles" :key="i" class="flex gap-2 mb-2">
              <select v-model="assignment.role_id" class="flex-1 rounded-lg border-slate-300 text-sm">
                <option value="">— select role —</option>
                <option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option>
              </select>
              <select v-model="assignment.project_id" class="flex-1 rounded-lg border-slate-300 text-sm">
                <option value="">All projects (global)</option>
                <option v-for="p in projects" :key="p.id" :value="String(p.id)">Scoped: {{ p.name }}</option>
              </select>
              <button type="button" v-if="createForm.roles.length > 1" @click="removeRole(i)"
                class="px-2 text-slate-400 hover:text-red-600" aria-label="Remove role assignment">✕</button>
            </div>
            <button type="button" @click="addRole" class="text-xs font-medium text-blue-600 hover:text-blue-800">+ Add another role</button>
            <InputError :message="createForm.errors['roles.0.role_id']" class="mt-1" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showCreate = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button type="submit" :disabled="createForm.processing"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-60 transition">
              {{ createForm.processing ? 'Creating…' : 'Create user' }}
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Edit modal -->
    <Modal :show="!!editing" @close="editing = null" max-width="2xl">
      <div v-if="editing" class="p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-1">Edit user</h3>
        <p class="text-sm text-slate-500 mb-4">{{ editing.email }}</p>
        <form @submit.prevent="saveEdit" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="eu-name" class="block text-sm font-medium text-slate-700 mb-1">Name</label>
              <input id="eu-name" v-model="editForm.name" type="text" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200" />
              <InputError :message="editForm.errors.name" class="mt-1" />
            </div>
            <div>
              <label for="eu-email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
              <input id="eu-email" v-model="editForm.email" type="email" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-200" />
              <InputError :message="editForm.errors.email" class="mt-1" />
            </div>
          </div>
          <div>
            <label for="eu-pass" class="block text-sm font-medium text-slate-700 mb-1">New password <span class="font-normal text-slate-400">(leave blank to keep)</span></label>
            <input id="eu-pass" v-model="editForm.password" type="text" class="w-full rounded-lg border-slate-300 text-sm font-mono focus:border-blue-500 focus:ring-blue-200" />
            <InputError :message="editForm.errors.password" class="mt-1" />
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" v-model="editForm.is_active" :disabled="editing.id === $page.props.auth.user.id"
              class="rounded border-slate-300 text-blue-600 focus:ring-blue-200 disabled:opacity-50" />
            Account active
            <span v-if="editing.id === $page.props.auth.user.id" class="text-xs text-slate-400">(you can't deactivate yourself)</span>
          </label>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Role assignments</label>
            <div v-for="(assignment, i) in editForm.roles" :key="i" class="flex gap-2 mb-2">
              <select v-model="assignment.role_id" class="flex-1 rounded-lg border-slate-300 text-sm">
                <option value="">— select role —</option>
                <option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option>
              </select>
              <select v-model="assignment.project_id" class="flex-1 rounded-lg border-slate-300 text-sm">
                <option value="">All projects (global)</option>
                <option v-for="p in projects" :key="p.id" :value="String(p.id)">Scoped: {{ p.name }}</option>
              </select>
              <button type="button" v-if="editForm.roles.length > 1" @click="editForm.roles.splice(i, 1)"
                class="px-2 text-slate-400 hover:text-red-600" aria-label="Remove role assignment">✕</button>
            </div>
            <button type="button" @click="editForm.roles.push({ role_id: '', project_id: '' })"
              class="text-xs font-medium text-blue-600 hover:text-blue-800">+ Add another role</button>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="editing = null" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
            <button type="submit" :disabled="editForm.processing"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-60 transition">
              {{ editForm.processing ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
