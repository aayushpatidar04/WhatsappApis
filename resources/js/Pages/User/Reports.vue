<template>
    <AppLayout title="Reports">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Reports & Analytics</h1>
                <p class="text-sm text-gray-400 mt-0.5">Message performance and campaign insights</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Period selector -->
                <select v-model="days" @change="loadAll" class="form-input text-sm w-32">
                    <option :value="7">Last 7 days</option>
                    <option :value="30">Last 30 days</option>
                    <option :value="90">Last 90 days</option>
                </select>
                <!-- Export -->
                <a :href="exportUrl" class="btn-secondary btn-sm">
                    <ArrowDownTrayIcon class="w-4 h-4" />
                    Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div v-if="overview" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard :icon="PaperAirplaneIcon" label="Messages Sent" :value="fmt(overview.messages.sent)" color="blue"
                :sub="`${overview.messages.delivery_rate}% delivery rate`" />
            <StatCard :icon="CheckCircleIcon" label="Delivered" :value="fmt(overview.messages.delivered)" color="green"
                :sub="`${overview.messages.read_rate}% read rate`" />
            <StatCard :icon="InboxIcon" label="Received" :value="fmt(overview.messages.received)" color="teal" />
            <StatCard :icon="XCircleIcon" label="Failed" :value="fmt(overview.messages.failed)" color="red"
                :sub="`${overview.messages.fail_rate}% fail rate`" />
        </div>

        <div class="grid lg:grid-cols-3 gap-6 mb-6">
            <!-- Daily Volume Line Chart -->
            <div class="lg:col-span-2 card">
                <div class="card-header">
                    <h2 class="card-title">Daily Message Volume</h2>
                    <div class="flex gap-3 text-xs text-gray-400">
                        <span class="flex items-center gap-1" @click="toggleBar('sent')" :class="visibleBars.sent
                            ? 'text-blue-500'
                            : 'text-gray-500'"><span
                                class="w-3 h-1 bg-blue-500 rounded-full inline-block" />Sent</span>
                        <span class="flex items-center gap-1" @click="toggleBar('delivered')" :class="visibleBars.delivered
                            ? 'text-green-500'
                            : 'text-gray-500'"><span
                                class="w-3 h-1 bg-green-500 rounded-full inline-block" />Delivered</span>
                        <span class="flex items-center gap-1" @click="toggleBar('failed')" :class="visibleBars.failed
                            ? 'text-red-500'
                            : 'text-gray-500'"><span
                                class="w-3 h-1 bg-red-400 rounded-full inline-block" />Failed</span>
                    </div>
                </div>
                <div v-if="!dailyVolume.length" class="h-48 flex items-center justify-center text-gray-300">
                    <p class="text-sm">No data for this period</p>
                </div>
                <div v-else class="h-48 flex items-end gap-px overflow-hidden">
                    <div v-for="(d, index) in dailyVolume" :key="d.date"
                        class="flex-1 h-full flex items-end justify-center gap-[2px] min-w-0 group cursor-default relative"
                        :class="index % 2 === 0 ? 'bg-gray-100' : 'bg-gray-200'"
                        :title="`${d.date}: ${d.total} sent, ${d.delivered} delivered, ${d.failed} failed`">
                        <!-- Sent -->
                        <div v-if="visibleBars.sent" class="bg-blue-400 rounded-t-sm w-1.5 md:w-2"
                            :style="{ height: barHeight(d.total, maxTotal) }"></div>
                        <!-- Delivered -->
                        <div v-if="visibleBars.delivered" class="bg-green-500 rounded-t-sm w-1.5 md:w-2"
                            :style="{ height: barHeight(d.delivered, maxTotal) }"></div>
                        <!-- Failed -->
                        <div v-if="visibleBars.failed" class="bg-red-400 rounded-t-sm w-1.5 md:w-2"
                            :style="{ height: barHeight(d.failed, maxTotal) }"></div>
                    </div>
                </div>
                <!-- X axis dates (every ~7th) -->
                <div class="flex justify-between mt-2 text-xs text-gray-400">
                    <span v-if="dailyVolume.length">{{ fmtDate(dailyVolume[0]?.date) }}</span>
                    <span v-if="dailyVolume.length">{{ fmtDate(dailyVolume[dailyVolume.length - 1]?.date) }}</span>
                </div>
            </div>

            <!-- Message Type Breakdown Donut -->
            <div class="card">
                <h2 class="card-title mb-4">Message Types</h2>
                <div v-if="!typeBreakdown.length" class="h-40 flex items-center justify-center text-gray-300 text-sm">No
                    data</div>
                <div v-else class="space-y-2">
                    <div v-for="t in typeBreakdown" :key="t.type">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span class="font-medium capitalize">{{ t.type }}</span>
                            <span>{{ t.count }} ({{ typePct(t.count) }}%)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full bg-blue-500 transition-all duration-500"
                                :style="{ width: typePct(t.count) + '%' }" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <!-- Per-Instance breakdown -->
            <div class="card">
                <h2 class="card-title mb-4">By Instance</h2>
                <div v-if="!byInstance.length" class="text-center py-8 text-gray-300 text-sm">No data</div>
                <div v-else class="divide-y divide-gray-50">
                    <div v-for="inst in byInstance" :key="inst.instance_id" class="py-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ inst.instance_name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ inst.phone }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900">{{ fmt(inst.total) }}</p>
                                <p class="text-xs text-green-600">{{ inst.delivery_rate }}% delivered</p>
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full bg-green-500" :style="{ width: inst.delivery_rate + '%' }" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hourly Heatmap -->
            <div class="card">
                <h2 class="card-title mb-4">Activity by Hour</h2>
                <div class="grid grid-cols-12 gap-1">
                    <div v-for="h in hourlyHeatmap" :key="h.hour" class="text-center">
                        <div :class="['rounded h-10 transition-colors', heatColor(h.count)]"
                            :title="`${h.label}: ${h.count} messages`" />
                        <p class="text-xs text-gray-400 mt-1">{{ h.hour % 6 === 0 ? h.hour : '' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3 text-xs text-gray-400">
                    <span>Less</span>
                    <span class="w-4 h-3 bg-blue-100 rounded" />
                    <span class="w-4 h-3 bg-blue-300 rounded" />
                    <span class="w-4 h-3 bg-blue-500 rounded" />
                    <span class="w-4 h-3 bg-blue-700 rounded" />
                    <span>More</span>
                </div>
            </div>
        </div>

        <!-- Campaign Funnel -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <div class="card mb-6">
                <h2 class="card-title mb-4">Campaign Funnel</h2>
                <div v-if="!campaignFunnel.length || !campaignFunnel[0].count"
                    class="text-center py-8 text-gray-300 text-sm">
                    No completed campaigns in this period.
                </div>
                <div v-else class="flex items-end justify-center gap-4">
                    <div v-for="(stage, idx) in campaignFunnel" :key="stage.stage" class="flex-1 text-center">
                        <div class="flex justify-center mb-2">
                            <div :class="['rounded-xl transition-all duration-700 w-full', funnelColors[idx]]"
                                :style="{ height: funnelHeight(stage.count) + 'px' }" />
                        </div>
                        <p class="text-lg font-bold text-gray-900">{{ fmt(stage.count) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ stage.stage }}</p>
                        <p v-if="idx > 0 && campaignFunnel[0].count" class="text-xs text-blue-600 font-medium">
                            {{ Math.round((stage.count / campaignFunnel[0].count) * 100) }}%
                        </p>
                        <p v-else class="text-xs text-gray-400 mt-0.5">&nbsp;</p>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { ArrowDownTrayIcon, PaperAirplaneIcon, CheckCircleIcon, InboxIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import { reportApi } from '@/composables/useApi'

const days = ref(30)
const overview = ref(null)
const dailyVolume = ref([])
const byInstance = ref([])
const typeBreakdown = ref([])
const hourlyHeatmap = ref([])
const campaignFunnel = ref([])

const exportUrl = computed(() => reportApi.exportUrl(days.value))
const maxTotal = computed(() => {
    return Math.max(
        ...dailyVolume.value.map(d => Number(d.total) || 0),
        1
    )
})
const totalMsgs = computed(() => typeBreakdown.value.reduce((s, t) => s + t.count, 0))

const funnelColors = ['bg-blue-500', 'bg-blue-400', 'bg-indigo-400', 'bg-green-500']

onMounted(() => loadAll())

async function loadAll() {
    const params = { days: days.value }
    const [ov, dv, bi, tb, hh, cf] = await Promise.all([
        reportApi.overview(params),
        reportApi.dailyVolume(params),
        reportApi.byInstance(params),
        reportApi.typeBreakdown(params),
        reportApi.hourlyHeatmap(params),
        reportApi.campaignFunnel(params),
    ])
    overview.value = ov.data.data
    dailyVolume.value = dv.data.data
    byInstance.value = bi.data.data
    typeBreakdown.value = tb.data.data
    hourlyHeatmap.value = hh.data.data
    campaignFunnel.value = cf.data.data
}

const chartHeight = 180

const barHeight = (val, max) => {
    const value = Math.max(Number(val) || 0, 0)

    if (!max || value <= 0) {
        return '0px'
    }

    const scaled = (value / max) * chartHeight

    return `${Math.max(6, scaled)}px`
}

const visibleBars = ref({
    sent: true,
    delivered: true,
    failed: true,
})

const toggleBar = (key) => {
    visibleBars.value[key] = !visibleBars.value[key]
}

const typePct = (count) => totalMsgs.value > 0 ? Math.round((count / totalMsgs.value) * 100) : 0
const funnelHeight = (count) => {
    const max = campaignFunnel.value[0]?.count ?? 1
    return Math.max(5, (count / max) * 160)
}

const heatColor = (count) => {
    if (!count) return 'bg-gray-100'
    const max = Math.max(...hourlyHeatmap.value.map(h => h.count), 1)
    const pct = count / max
    if (pct < 0.25) return 'bg-blue-100'
    if (pct < 0.5) return 'bg-blue-300'
    if (pct < 0.75) return 'bg-blue-500'
    return 'bg-blue-700'
}

const fmt = (n) => n?.toLocaleString('en-IN') ?? '0'
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : ''
</script>