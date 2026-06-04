<template>
    <AppLayout title="Reports">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Reports & Analytics</h1>
                <p class="text-sm text-gray-400 mt-0.5">Your account messaging performance</p>
            </div>
            <div class="flex gap-2">
                <select v-model="days" @change="loadAll" class="form-input text-sm w-32">
                    <option :value="7">Last 7 days</option>
                    <option :value="30">Last 30 days</option>
                    <option :value="90">Last 90 days</option>
                </select>
                <a :href="`/client/reports/export?days=${days}`" class="btn-secondary btn-sm">
                    <ArrowDownTrayIcon class="w-4 h-4" />
                    Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div v-if="overview" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard :icon="PaperAirplaneIcon" label="Sent" :value="fmt(overview.messages.sent)" color="blue" />
            <StatCard :icon="CheckCircleIcon" label="Delivered" :value="fmt(overview.messages.delivered)"
                color="green" />
            <StatCard :icon="EyeIcon" label="Read" :value="fmt(overview.messages.read)" color="purple" />
            <StatCard :icon="XCircleIcon" label="Failed" :value="fmt(overview.messages.failed)" color="red" />
        </div>

        <!-- Charts -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
            <!-- Daily volume -->
            <div class="lg:col-span-2 card">
                <h2 class="card-title mb-4">Daily Message Volume</h2>
                <div v-if="!dailyVolume.length" class="h-48 flex items-center justify-center text-gray-300 text-sm">No
                    data</div>
                <div v-else class="h-48 flex items-end gap-1 overflow-hidden">
                    <div v-for="d in dailyVolume" :key="d.date"
                        class="flex-1 flex flex-col justify-end group cursor-default min-h-px"
                        :title="`${d.date}: ${d.total} sent, ${d.delivered} delivered`">
                        <div class="bg-red-400 rounded-t-sm" :style="{ height: barH(d.failed, maxTotal) + '%' }" />
                        <div class="bg-green-500" :style="{ height: barH(d.delivered - d.failed, maxTotal) + '%' }" />
                        <div class="bg-blue-400" :style="{ height: barH(d.total - d.delivered, maxTotal) + '%' }" />
                    </div>
                </div>
            </div>

            <!-- Type breakdown -->
            <div class="card">
                <h2 class="card-title mb-4">By Type</h2>
                <div v-if="!typeBreakdown.length" class="text-center py-8 text-gray-300 text-sm">No data</div>
                <div v-else class="space-y-3">
                    <div v-for="t in typeBreakdown" :key="t.type">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-700 font-medium capitalize">{{ t.type }}</span>
                            <span class="text-gray-500">{{ t.count }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full bg-blue-500" :style="{ width: typePct(t.count) + '%' }" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By instance -->
        <div class="card mb-6">
            <h2 class="card-title mb-4">By Instance</h2>
            <div v-if="!byInstance.length" class="text-center py-8 text-gray-300 text-sm">No data</div>
            <div v-else class="divide-y divide-gray-50">
                <div v-for="i in byInstance" :key="i.instance_id"
                    class="py-3 flex items-center justify-between first:pt-0 last:pb-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ i.instance_name }}</p>
                        <p class="text-xs text-gray-400">{{ i.phone }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900">{{ i.total }}</p>
                        <p class="text-xs text-green-600">{{ i.delivery_rate }}%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campaign stats -->
        <div v-if="campaignStats" class="card">
            <h2 class="card-title mb-4">Campaign Performance</h2>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 text-center">
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-lg font-bold text-gray-900">{{ campaignStats.total_campaigns }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Campaigns</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-lg font-bold text-gray-900">{{ fmt(campaignStats.total_recipients) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Recipients</p>
                </div>
                <div class="bg-green-50 rounded-lg p-3">
                    <p class="text-lg font-bold text-green-700">{{ fmt(campaignStats.total_delivered) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Delivered</p>
                </div>
                <div class="bg-red-50 rounded-lg p-3">
                    <p class="text-lg font-bold text-red-700">{{ fmt(campaignStats.total_failed) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Failed</p>
                </div>
                <div class="bg-blue-50 rounded-lg p-3">
                    <p class="text-lg font-bold text-blue-700">{{ campaignStats.avg_delivery_rate }}%</p>
                    <p class="text-xs text-gray-400 mt-0.5">Avg Delivery</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { PaperAirplaneIcon, CheckCircleIcon, EyeIcon, XCircleIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const days = ref(30)
const overview = ref(null)
const dailyVolume = ref([])
const byInstance = ref([])
const typeBreakdown = ref([])
const campaignStats = ref(null)

const maxTotal = computed(() => Math.max(...dailyVolume.value.map(d => d.total), 1))
const totalMsgs = computed(() => typeBreakdown.value.reduce((s, t) => s + t.count, 0))

onMounted(loadAll)

async function loadAll() {
    const p = { days: days.value }
    const [ov, dv, bi, tb, cs] = await Promise.all([
        webHttp.get('/client/reports/overview', { params: p }),
        webHttp.get('/client/reports/daily-volume', { params: p }),
        webHttp.get('/client/reports/by-instance', { params: p }),
        webHttp.get('/client/reports/type-breakdown', { params: p }),
        webHttp.get('/client/reports/campaign-stats', { params: p }),
    ])
    overview.value = ov.data.data
    dailyVolume.value = dv.data.data
    byInstance.value = bi.data.data
    typeBreakdown.value = tb.data.data
    campaignStats.value = cs.data.data
}

const barH = (val, max) => max > 0 ? Math.max(1, (val / max) * 100) : 0
const typePct = (count) => totalMsgs.value > 0 ? Math.round((count / totalMsgs.value) * 100) : 0
const fmt = (n) => n?.toLocaleString('en-IN') ?? '0'
</script>