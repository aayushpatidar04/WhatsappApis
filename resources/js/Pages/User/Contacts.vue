<template>
    <AppLayout title="Contacts">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Contacts</h1>
                <p class="text-sm text-gray-400 mt-0.5">{{ pagination.total ?? 0 }} contacts</p>
            </div>
            <div class="flex gap-2">
                <button class="btn-secondary" @click="showImport = true">
                    <ArrowUpTrayIcon class="w-4 h-4" />
                    Import CSV
                </button>
                <button class="btn-primary" @click="showCreate = true">
                    <PlusIcon class="w-4 h-4" />
                    Add Contact
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-5">
            <input v-model="filter.search" type="search" class="form-input text-sm w-48"
                placeholder="Search name or phone…" @input="debouncedFetch" />
            <select v-model="filter.tag" class="form-input text-sm w-48" @change="fetch">
                <option value="">All tags</option>
                <option v-for="tag in tags" :key="tag" :value="tag">{{ tag }}</option>
            </select>
            <select v-model="filter.group_id" class="form-input text-sm w-48" @change="fetch">
                <option value="">All groups</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }} ({{ g.contacts_count }})</option>
            </select>
            <button @click="showGroups = true" class="btn-secondary btn-sm">
                <UserGroupIcon class="w-4 h-4" />
                Manage Groups
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="card animate-pulse h-64" />

        <!-- Empty -->
        <div v-else-if="!contacts.length" class="card text-center py-14">
            <UserGroupIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <h3 class="text-base font-semibold text-gray-700">No contacts yet</h3>
            <p class="text-gray-400 text-sm mt-1">Add contacts manually or import from a CSV file.</p>
            <div class="flex gap-3 justify-center mt-4">
                <button class="btn-secondary btn-sm" @click="showImport = true">Import CSV</button>
                <button class="btn-primary btn-sm" @click="showCreate = true">Add Contact</button>
            </div>
        </div>

        <!-- Contact table -->
        <div v-else class="card overflow-hidden p-0">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <input type="checkbox" @change="toggleAll" :checked="allSelected" class="rounded" />
                        </th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Name
                        </th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Phone</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tags
                        </th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            Status</th>
                        <th class="py-3 px-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="c in contacts" :key="c.id" class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <input type="checkbox" :value="c.id" v-model="selected" class="rounded" />
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-7 h-7 px-2 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-blue-700 text-xs font-bold">{{ c.name.charAt(0) }}</span>
                                </div>
                                <p class="font-medium text-gray-900 text-sm">{{ c.name }}</p>
                            </div>
                        </td>
                        <td class="py-3 px-4 font-mono text-gray-600 text-xs">{{ c.phone }}</td>
                        <td class="py-3 px-4">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="tag in (c.tags ?? [])" :key="tag"
                                    class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">{{ tag }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span v-if="c.is_blocked" class="badge-expired">Blocked</span>
                            <span v-else-if="c.is_whatsapp" class="badge-active">WhatsApp</span>
                            <span v-else class="badge bg-gray-100 text-gray-500">Unknown</span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex gap-1 justify-end">
                                <button @click="editContact(c)"
                                    class="p-1.5 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600">
                                    <PencilIcon class="w-4 h-4" />
                                </button>
                                <button @click="deleteContact(c)"
                                    class="p-1.5 hover:bg-red-50 rounded-lg text-red-400 hover:text-red-600">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Bulk actions -->
            <div v-if="selected.length" class="flex items-center gap-3 px-4 py-3 border-t border-gray-100 bg-blue-50">
                <p class="text-sm text-blue-700 font-medium">{{ selected.length }} selected</p>
                <select v-model="bulkGroupId" class="form-input text-sm w-auto">
                    <option value="">Add to group…</option>
                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>
                <button @click="addSelectedToGroup" :disabled="!bulkGroupId" class="btn-primary btn-sm">Add to
                    Group</button>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-400">{{ pagination.total ?? 0 }} contacts</p>
                <div class="flex gap-2">
                    <button :disabled="page === 1" @click="goTo(page - 1)" class="btn-secondary btn-sm px-3">‹</button>
                    <span class="text-xs text-gray-500 px-2 py-1.5">{{ page }} / {{ pagination.last_page ?? 1 }}</span>
                    <button :disabled="page >= (pagination.last_page ?? 1)" @click="goTo(page + 1)"
                        class="btn-secondary btn-sm px-3">›</button>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate || editingContact"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="closeForm" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">{{ editingContact ? 'Edit Contact' : 'Add Contact' }}
                            </h2>
                            <button @click="closeForm" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="saveContact" class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="form-input" required />
                                <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                            </div>
                            <div>
                                <label class="form-label">Phone <span class="text-red-500">*</span></label>
                                <input v-model="form.phone" type="tel" class="form-input" :disabled="!!editingContact"
                                    placeholder="919876543210" required />
                                <p class="text-xs text-gray-400 mt-1">Include country code, no + prefix.</p>
                                <p v-if="errors.phone" class="form-error">{{ errors.phone[0] }}</p>
                            </div>
                            <div>
                                <label class="form-label">Email <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <input v-model="form.email" type="email" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">Tags <span class="text-gray-400 font-normal">(comma
                                        separated)</span></label>
                                <input v-model="tagsInput" type="text" class="form-input"
                                    placeholder="VIP, Lead, Customer" />
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="btn-secondary flex-1" @click="closeForm">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="saving">
                                    {{ saving ? 'Saving…' : (editingContact ? 'Save Changes' : 'Add Contact') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- CSV Import Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showImport = false" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Import Contacts from CSV</h2>
                            <button @click="showImport = false" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
                                <p class="font-medium mb-1">CSV Format</p>
                                <p>Required column: <code class="bg-blue-100 px-1 rounded">phone</code></p>
                                <p>Optional: <code class="bg-blue-100 px-1 rounded">name</code>, <code
                                        class="bg-blue-100 px-1 rounded">email</code></p>
                                <p class="mt-2">First row must be header row.</p>
                            </div>
                            <div>
                                <label class="form-label">CSV File <span class="text-red-500">*</span></label>
                                <input ref="csvInput" type="file" accept=".csv,.txt" class="form-input"
                                    @change="onFileChange" />
                            </div>
                            <div>
                                <label class="form-label">Add to Group <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <select v-model="importGroupId" class="form-input">
                                    <option :value="null">None</option>
                                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                </select>
                            </div>

                            <!-- Import result -->
                            <div v-if="importResult"
                                :class="['rounded-xl p-4 text-sm', importResult.success ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700']">
                                <p class="font-semibold">{{ importResult.imported }} imported, {{ importResult.skipped
                                }} skipped</p>
                                <ul v-if="importResult.errors?.length" class="mt-2 space-y-0.5 text-xs">
                                    <li v-for="e in importResult.errors" :key="e">{{ e }}</li>
                                </ul>
                            </div>

                            <div class="flex gap-3">
                                <button class="btn-secondary flex-1" @click="showImport = false">Close</button>
                                <button class="btn-primary flex-1" @click="runImport" :disabled="!csvFile || importing">
                                    {{ importing ? 'Importing…' : 'Import' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Groups Manager Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showGroups" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showGroups = false" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Contact Groups</h2>
                            <button @click="showGroups = false" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <!-- Create group -->
                            <div class="flex gap-2">
                                <input v-model="newGroupName" type="text" class="form-input text-sm"
                                    placeholder="New group name…" maxlength="255" />
                                <button class="btn-primary btn-sm whitespace-nowrap" @click="createGroup"
                                    :disabled="!newGroupName">Create</button>
                            </div>
                            <!-- Group list -->
                            <div v-if="!groups.length" class="text-center py-6 text-gray-400 text-sm">No groups yet.
                            </div>
                            <div v-else class="divide-y divide-gray-100">
                                <div v-for="g in groups" :key="g.id" class="flex items-center justify-between py-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ g.name }}</p>
                                        <p class="text-xs text-gray-400">{{ g.contacts_count }} contacts</p>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ g.id }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { PlusIcon, ArrowUpTrayIcon, UserGroupIcon, PencilIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { contactApi } from '@/composables/useApi'

const contacts = ref([])
const tags = ref([])
const groups = ref([])
const loading = ref(true)
const saving = ref(false)
const importing = ref(false)
const showCreate = ref(false)
const showImport = ref(false)
const showGroups = ref(false)
const editingContact = ref(null)
const importResult = ref(null)
const csvFile = ref(null)
const importGroupId = ref(null)
const selected = ref([])
const bulkGroupId = ref('')
const newGroupName = ref('')
const pagination = ref({})
const page = ref(1)

const filter = reactive({ search: '', tag: '', group_id: '' })
const form = reactive({ name: '', phone: '', email: '', tags: [] })
const errors = reactive({})
const tagsInput = ref('')

const allSelected = computed(() => contacts.value.length > 0 && selected.value.length === contacts.value.length)

let debounceTimer = null
const debouncedFetch = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetch, 400) }

onMounted(async () => {
    await fetch()
    await loadMeta()
})

async function fetch() {
    loading.value = true
    try {
        const { data } = await contactApi.list({ ...filter, page: page.value, per_page: 25 })
        contacts.value = data.data.data
        pagination.value = { total: data.data.total, last_page: data.data.last_page }
    } finally {
        loading.value = false
    }
}

async function loadMeta() {
    const [tagsRes, groupsRes] = await Promise.all([contactApi.tags(), contactApi.groups()])
    tags.value = tagsRes.data.data
    groups.value = groupsRes.data.data
}

const goTo = (p) => { page.value = p; fetch() }

const toggleAll = () => {
    selected.value = allSelected.value ? [] : contacts.value.map(c => c.id)
}

const editContact = (c) => {
    editingContact.value = c
    Object.assign(form, { name: c.name, phone: c.phone, email: c.email ?? '', tags: c.tags ?? [] })
    tagsInput.value = (c.tags ?? []).join(', ')
}

const closeForm = () => {
    showCreate.value = false
    editingContact.value = null
    Object.assign(form, { name: '', phone: '', email: '', tags: [] })
    tagsInput.value = ''
    Object.keys(errors).forEach(k => delete errors[k])
}

const saveContact = async () => {
    saving.value = true
    const payload = { ...form, tags: tagsInput.value.split(',').map(t => t.trim()).filter(Boolean) }
    try {
        if (editingContact.value) {
            const { data } = await contactApi.update(editingContact.value.id, payload)
            const idx = contacts.value.findIndex(c => c.id === editingContact.value.id)
            if (idx !== -1) contacts.value[idx] = data.data
        } else {
            const { data } = await contactApi.create(payload)
            contacts.value.unshift(data.data)
            if (pagination.value.total != null) pagination.value.total++
        }
        closeForm()
    } catch (err) {
        if (err.response?.status === 422) Object.assign(errors, err.response.data.errors ?? {})
        else alert(err.response?.data?.message ?? 'Failed to save.')
    } finally {
        saving.value = false
    }
}

const deleteContact = async (c) => {
    if (!confirm(`Delete "${c.name}"?`)) return
    await contactApi.delete(c.id)
    contacts.value = contacts.value.filter(x => x.id !== c.id)
    if (pagination.value.total) pagination.value.total--
}

const onFileChange = (e) => { csvFile.value = e.target.files[0] }

const runImport = async () => {
    if (!csvFile.value) return
    importing.value = true
    importResult.value = null
    const fd = new FormData()
    fd.append('file', csvFile.value)
    if (importGroupId.value) fd.append('group_id', importGroupId.value)
    try {
        const { data } = await contactApi.import(fd)
        importResult.value = data
        if (data.imported > 0) await fetch()
    } catch (err) {
        importResult.value = { success: false, imported: 0, skipped: 0, errors: [err.response?.data?.message ?? 'Failed'] }
    } finally {
        importing.value = false
    }
}

const addSelectedToGroup = async () => {
    if (!bulkGroupId.value || !selected.value.length) return
    await contactApi.addToGroup(bulkGroupId.value, selected.value)
    selected.value = []; bulkGroupId.value = ''
}

const createGroup = async () => {
    if (!newGroupName.value) return
    const { data } = await contactApi.createGroup({ name: newGroupName.value })
    groups.value.push({ ...data.data, contacts_count: 0 })
    newGroupName.value = ''
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>