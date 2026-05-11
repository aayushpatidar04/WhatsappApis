<template>
    <AppLayout title="Inbox">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Inbox</h1>
                <p class="text-sm text-gray-400 mt-0.5">All inbound messages across your instances</p>
            </div>
            <!-- Live indicator -->
            <div
                class="flex items-center gap-2 text-xs text-green-600 bg-green-50 border border-green-200 px-3 py-1.5 rounded-full">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                Live
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-5">
            <select v-model="filter.instance_id" class="form-input w-48 text-sm" @change="fetch">
                <option value="">All instances</option>
                <option v-for="inst in allInstances" :key="inst.id" :value="inst.id">{{ inst.name }}</option>
            </select>

            <div class="flex-1 min-w-48">
                <input v-model="filter.search" type="search" class="form-input text-sm" placeholder="Search messages…"
                    @input="debouncedFetch" />
            </div>
        </div>

        <!-- New message toast -->
        <Transition name="slide-down">
            <button v-if="newCount > 0" @click="loadNew"
                class="fixed top-20 left-1/2 -translate-x-1/2 z-40 bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-full shadow-lg flex items-center gap-2">
                <ArrowUpIcon class="w-4 h-4" />
                {{ newCount }} new message{{ newCount > 1 ? 's' : '' }} — Click to load
            </button>
        </Transition>

        <!-- Loading -->
        <div v-if="loading" class="space-y-3">
            <div v-for="n in 5" :key="n" class="card animate-pulse flex gap-4">
                <div class="w-10 h-10 bg-gray-200 rounded-full flex-shrink-0" />
                <div class="flex-1 space-y-2 py-1">
                    <div class="h-3 bg-gray-200 rounded w-1/4" />
                    <div class="h-3 bg-gray-100 rounded w-3/4" />
                </div>
            </div>
        </div>

        <!-- Empty -->
        <div v-else-if="!messages.length" class="card text-center py-16">
            <InboxIcon class="w-14 h-14 text-gray-200 mx-auto mb-3" />
            <h3 class="text-base font-semibold text-gray-700">No messages yet</h3>
            <p class="text-gray-400 text-sm mt-1">Inbound WhatsApp messages will appear here in real time.</p>
        </div>

        <!-- Message list -->
        <div v-else class="space-y-3">
            <div v-for="msg in messages" :key="msg.id" class="card hover:shadow-md transition-shadow cursor-pointer"
                @click="openMessage(msg)">
                <div class="flex items-start gap-4">
                    <!-- Avatar -->
                    <div
                        class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-teal-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                        {{ msg.phone?.charAt(0) ?? '?' }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <p class="font-semibold text-gray-900 text-sm">{{ msg.phone }}</p>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <!-- Message type badge -->
                                <span v-if="msg.type !== 'text'" class="badge bg-gray-100 text-gray-500 text-xs">{{
                                    msg.type }}</span>
                                <p class="text-xs text-gray-400">{{ timeAgo(msg.created_at) }}</p>
                            </div>
                        </div>

                        <!-- Body preview -->
                        <p class="text-sm text-gray-500 truncate">{{ msg.body ?? `[${msg.type}]` }}</p>

                        <!-- Instance indicator -->
                        <p class="text-xs text-gray-300 mt-1">via {{ msg.instance?.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Load more -->
            <button v-if="hasMore" @click="loadMore" class="btn-secondary w-full justify-center"
                :disabled="loadingMore">
                {{ loadingMore ? 'Loading…' : 'Load more' }}
            </button>
        </div>

        <!-- Message detail drawer -->
        <Teleport to="body">
            <Transition name="slide">
                <div v-if="selectedMessage" class="fixed inset-0 z-50 flex justify-end">
                    <div class="absolute inset-0 bg-black/40" @click="selectedMessage = null" />
                    <div class="relative bg-white w-full max-w-md shadow-2xl overflow-y-auto">
                        <div class="flex items-center justify-between px-6 py-5 border-b sticky top-0 bg-white z-10">
                            <div>
                                <p class="font-bold text-gray-900">{{ selectedMessage.phone }}</p>
                                <p class="text-xs text-gray-400">{{ formatDate(selectedMessage.created_at) }}</p>
                            </div>
                            <button @click="selectedMessage = null"
                                class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="bg-gray-50 rounded-xl p-4">
                                <p class="text-xs text-gray-400 mb-1 uppercase tracking-wide font-medium">Message</p>
                                <p class="text-gray-800">{{ selectedMessage.body ?? `[${selectedMessage.type}]` }}</p>
                            </div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-gray-400">From</span><span
                                        class="font-mono text-gray-800">{{ selectedMessage.recipient_jid }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-400">Type</span><span
                                        class="text-gray-800">{{ selectedMessage.type }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-400">Instance</span><span
                                        class="text-gray-800">{{ selectedMessage.instance?.name }}</span></div>
                                <div class="flex justify-between"><span class="text-gray-400">WA Message ID</span><code
                                        class="text-xs text-gray-600">{{ selectedMessage.wa_message_id }}</code></div>
                            </div>
                            <div v-if="selectedMessage.media_url" class="bg-blue-50 rounded-xl p-4">
                                <p class="text-xs text-gray-400 mb-2">Media</p>
                                <a :href="selectedMessage.media_url" target="_blank"
                                    class="text-blue-600 text-sm underline break-all">{{ selectedMessage.media_url
                                    }}</a>
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
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { InboxIcon, ArrowUpIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { messageApi, instanceApi } from '@/composables/useApi'

const messages = ref([])
const allInstances = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(false)
const currentPage = ref(1)
const newCount = ref(0)
const newMessages = ref([])
const selectedMessage = ref(null)

const filter = reactive({ instance_id: '', search: '' })

let pusherChannels = []
let debounceTimer = null

const page = usePage()

// ── Fetch ─────────────────────────────────────────────────────────────────────

async function fetch(reset = true) {
    if (reset) { loading.value = true; currentPage.value = 1 }
    try {
        const { data } = await messageApi.inbox({
            ...filter,
            page: currentPage.value,
            per_page: 20,
        })
        messages.value = reset ? data.data.data : [...messages.value, ...data.data.data]
        hasMore.value = !!data.data.next_page_url
    } finally {
        loading.value = false
    }
}

async function loadMore() {
    loadingMore.value = true
    currentPage.value++
    await fetch(false)
    loadingMore.value = false
}

async function loadNew() {
    messages.value = [...newMessages.value, ...messages.value]
    newMessages.value = []
    newCount.value = 0
}

const debouncedFetch = () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => fetch(), 400)
}

// ── Pusher — subscribe to ALL user's active instances ─────────────────────────

async function subscribeToInstances() {
    const { data } = await instanceApi.list({ status: 'active', per_page: 50 })
    allInstances.value = data.data?.data ?? []

    for (const inst of allInstances.value) {
        const ch = window.Echo?.private(`instance.${inst.instance_token}`)
        if (!ch) continue

        ch.listen('InstanceEvent', ({ event, payload }) => {
            if (event !== 'message.inbound') return
            const msg = payload.message
            if (!msg) return

            // Prepend to new buffer — show "X new messages" toast
            newMessages.value.unshift(msg)
            newCount.value++
        })

        pusherChannels.push({ token: inst.instance_token, channel: ch })
    }
}

onMounted(async () => {
    await fetch()
    await subscribeToInstances()
})

onUnmounted(() => {
    for (const { token } of pusherChannels) {
        window.Echo?.leave(`instance.${token}`)
    }
    clearTimeout(debounceTimer)
})

// ── Helpers ───────────────────────────────────────────────────────────────────

const openMessage = (msg) => { selectedMessage.value = msg }

const timeAgo = (iso) => {
    const diff = Date.now() - new Date(iso)
    const m = Math.floor(diff / 60000)
    if (m < 1) return 'just now'
    if (m < 60) return `${m}m ago`
    if (m < 1440) return `${Math.floor(m / 60)}h ago`
    return `${Math.floor(m / 1440)}d ago`
}

const formatDate = (iso) => new Date(iso).toLocaleString('en-IN', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
})
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

.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
    transform: translate(-50%, -20px);
    opacity: 0;
}
</style>