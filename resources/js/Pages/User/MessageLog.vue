<template>
    <AppLayout title="Message Log">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Message Log</h1>
                <p class="text-sm text-gray-400 mt-0.5">All sent and received messages</p>
            </div>
            <!-- Stats row -->
            <div class="flex gap-3">
                <div class="text-center bg-blue-50 rounded-xl px-4 py-2">
                    <p class="text-lg font-bold text-blue-700">{{ stats.today_sent ?? '—' }}</p>
                    <p class="text-xs text-gray-400">Sent today</p>
                </div>
                <div class="text-center bg-green-50 rounded-xl px-4 py-2">
                    <p class="text-lg font-bold text-green-700">{{ stats.delivery_rate ?? '—' }}%</p>
                    <p class="text-xs text-gray-400">Delivery rate</p>
                </div>
                <div class="text-center bg-orange-50 rounded-xl px-4 py-2">
                    <p class="text-lg font-bold text-orange-700">{{ stats.today_received ?? '—' }}</p>
                    <p class="text-xs text-gray-400">Received today</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-5">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <select v-model="filter.direction" @change="fetch" class="form-input text-sm">
                    <option value="">All directions</option>
                    <option value="outbound">Outbound</option>
                    <option value="inbound">Inbound</option>
                </select>
                <select v-model="filter.status" @change="fetch" class="form-input text-sm">
                    <option value="">All statuses</option>
                    <option value="queued">Queued</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="read">Read</option>
                    <option value="failed">Failed</option>
                </select>
                <select v-model="filter.instance_id" @change="fetch" class="form-input text-sm">
                    <option value="">All instances</option>
                    <option v-for="inst in instances" :key="inst.id" :value="inst.id">{{ inst.name }}</option>
                </select>
                <input v-model="filter.search" type="search" class="form-input text-sm"
                    placeholder="Search number or message…" @input="debouncedFetch" />
            </div>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-2">
            <div v-for="n in 8" :key="n" class="card animate-pulse flex gap-4 py-3">
                <div class="w-8 h-4 bg-gray-200 rounded flex-shrink-0" />
                <div class="flex-1 h-4 bg-gray-100 rounded" />
                <div class="w-16 h-4 bg-gray-200 rounded flex-shrink-0" />
            </div>
        </div>

        <!-- Empty -->
        <div v-else-if="!messages.length" class="card text-center py-14">
            <ChatBubbleLeftRightIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <p class="text-gray-400 text-sm">No messages match your filters.</p>
        </div>

        <!-- Table -->
        <div v-else class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Direction</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Phone</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Message</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Instance</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Status</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="msg in messages" :key="msg.id"
                            class="hover:bg-gray-50 cursor-pointer transition-colors" @click="selected = msg">
                            <td class="py-3 px-4">
                                <span
                                    :class="msg.direction == 'inbound' ? 'badge bg-green-100 text-green-700' : 'badge bg-blue-100 text-blue-700'">
                                    {{ msg.direction == 'inbound' ? '↓ In' : '↑ Out' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-gray-700 text-xs">{{ msg.phone }}</td>
                            <td class="py-3 px-4 text-gray-500 max-w-xs truncate">{{ msg.body ?? `[${msg.type}]` }}</td>
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ msg.instance?.name }}</td>
                            <td class="py-3 px-4">
                                <span :class="statusBadge(msg.status)">
                                    {{ statusLabel(msg.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-400 text-xs whitespace-nowrap">{{ timeAgo(msg.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-400">{{ pagination.total ?? 0 }} total messages</p>
                <div class="flex gap-2">
                    <button :disabled="page == 1" @click="goTo(page - 1)" class="btn-secondary btn-sm px-3">‹</button>
                    <span class="text-xs text-gray-500 px-2 py-1.5">{{ page }} / {{ pagination.last_page ?? 1 }}</span>
                    <button :disabled="page >= (pagination.last_page ?? 1)" @click="goTo(page + 1)"
                        class="btn-secondary btn-sm px-3">›</button>
                </div>
            </div>
        </div>

        <!-- Detail drawer -->
        <Teleport to="body">
            <Transition name="slide">
                <div v-if="selected" class="fixed inset-0 z-50 flex justify-end">
                    <div class="absolute inset-0 bg-black/40" @click="selected = null" />
                    <div class="relative bg-white w-full max-w-md shadow-2xl overflow-y-auto">
                        <div class="flex items-center justify-between px-6 py-5 border-b sticky top-0 bg-white z-10">
                            <h2 class="font-bold text-gray-900">Message Detail</h2>
                            <button @click="selected = null" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="p-6 space-y-5">
                            <!-- Status + direction -->
                            <div class="flex gap-3">
                                <span
                                    :class="selected.direction == 'inbound' ? 'badge bg-green-100 text-green-700' : 'badge bg-blue-100 text-blue-700'">
                                    {{ selected.direction }}
                                </span>
                                <span :class="statusBadge(selected.status)">{{ statusLabel(selected.status) }}</span>
                                <span class="badge bg-gray-100 text-gray-500">{{ selected.type }}</span>
                            </div>

                            <!-- Body -->
                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide font-medium">Content</p>
                                <p class="text-gray-800 break-words">{{ selected.body ?? `[${selected.type} message]` }}
                                </p>
                            </div>

                            <!-- Meta -->
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Phone</span>
                                    <code class="text-gray-800 font-mono">{{ selected.phone }}</code>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Instance</span>
                                    <span class="text-gray-800">{{ selected.instance?.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">WA Message ID</span>
                                    <code
                                        class="text-xs text-gray-500 truncate ml-4">{{ selected.wa_message_id ?? '—' }}</code>
                                </div>
                            </div>

                            <!-- Timeline -->
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold mb-3">Timeline</p>
                                <div class="space-y-2 text-sm">
                                    <div v-if="selected.queued_at" class="flex justify-between"><span
                                            class="text-gray-400">Queued</span><span>{{ formatDate(selected.queued_at)
                                            }}</span></div>
                                    <div v-if="selected.sent_at" class="flex justify-between"><span
                                            class="text-gray-400">Sent</span><span>{{ formatDate(selected.sent_at)
                                            }}</span></div>
                                    <div v-if="selected.delivered_at" class="flex justify-between"><span
                                            class="text-gray-400">Delivered</span><span>{{
                                            formatDate(selected.delivered_at) }}</span></div>
                                    <div v-if="selected.read_at" class="flex justify-between"><span
                                            class="text-gray-400">Read</span><span class="text-blue-600 font-medium">{{
                                            formatDate(selected.read_at) }}</span></div>
                                </div>
                            </div>

                            <!-- Error -->
                            <div v-if="selected.status == 'failed'"
                                class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                                <p class="font-semibold mb-1">Failure reason</p>
                                <p>{{ selected.error_message ?? 'Unknown error' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { ChatBubbleLeftRightIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { messageApi, instanceApi } from '@/composables/useApi'

const messages = ref([])
const instances = ref([])
const stats = ref({})
const loading = ref(true)
const selected = ref(null)
const page = ref(1)
const pagination = ref({})

const filter = reactive({ direction: '', status: '', instance_id: '', search: '' })

let debounceTimer = null
let pusherChannels = []

async function fetch() {
    loading.value = true
    try {
        const { data } = await messageApi.list({ ...filter, page: page.value, per_page: 25 })
        messages.value = data.data.data
        pagination.value = { total: data.data.total, last_page: data.data.last_page }
    } finally {
        loading.value = false
    }
}

const debouncedFetch = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetch, 400) }
const goTo = (p) => { page.value = p; fetch() }

// Subscribe to Pusher for live ACK updates
async function subscribeInstances() {
    const { data } = await instanceApi.list({ per_page: 50 })
    instances.value = data.data?.data ?? []

    for (const inst of instances.value) {
        const ch = window.Echo?.private(`instance.${inst.instance_token}`)
        if (!ch) continue

        ch.listen('InstanceEvent', ({ event, payload }) => {
            if (event == 'message.ack') {
                // Update status in-place without refetching
                const idx = messages.value.findIndex(m => m.id == payload.message_id)
                if (idx !== -1) messages.value[idx] = { ...messages.value[idx], status: payload.status }
                if (selected.value?.id == payload.message_id) selected.value.status = payload.status
            }
            if (event == 'message.sent') {
                const idx = messages.value.findIndex(m => m.id == payload.message_id)
                if (idx !== -1) messages.value[idx] = { ...messages.value[idx], status: 'sent' }
            }
        })

        pusherChannels.push(inst.instance_token)
    }
}

onMounted(async () => {
    await fetch()
    const { data } = await messageApi.stats()
    stats.value = data.data
    await subscribeInstances()
})

onUnmounted(() => {
    pusherChannels.forEach(t => window.Echo?.leave(`instance.${t}`))
    clearTimeout(debounceTimer)
})

// Status display
const statusBadge = (s) => ({
    queued: 'badge bg-gray-100 text-gray-500',
    sending: 'badge bg-blue-100 text-blue-700',
    sent: 'badge bg-blue-100 text-blue-700',
    delivered: 'badge bg-indigo-100 text-indigo-700',
    read: 'badge bg-green-100 text-green-700',
    failed: 'badge bg-red-100 text-red-700',
    rejected: 'badge bg-orange-100 text-orange-700',
}[s] ?? 'badge bg-gray-100 text-gray-500')

const statusLabel = (s) => ({
    queued: 'Queued', sending: 'Sending', sent: '✓ Sent',
    delivered: '✓✓ Delivered', read: '✓✓ Read', failed: '✗ Failed', rejected: 'Rejected',
}[s] ?? s)

const timeAgo = (iso) => {
    const m = Math.floor((Date.now() - new Date(iso)) / 60000)
    if (m < 1) return 'just now'
    if (m < 60) return `${m}m ago`
    if (m < 1440) return `${Math.floor(m / 60)}h ago`
    return `${Math.floor(m / 1440)}d ago`
}

const formatDate = (iso) => iso ? new Date(iso).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>