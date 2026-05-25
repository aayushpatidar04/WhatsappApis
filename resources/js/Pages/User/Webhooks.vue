<template>
    <AppLayout title="Webhooks">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Webhooks</h1>
                <p class="text-sm text-gray-400 mt-0.5">Receive WhatsApp events in your application</p>
            </div>
            <button class="btn-primary" @click="openCreateModal">
                <PlusIcon class="w-4 h-4" />
                New Webhook
            </button>
        </div>

        <div class="card mb-6">
            <h2 class="card-title mb-3">How it works</h2>
            <p class="text-sm text-gray-500 mb-3">
                When a selected event occurs (e.g. inbound message), we POST a JSON payload to your URL
                signed with HMAC-SHA256. Verify the signature using the secret shown below.
            </p>
            <div class="bg-gray-900 rounded-xl p-4 text-xs font-mono text-green-400 space-y-1">
                <p class="text-gray-500"># Verify signature in your app (PHP example):</p>
                <p>$sig = 'sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret);</p>
                <p>if (!hash_equals($sig, $_SERVER['HTTP_X_WA_SIGNATURE'])) abort(401);</p>
            </div>
        </div>

        <div v-if="loading" class="space-y-3">
            <div v-for="n in 3" :key="n" class="card animate-pulse h-20" />
        </div>

        <div v-else-if="!webhooks.length" class="card text-center py-14">
            <GlobeAltIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <h3 class="text-base font-semibold text-gray-700">No webhooks yet</h3>
            <p class="text-gray-400 text-sm mt-1 max-w-sm mx-auto">
                Register a URL to receive inbound messages and status updates in real time.
            </p>
            <button class="btn-primary btn-sm mt-4" @click="openCreateModal">
                <PlusIcon class="w-4 h-4" />
                Create Webhook
            </button>
        </div>

        <div v-else class="space-y-4">
            <div v-for="wh in webhooks" :key="wh.id" class="card">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900">{{ wh.name }}</h3>
                            <span :class="wh.is_active ? 'badge-active' : 'badge-suspended'">
                                {{ wh.is_active ? 'Active' : 'Disabled' }}
                            </span>
                            <span v-if="wh.failure_count > 0" class="badge bg-red-100 text-red-700">
                                {{ wh.failure_count }} failures
                            </span>
                        </div>
                        <p class="text-sm text-gray-400 font-mono truncate mt-1">{{ wh.url }}</p>
                        <p v-if="wh.instance" class="text-xs text-gray-400 mt-0.5">
                            Instance: {{ wh.instance.name }} ({{ wh.instance.phone_number }})
                        </p>
                        <p v-else class="text-xs text-gray-400 mt-0.5">All instances</p>
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <button @click="pingWebhook(wh)" :disabled="pinging === wh.id" class="btn-secondary btn-sm"
                            title="Test ping">
                            <BoltIcon class="w-4 h-4" :class="{ 'animate-pulse': pinging === wh.id }" />
                        </button>
                        <button @click="viewLogs(wh)" class="btn-secondary btn-sm" title="Delivery logs">
                            <ClipboardDocumentListIcon class="w-4 h-4" />
                        </button>
                        <button @click="openEditModal(wh)" class="btn-secondary btn-sm" title="Edit webhook">
                            <PencilIcon class="w-4 h-4" />
                        </button>
                        <button @click="toggleActive(wh)" class="btn-secondary btn-sm">
                            <component :is="wh.is_active ? PauseIcon : PlayIcon" class="w-4 h-4" />
                        </button>
                        <button @click="deleteWebhook(wh)"
                            class="btn-sm p-2 text-red-400 hover:bg-red-50 rounded-lg border border-red-100">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5 mb-3">
                    <span v-for="ev in wh.events" :key="ev" class="badge bg-blue-50 text-blue-700 text-xs">
                        {{ ev }}
                    </span>
                </div>

                <div class="bg-gray-50 rounded-lg px-3 py-2 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-400">Signing secret</p>
                        <code
                            class="text-xs font-mono text-gray-600">{{ showSecret === wh.id ? wh.secret : '••••••••••••••••••••' }}</code>
                    </div>
                    <div class="flex gap-2">
                        <button @click="showSecret = showSecret === wh.id ? null : wh.id"
                            class="text-gray-400 hover:text-gray-600">
                            <EyeIcon v-if="showSecret !== wh.id" class="w-4 h-4" />
                            <EyeSlashIcon v-else class="w-4 h-4" />
                        </button>
                        <button @click="copySecret(wh.secret)" class="text-gray-400 hover:text-blue-600">
                            <ClipboardDocumentIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <p v-if="wh.last_triggered_at" class="text-xs text-gray-400 mt-2">
                    Last triggered: {{ timeAgo(wh.last_triggered_at) }}
                </p>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showForm = false" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
                        <div class="flex items-center justify-between px-6 py-5 border-b flex-shrink-0">
                            <h2 class="font-bold text-gray-900">{{ editingId ? 'Edit Webhook' : 'Create Webhook' }}</h2>
                            <button @click="showForm = false" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="saveWebhook" class="px-6 py-5 space-y-4 overflow-y-auto hide-scrollbar">
                            <div>
                                <label class="form-label">Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="form-input"
                                    :class="{ 'border-red-400': errors.name }" placeholder="e.g. My CRM Handler"
                                    required />
                                <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                            </div>
                            <div>
                                <label class="form-label">URL <span class="text-red-500">*</span></label>
                                <input v-model="form.url" type="url" class="form-input"
                                    :class="{ 'border-red-400': errors.url }" placeholder="https://your-app.com/webhook"
                                    required />
                                <p class="text-xs text-gray-400 mt-1">Must be HTTPS.</p>
                                <p v-if="errors.url" class="form-error">{{ errors.url[0] }}</p>
                            </div>
                            <div>
                                <label class="form-label">Instance <span class="text-gray-400 font-normal">(optional —
                                        blank = all)</span></label>
                                <select v-model="form.instance_id" class="form-input" :disabled="editingId !== null">
                                    <option :value="null">All instances</option>
                                    <option v-for="inst in instances" :key="inst.id" :value="inst.id">{{ inst.name }}
                                    </option>
                                </select>
                                <p v-if="editingId" class="text-xs text-gray-400 mt-1">Instance mapping cannot be
                                    changed after creation.</p>
                            </div>
                            <div>
                                <label class="form-label">Events <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-2 mt-1">
                                    <label v-for="ev in availableEvents" :key="ev.value"
                                        class="flex items-center gap-2 p-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" :value="ev.value" v-model="form.events"
                                            class="rounded text-blue-600" />
                                        <span class="text-sm text-gray-700">{{ ev.label }}</span>
                                    </label>
                                </div>
                                <p v-if="errors.events" class="form-error">{{ errors.events[0] }}</p>
                            </div>
                            <p v-if="serverError" class="text-sm text-red-600">{{ serverError }}</p>
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="btn-secondary flex-1"
                                    @click="showForm = false">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="saving">
                                    {{ saving ? 'Saving…' : (editingId ? 'Save Changes' : 'Create Webhook') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition name="slide">
                <div v-if="logsWebhook" class="fixed inset-0 z-50 flex justify-end">
                    <div class="absolute inset-0 bg-black/40" @click="logsWebhook = null" />
                    <div class="relative bg-white w-full max-w-lg shadow-2xl overflow-y-auto">
                        <div class="flex items-center justify-between px-6 py-5 border-b sticky top-0 bg-white z-10">
                            <h2 class="font-bold text-gray-900">Delivery Logs — {{ logsWebhook.name }}</h2>
                            <button @click="logsWebhook = null" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="p-6">
                            <div v-if="logsLoading" class="text-center py-8">
                                <div
                                    class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto" />
                            </div>
                            <div v-else-if="!logs.length" class="text-center py-8 text-gray-400 text-sm">No delivery
                                logs yet.</div>
                            <div v-else class="space-y-3">
                                <div v-for="log in logs" :key="log.id" class="border border-gray-100 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span :class="log.success ? 'badge-active' : 'badge-expired'">
                                                {{ log.success ? 'Success' : 'Failed' }}
                                            </span>
                                            <span class="text-xs text-gray-400">HTTP {{ log.http_status ?? 'ERR'
                                            }}</span>
                                            <span class="text-xs text-gray-400">Attempt {{ log.attempt }}</span>
                                        </div>
                                        <span class="text-xs text-gray-400">{{ log.duration_ms }}ms · {{
                                            timeAgo(log.created_at) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 font-medium">{{ log.event }}</p>
                                    <p v-if="log.response_body" class="text-xs text-gray-400 font-mono mt-1 truncate">{{
                                        log.response_body }}</p>
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
import { ref, reactive, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import {
    PlusIcon, GlobeAltIcon, BoltIcon, ClipboardDocumentListIcon,
    PauseIcon, PlayIcon, TrashIcon, XMarkIcon, PencilIcon,
    EyeIcon, EyeSlashIcon, ClipboardDocumentIcon,
} from '@heroicons/vue/24/outline'
import { webhookApi, instanceApi } from '@/composables/useApi'

const webhooks = ref([])
const instances = ref([])
const logs = ref([])
const loading = ref(true)
const saving = ref(false)
const pinging = ref(null)
const logsLoading = ref(false)
const showForm = ref(false)
const editingId = ref(null)
const showSecret = ref(null)
const logsWebhook = ref(null)
const serverError = ref(null)
const errors = reactive({})

const form = reactive({
    name: '',
    url: '',
    instance_id: null,
    events: ['message.inbound'],
})

const availableEvents = [
    { value: 'message.inbound', label: 'Message Received' },
    { value: 'message.sent', label: 'Message Sent' },
    { value: 'message.ack', label: 'Delivery Status' },
    { value: 'message.failed', label: 'Message Failed' },
    { value: 'instance.connected', label: 'Instance Connected' },
    { value: 'instance.disconnected', label: 'Instance Disconnected' },
    { value: 'instance.expiring', label: 'Instance Expiring' },
]

onMounted(async () => {
    await fetchWebhooks()
    const { data } = await instanceApi.list({ per_page: 50 })
    instances.value = data.data?.data ?? []
})

async function fetchWebhooks() {
    loading.value = true
    try {
        const { data } = await webhookApi.list()
        webhooks.value = data.data
    } finally {
        loading.value = false
    }
}

const openCreateModal = () => {
    editingId.value = null
    form.name = ''
    form.url = ''
    form.instance_id = null
    form.events = ['message.inbound']
    Object.keys(errors).forEach(k => delete errors[k])
    serverError.value = null
    showForm.value = true
}

const openEditModal = (wh) => {
    editingId.value = wh.id
    form.name = wh.name
    form.url = wh.url
    form.instance_id = wh.instance?.id ?? null
    form.events = [...wh.events]
    Object.keys(errors).forEach(k => delete errors[k])
    serverError.value = null
    showForm.value = true
}

const saveWebhook = async () => {
    saving.value = true
    serverError.value = null
    Object.keys(errors).forEach(k => delete errors[k])

    try {
        if (editingId.value) {
            // Edit Flow
            const payload = {
                name: form.name,
                url: form.url,
                events: form.events,
            }
            const { data } = await webhookApi.update(editingId.value, payload)
            const idx = webhooks.value.findIndex(w => w.id === editingId.value)
            if (idx !== -1) webhooks.value[idx] = data.data
        } else {
            // Create Flow
            const { data } = await webhookApi.create(form)
            webhooks.value.unshift(data.data)
        }
        showForm.value = false
    } catch (err) {
        if (err.response?.status === 422) {
            Object.assign(errors, err.response.data.errors ?? {})
        } else {
            serverError.value = err.response?.data?.message ?? 'Failed to save webhook.'
        }
    } finally {
        saving.value = false
    }
}

const toggleActive = async (wh) => {
    const { data } = await webhookApi.update(wh.id, { is_active: !wh.is_active })
    const idx = webhooks.value.findIndex(w => w.id === wh.id)
    if (idx !== -1) webhooks.value[idx] = data.data
}

const deleteWebhook = async (wh) => {
    if (!confirm(`Delete webhook "${wh.name}"?`)) return
    await webhookApi.delete(wh.id)
    webhooks.value = webhooks.value.filter(w => w.id !== wh.id)
}

const pingWebhook = async (wh) => {
    pinging.value = wh.id
    try {
        const { data } = await webhookApi.ping(wh.id)
        alert(data.data.success
            ? `✓ Ping successful (HTTP ${data.data.http_status})`
            : `✗ Ping failed: ${data.data.error ?? `HTTP ${data.data.http_status}`}`)
    } finally {
        pinging.value = null
    }
}

const viewLogs = async (wh) => {
    logsWebhook.value = wh
    logsLoading.value = true
    try {
        const { data } = await webhookApi.logs(wh.id)
        logs.value = data.data
    } finally {
        logsLoading.value = false
    }
}

const copySecret = async (secret) => {
    await navigator.clipboard.writeText(secret)
}

const timeAgo = (iso) => {
    const diff = Date.now() - new Date(iso)
    const m = Math.floor(diff / 60000)
    if (m < 1) return 'just now'
    if (m < 60) return `${m}m ago`
    if (m < 1440) return `${Math.floor(m / 60)}h ago`
    return `${Math.floor(m / 1440)}d ago`
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

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
