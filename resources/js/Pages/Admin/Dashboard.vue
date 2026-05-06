<template>
    <AppLayout title="Super Admin">
        <div class="bg-gradient-to-r from-purple-700 to-indigo-700 rounded-2xl p-6 mb-6 text-white">
            <h1 class="text-xl font-bold">Platform Overview</h1>
            <p class="text-purple-200 text-sm mt-1">System-wide control panel</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard :icon="BuildingOfficeIcon" label="Total Clients" :value="stats.total_clients" color="purple" />
            <StatCard :icon="UsersIcon" label="Total Users" :value="stats.total_users" color="blue" />
            <StatCard :icon="DevicePhoneMobileIcon" label="Active Instances" :value="stats.active_instances"
                color="green" />
            <StatCard :icon="CreditCardIcon" label="Credits Sold" :value="stats.credits_sold" color="orange" />
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Recent clients -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Clients</h2>
                    <Link :href="route('super.clients')" class="text-sm text-blue-600 hover:underline">View all →</Link>
                </div>
                <div v-if="!recentClients.length" class="text-center py-8">
                    <p class="text-gray-400 text-sm">No clients yet</p>
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <div v-for="client in recentClients" :key="client.id" class="flex items-center gap-3 py-3">
                        <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-purple-700 text-sm font-bold">{{ client.name.charAt(0) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ client.name }}</p>
                            <p class="text-xs text-gray-400">{{ client.users_count }} users · {{
                                client.all_instances_count }} instances</p>
                        </div>
                        <span :class="client.is_active ? 'badge-active' : 'badge-suspended'">
                            {{ client.is_active ? 'Active' : 'Suspended' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Baileys service health -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">System Health</h2>
                    <button @click="refreshHealth" class="text-sm text-blue-600 hover:underline">Refresh</button>
                </div>
                <div class="space-y-3">
                    <!-- Baileys service -->
                    <div class="flex items-center justify-between p-3 rounded-xl"
                        :class="health.baileys?.online ? 'bg-green-50' : 'bg-red-50'">
                        <div class="flex items-center gap-3">
                            <div
                                :class="['w-2.5 h-2.5 rounded-full', health.baileys?.online ? 'bg-green-500' : 'bg-red-500']" />
                            <span class="text-sm font-medium"
                                :class="health.baileys?.online ? 'text-green-800' : 'text-red-800'">
                                Baileys Service
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold"
                                :class="health.baileys?.online ? 'text-green-700' : 'text-red-700'">
                                {{ health.baileys?.online ? 'Online' : 'Offline' }}
                            </p>
                            <p class="text-xs text-gray-400">{{ health.baileys?.sessions ?? 0 }} sessions</p>
                        </div>
                    </div>

                    <!-- Queue -->
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-xl">
                        <span class="text-sm font-medium text-blue-800">Queue Depth</span>
                        <span class="text-sm font-bold text-blue-700">{{ health.queue_depth ?? '—' }} jobs</span>
                    </div>

                    <!-- DB -->
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-xl">
                        <span class="text-sm font-medium text-green-800">Database</span>
                        <span class="text-sm font-bold text-green-700">OK</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import {
    BuildingOfficeIcon, UsersIcon, DevicePhoneMobileIcon, CreditCardIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    recentClients: { type: Array, default: () => [] },
    health: { type: Object, default: () => ({ baileys: { online: false, sessions: 0 }, queue_depth: 0 }) },
})

const refreshHealth = () => router.reload({ only: ['health'] })
</script>