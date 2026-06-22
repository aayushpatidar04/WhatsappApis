<template>
    <AppLayout title="Campaigns">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Campaigns</h1>
                <p class="text-sm text-gray-400 mt-0.5">Bulk WhatsApp messaging</p>
            </div>
            <button class="btn-primary" @click="openCreate">
                <PlusIcon class="w-4 h-4" />
                New Campaign
            </button>
        </div>

        <div class="flex gap-2 mb-5 flex-wrap">
            <button v-for="tab in statusTabs" :key="tab.value" @click="filter.status = tab.value; fetch()"
                :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                    filter.status == tab.value ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']">
                {{ tab.label }}
            </button>
        </div>

        <div v-if="loading" class="space-y-4">
            <div v-for="n in 4" :key="n" class="card animate-pulse h-28" />
        </div>

        <div v-else-if="!campaigns.length" class="card text-center py-14">
            <MegaphoneIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <h3 class="text-base font-semibold text-gray-700">No campaigns yet</h3>
            <p class="text-gray-400 text-sm mt-1">Create a campaign to send bulk WhatsApp messages to your contacts.</p>
            <button class="btn-primary btn-sm mt-4" @click="openCreate">Create Campaign</button>
        </div>

        <div v-else class="space-y-4">
            <div v-for="camp in campaigns" :key="camp.id" class="card hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-gray-900">{{ camp.name }}</h3>
                            <span :class="statusBadge(camp.status)">{{ camp.status }}</span>
                            <span class="badge bg-gray-100 text-gray-500 text-xs">{{ camp.message_type }}</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ camp.instance?.name }} ·
                            <template v-if="camp.schedule_time">Scheduled: {{ formatDate(camp.schedule_time)
                            }}</template>
                            <template v-else>{{ formatDate(camp.created_at) }}</template>
                        </p>
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <button v-if="['draft', 'scheduled'].includes(camp.status)" @click="launch(camp)"
                            class="btn-primary btn-sm" title="Launch">
                            <PlayIcon class="w-3.5 h-3.5" />
                        </button>
                        <button v-if="camp.status == 'running'" @click="pause(camp)" class="btn-secondary btn-sm"
                            title="Pause">
                            <PauseIcon class="w-3.5 h-3.5" />
                        </button>
                        <button v-if="camp.status == 'paused'" @click="resume(camp)" class="btn-primary btn-sm"
                            title="Resume">
                            <PlayIcon class="w-3.5 h-3.5" />
                        </button>

                        <button v-if="['draft', 'scheduled'].includes(camp.status)" @click="openEdit(camp)"
                            class="btn-secondary btn-sm" title="Edit">
                            <PencilIcon class="w-3.5 h-3.5" />
                        </button>

                        <button @click="openDetails(camp)" class="btn-secondary btn-sm" title="Analytics">
                            <ChartBarIcon class="w-3.5 h-3.5" />
                        </button>
                        <button v-if="!['running', 'completed'].includes(camp.status)" @click="cancel(camp)"
                            class="btn-sm p-2 text-red-400 hover:bg-red-50 rounded-lg border border-red-100"
                            title="Cancel">
                            <XMarkIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div v-if="camp.total_recipients > 0">
                    <div class="flex justify-between text-xs text-gray-400 mb-1.5">
                        <span>{{ camp.sent_count + camp.failed_count }} / {{ camp.total_recipients }} processed</span>
                        <span>{{ camp.delivery_rate }}% delivery</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="h-2 flex">
                            <div class="bg-green-500 transition-all duration-500"
                                :style="{ width: deliveredWidth(camp) + '%' }" />
                            <div class="bg-blue-400 transition-all duration-500"
                                :style="{ width: sentWidth(camp) + '%' }" />
                            <div class="bg-red-400 transition-all duration-500"
                                :style="{ width: failedWidth(camp) + '%' }" />
                        </div>
                    </div>
                    <div class="flex gap-3 mt-1.5 text-xs text-gray-400">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-500 rounded-full" /><span>{{
                            camp.delivered_count }} delivered</span></span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-blue-400 rounded-full" /><span>{{
                            camp.sent_count }} sent</span></span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-400 rounded-full" /><span>{{
                            camp.failed_count
                        }} failed</span></span>
                    </div>
                </div>
                <div v-else class="text-xs text-gray-400">
                    No recipients added yet.
                </div>
            </div>
        </div>

        <Teleport to="body">
            <Transition name="slide">
                <div v-if="detailsCampaign" class="fixed inset-0 z-50 flex justify-end">
                    <div class="absolute inset-0 bg-black/40" @click="detailsCampaign = null" />
                    <div class="relative bg-white w-full max-w-lg shadow-2xl overflow-y-auto">
                        <div class="flex items-center justify-between px-6 py-5 border-b sticky top-0 bg-white z-10">
                            <h2 class="font-bold text-gray-900">{{ detailsCampaign.name }}</h2>
                            <button @click="detailsCampaign = null"
                                class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="p-6 space-y-6">
                            <div v-if="analytics">
                                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-3">Performance
                                </p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="stat in analyticsStats" :key="stat.label"
                                        class="bg-gray-50 rounded-xl p-4 text-center">
                                        <p class="text-2xl font-bold" :class="stat.color">{{ stat.value }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ stat.label }}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">Recipients
                                    </p>
                                    <select v-model="recipientFilter" @change="loadRecipients"
                                        class="form-input text-xs w-auto">
                                        <option value="">All</option>
                                        <option value="sent">Sent</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="failed">Failed</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <div v-if="recipientsLoading" class="text-center py-4">
                                    <div
                                        class="w-6 h-6 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto" />
                                </div>
                                <div v-else class="divide-y divide-gray-50 max-h-64 overflow-y-auto">
                                    <div v-for="r in recipients" :key="r.id"
                                        class="flex items-center justify-between py-2.5">
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ r.name ?? r.phone }}</p>
                                            <p class="text-xs text-gray-400 font-mono">{{ r.phone }}</p>
                                        </div>
                                        <span :class="recipientBadge(r.status)">{{ r.status }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <CampaignCreateModal :show="showCreate" :instances="activeInstances" :groups="contactGroups"
            :edit-campaign="editingCampaign" @close="closeModal" @created="onCreated" @updated="onUpdated" />

    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import CampaignCreateModal from '@/Components/Campaign/CampaignCreateModal.vue'
import {
    PlusIcon, MegaphoneIcon, PlayIcon, PauseIcon,
    ChartBarIcon, XMarkIcon, PencilIcon
} from '@heroicons/vue/24/outline'
import { campaignApi, contactApi, instanceApi } from '@/composables/useApi'

const campaigns = ref([])
const activeInstances = ref([])
const contactGroups = ref([])
const analytics = ref(null)
const recipients = ref([])
const loading = ref(true)
const recipientsLoading = ref(false)
const showCreate = ref(false)
const editingCampaign = ref(null)
const detailsCampaign = ref(null)
const recipientFilter = ref('')
const filter = reactive({ status: '' })

const statusTabs = [
    { value: '', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'running', label: 'Running' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
]

// Pusher subscriptions for live campaign progress
let pusherChannels = []

onMounted(async () => {
    await fetch()
    const [instRes, groupRes] = await Promise.all([
        instanceApi.list({ status: 'active', per_page: 50 }),
        contactApi.groups(),
    ])
    activeInstances.value = instRes.data.data?.data ?? []
    contactGroups.value = groupRes.data.data ?? []
    subscribePusher()
})

onUnmounted(() => {
    pusherChannels.forEach(({ token }) => window.Echo?.leave(`instance.${token}`))
})

async function fetch() {
    loading.value = true
    try {
        const { data } = await campaignApi.list(filter)
        campaigns.value = data.data.data
    } finally {
        loading.value = false
    }
}

function subscribePusher() {
    for (const inst of activeInstances.value) {
        if (!window.Echo) break
        const ch = window.Echo.private(`instance.${inst.instance_token}`)
        ch.listen('InstanceEvent', ({ event, payload }) => {
            if (event == 'campaign.completed' || event == 'campaign.launched') {
                fetch()  // refresh on campaign lifecycle events
            }
        })
        pusherChannels.push({ token: inst.instance_token })
    }
}

const openCreate = () => {
    editingCampaign.value = null
    showCreate.value = true
}

const openEdit = (camp) => {
    editingCampaign.value = camp
    showCreate.value = true
}

const closeModal = () => {
    showCreate.value = false
    // Clear the edit state after the modal close animation finishes
    setTimeout(() => { editingCampaign.value = null }, 200)
}

const openDetails = async (camp) => {
    detailsCampaign.value = camp
    analytics.value = null
    recipients.value = []
    recipientFilter.value = ''
    const { data } = await campaignApi.analytics(camp.id)
    analytics.value = data.data
    await loadRecipients()
}

const loadRecipients = async () => {
    if (!detailsCampaign.value) return
    recipientsLoading.value = true
    try {
        const { data } = await campaignApi.recipients(detailsCampaign.value.id, { status: recipientFilter.value, per_page: 50 })
        recipients.value = data.data.data
    } finally {
        recipientsLoading.value = false
    }
}

const analyticsStats = computed(() => analytics.value ? [
    { label: 'Total', value: analytics.value.total, color: 'text-gray-900' },
    { label: 'Progress', value: analytics.value.progress_pct + '%', color: 'text-blue-600' },
    { label: 'Delivered', value: analytics.value.delivered, color: 'text-green-600' },
    { label: 'Delivery Rate', value: analytics.value.delivery_rate + '%', color: 'text-green-600' },
    { label: 'Read', value: analytics.value.read, color: 'text-indigo-600' },
    { label: 'Read Rate', value: analytics.value.read_rate + '%', color: 'text-indigo-600' },
    { label: 'Failed', value: analytics.value.failed, color: 'text-red-600' },
    { label: 'Pending', value: analytics.value.pending, color: 'text-gray-500' },
] : [])

const onCreated = (camp) => {
    closeModal()
    campaigns.value.unshift(camp)
}

const onUpdated = (camp) => {
    const idx = campaigns.value.findIndex(c => c.id == camp.id)
    if (idx !== -1) campaigns.value[idx] = { ...campaigns.value[idx], ...camp }
    closeModal()
}

const launch = async (c) => { await campaignApi.launch(c.id); updateStatus(c.id, 'running') }
const pause = async (c) => { await campaignApi.pause(c.id); updateStatus(c.id, 'paused') }
const resume = async (c) => { await campaignApi.resume(c.id); updateStatus(c.id, 'running') }
const cancel = async (c) => { if (!confirm(`Cancel "${c.name}"?`)) return; await campaignApi.cancel(c.id); updateStatus(c.id, 'cancelled') }

const updateStatus = (id, status) => {
    const idx = campaigns.value.findIndex(c => c.id == id)
    if (idx !== -1) campaigns.value[idx] = { ...campaigns.value[idx], status }
}

const deliveredWidth = (c) => c.total_recipients > 0 ? (c.delivered_count / c.total_recipients) * 100 : 0
const sentWidth = (c) => c.total_recipients > 0 ? ((c.sent_count - c.delivered_count) / c.total_recipients) * 100 : 0
const failedWidth = (c) => c.total_recipients > 0 ? (c.failed_count / c.total_recipients) * 100 : 0

const statusBadge = (s) => ({
    draft: 'badge bg-gray-100 text-gray-600', scheduled: 'badge bg-yellow-100 text-yellow-700',
    running: 'badge-active', paused: 'badge bg-orange-100 text-orange-700',
    completed: 'badge bg-indigo-100 text-indigo-700', failed: 'badge-expired', cancelled: 'badge-disconnected',
}[s] ?? 'badge')

const recipientBadge = (s) => ({
    pending: 'badge bg-gray-100 text-gray-500', queued: 'badge bg-blue-100 text-blue-600',
    sent: 'badge bg-blue-100 text-blue-700', delivered: 'badge-active',
    read: 'badge bg-indigo-100 text-indigo-700', failed: 'badge-expired', skipped: 'badge-disconnected',
}[s] ?? 'badge')

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