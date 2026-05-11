<template>
    <AppLayout title="System Monitor">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-900">System Monitor</h1>
            <button @click="refresh" class="btn-secondary btn-sm" :disabled="refreshing">
                <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
                Refresh
            </button>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard :icon="ServerIcon" label="Baileys Service" :value="health.online ? 'Online' : 'Offline'"
                :color="health.online ? 'green' : 'red'" />
            <StatCard :icon="DevicePhoneMobileIcon" label="Active Sessions" :value="health.sessions ?? 0"
                color="blue" />
            <StatCard :icon="QueueListIcon" label="Queue Depth" :value="queueDepth" color="orange" />
            <StatCard :icon="ClockIcon" label="Uptime" :value="uptimeLabel" color="purple" />
        </div>

        <div class="grid lg:grid-cols-2 gap-6">

            <!-- Baileys health detail -->
            <div class="card">
                <h2 class="card-title mb-4">Baileys Service</h2>
                <div class="space-y-3">
                    <!-- Online/Offline -->
                    <div class="flex items-center justify-between p-3 rounded-xl"
                        :class="health.online ? 'bg-green-50' : 'bg-red-50'">
                        <div class="flex items-center gap-3">
                            <span
                                :class="['w-3 h-3 rounded-full', health.online ? 'bg-green-500 animate-pulse' : 'bg-red-500']" />
                            <span class="text-sm font-medium"
                                :class="health.online ? 'text-green-800' : 'text-red-800'">
                                {{ health.online ? 'Service Online' : 'Service Offline' }}
                            </span>
                        </div>
                        <span class="text-xs" :class="health.online ? 'text-green-600' : 'text-red-600'">
                            {{ health.online ? `v${health.version ?? '2.0.0'}` : 'Unreachable' }}
                        </span>
                    </div>

                    <!-- Session status breakdown -->
                    <div v-if="health.statusCounts" class="space-y-2">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Session Breakdown</p>
                        <div v-for="(count, status) in health.statusCounts" :key="status"
                            class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600 capitalize">{{ status.replace('_', ' ') }}</span>
                            <span class="text-sm font-bold text-gray-900">{{ count }}</span>
                        </div>
                    </div>

                    <!-- Memory -->
                    <div v-if="health.memory" class="grid grid-cols-2 gap-2 text-xs">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-gray-400">Heap Used</p>
                            <p class="font-semibold text-gray-800">{{ formatBytes(health.memory.heapUsed) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-gray-400">Heap Total</p>
                            <p class="font-semibold text-gray-800">{{ formatBytes(health.memory.heapTotal) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queue health -->
            <div class="card">
                <h2 class="card-title mb-4">Queue Status</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 rounded-xl"
                        :class="queueDepth > 100 ? 'bg-red-50' : queueDepth > 20 ? 'bg-yellow-50' : 'bg-green-50'">
                        <span class="text-sm font-medium"
                            :class="queueDepth > 100 ? 'text-red-700' : queueDepth > 20 ? 'text-yellow-700' : 'text-green-700'">
                            {{ queueDepth === 0 ? 'Queue Empty' : `${queueDepth} jobs pending` }}
                        </span>
                        <span class="text-lg font-bold"
                            :class="queueDepth > 100 ? 'text-red-700' : queueDepth > 20 ? 'text-yellow-700' : 'text-green-600'">
                            {{ queueDepth }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div v-for="stat in queueStats" :key="stat.label" class="bg-gray-50 rounded-lg p-2">
                            <p class="text-lg font-bold text-gray-900">{{ stat.count }}</p>
                            <p class="text-gray-400">{{ stat.label }}</p>
                        </div>
                    </div>
                </div>

                <!-- Instance expiry alerts -->
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Expiry Alerts</p>
                    <div v-if="!expiringInstances.length" class="text-xs text-gray-400 bg-gray-50 rounded-lg p-3">
                        No instances expiring in the next 7 days.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="inst in expiringInstances" :key="inst.id"
                            class="flex items-center justify-between p-2 bg-orange-50 rounded-lg text-xs">
                            <span class="text-orange-800 font-medium truncate">{{ inst.name }}</span>
                            <span class="text-orange-600 font-bold ml-2 flex-shrink-0">{{ inst.days_until_expiry }}d
                                left</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import {
    ServerIcon, DevicePhoneMobileIcon, QueueListIcon,
    ClockIcon, ArrowPathIcon,
} from '@heroicons/vue/24/outline'
import { healthApi, instanceApi } from '@/composables/useApi'
import axios from 'axios'

const health = ref({ online: false, sessions: 0 })
const queueDepth = ref(0)
const queueStats = ref([])
const expiringInstances = ref([])
const refreshing = ref(false)

let pollInterval = null

const uptimeLabel = computed(() => {
    const s = health.value.uptime ?? 0
    if (s < 60) return `${s}s`
    if (s < 3600) return `${Math.floor(s / 60)}m`
    return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`
})

async function refresh() {
    refreshing.value = true
    try {
        const [healthRes, queueRes, expiringRes] = await Promise.all([
            healthApi.superBaileys(),
            axios.get('/super/baileys-health'),
            instanceApi.list({ expiring_days: 7, per_page: 10 }),
        ])
        health.value = healthRes.data?.data ?? { online: false }
        queueDepth.value = queueRes.data?.data?.queue_depth ?? 0
        queueStats.value = queueRes.data?.data?.queue_stats ?? []
        expiringInstances.value = (expiringRes.data?.data?.data ?? [])
            .filter(i => i.days_until_expiry != null && i.days_until_expiry <= 7)
    } catch (e) {
        health.value = { online: false }
    } finally {
        refreshing.value = false
    }
}

onMounted(() => {
    refresh()
    pollInterval = setInterval(refresh, 30000) // refresh every 30s
})

onUnmounted(() => clearInterval(pollInterval))

const formatBytes = (bytes) => {
    if (!bytes) return '—'
    const mb = bytes / 1024 / 1024
    return `${mb.toFixed(1)} MB`
}
</script>