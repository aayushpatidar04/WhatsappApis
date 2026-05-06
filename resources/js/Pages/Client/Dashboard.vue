<template>
    <AppLayout title="Client Dashboard">
        <!-- Welcome -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 rounded-2xl p-6 mb-6 text-white">
            <h1 class="text-xl font-bold">{{ $page.props.auth.client?.name ?? 'Client Dashboard' }}</h1>
            <div class="flex flex-wrap gap-4 mt-3">
                <div class="bg-white/20 rounded-lg px-4 py-2 text-sm">
                    <p class="text-indigo-200 text-xs">Client Credits</p>
                    <p class="font-bold text-lg">{{ stats.client_credit_balance }}</p>
                </div>
                <div class="bg-white/20 rounded-lg px-4 py-2 text-sm">
                    <p class="text-indigo-200 text-xs">Total Users</p>
                    <p class="font-bold text-lg">{{ stats.total_users }}</p>
                </div>
                <div class="bg-white/20 rounded-lg px-4 py-2 text-sm">
                    <p class="text-indigo-200 text-xs">Active Instances</p>
                    <p class="font-bold text-lg">{{ stats.active_instances }}</p>
                </div>
                <div class="bg-white/20 rounded-lg px-4 py-2 text-sm">
                    <p class="text-indigo-200 text-xs">My Instances</p>
                    <p class="font-bold text-lg">{{ stats.own_instances }}</p>
                </div>
            </div>
        </div>

        <!-- Info: Master Admin can create own instances -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800">
            <strong>Master Admin mode:</strong> You can create WhatsApp instances directly under your account
            without assigning them to sub-users. Credits are drawn from the client wallet.
            <Link :href="route('client.instances')" class="underline ml-1">Manage your instances →</Link>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Recent users -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Users</h2>
                    <Link :href="route('client.users')" class="text-sm text-blue-600 hover:underline">View all →</Link>
                </div>
                <div v-if="!recentUsers.length" class="text-center py-8">
                    <p class="text-gray-400 text-sm">No users yet</p>
                    <Link :href="route('client.users')" class="btn-primary btn-sm mt-3 inline-flex">Add User</Link>
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <div v-for="user in recentUsers" :key="user.id" class="flex items-center gap-3 py-3">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-blue-700 text-xs font-bold">{{ user.name.charAt(0) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ user.email }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-sm font-semibold text-gray-900">{{ user.credit_balance }}</p>
                            <p class="text-xs text-gray-400">credits</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instance overview -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Instance Overview</h2>
                    <Link :href="route('client.instances')" class="text-sm text-blue-600 hover:underline">Manage →
                    </Link>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded-xl">
                        <span class="text-sm text-green-700 font-medium">Active</span>
                        <span class="text-xl font-bold text-green-700">{{ stats.active_instances }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-xl">
                        <span class="text-sm text-yellow-700 font-medium">Pending / Disconnected</span>
                        <span class="text-xl font-bold text-yellow-700">{{ stats.pending_instances }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded-xl">
                        <span class="text-sm text-red-700 font-medium">Suspended / Expired</span>
                        <span class="text-xl font-bold text-red-700">{{ stats.suspended_instances }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded-xl">
                        <span class="text-sm text-blue-700 font-medium">Your own instances</span>
                        <span class="text-xl font-bold text-blue-700">{{ stats.own_instances }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

defineProps({
    stats: {
        type: Object,
        default: () => ({
            client_credit_balance: 0, total_users: 0,
            active_instances: 0, pending_instances: 0,
            suspended_instances: 0, own_instances: 0,
        }),
    },
    recentUsers: { type: Array, default: () => [] },
})
</script>