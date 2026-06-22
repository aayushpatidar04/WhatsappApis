<template>
    <AppLayout title="Dashboard">
        <!-- Welcome banner -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 mb-6 text-white">
            <h1 class="text-xl font-bold">Good {{ greeting }}, {{ firstName }}! 👋</h1>
            <p class="text-blue-200 text-sm mt-1">
                You have <strong class="text-white">{{ $page.props.auth.user.credit_balance }}</strong>
                instance credit{{ $page.props.auth.user.credit_balance !== 1 ? 's' : '' }} available.
            </p>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <StatCard :icon="DevicePhoneMobileIcon" label="Total Instances" :value="stats.total_instances"
                color="blue" />
            <StatCard :icon="CheckCircleIcon" label="Active" :value="stats.active_instances" color="green" />
            <StatCard :icon="PaperAirplaneIcon" label="Sent Today" :value="stats.messages_today" color="purple" />
            <!-- <StatCard :icon="InboxIcon" label="Received Today" :value="stats.messages_received" color="teal" /> -->
        </div>

        <!-- Secondary stats -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="card flex items-center gap-4">
                <div class="p-3 bg-green-100 rounded-xl">
                    <ChartBarIcon class="w-5 h-5 text-green-600" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.delivery_rate }}%</p>
                    <p class="text-xs text-gray-400">Delivery rate</p>
                </div>
            </div>
            <div class="card flex items-center gap-4">
                <div class="p-3 bg-red-100 rounded-xl">
                    <XCircleIcon class="w-5 h-5 text-red-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ stats.failed_today }}</p>
                    <p class="text-xs text-gray-400">Failed today</p>
                </div>
            </div>
            <div class="card flex items-center gap-4">
                <div class="p-3 bg-orange-100 rounded-xl">
                    <CreditCardIcon class="w-5 h-5 text-orange-500" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ $page.props.auth.user.credit_balance }}</p>
                    <p class="text-xs text-gray-400">Credits available</p>
                </div>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Instances quick view -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">My Instances</h2>
                        <Link :href="route('user.instances')" class="text-sm text-blue-600 hover:underline">
                            View all →
                        </Link>
                    </div>

                    <!-- Empty state -->
                    <div v-if="instances.length == 0" class="text-center py-12">
                        <DevicePhoneMobileIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
                        <p class="text-gray-400 text-sm">No instances yet</p>
                        <Link :href="route('user.instances')" class="btn-primary btn-sm mt-4 inline-flex">
                            Create your first instance
                        </Link>
                    </div>

                    <!-- Instance list -->
                    <div v-else class="space-y-3">
                        <div v-for="inst in instances" :key="inst.id"
                            class="flex items-center gap-4 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                            <div
                                :class="['w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0', statusBg(inst.status)]">
                                <DevicePhoneMobileIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 text-sm truncate">{{ inst.name }}</p>
                                <p class="text-xs text-gray-400 font-mono truncate">
                                    {{ inst.phone_number ?? 'Not connected' }}
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span :class="badgeClass(inst.status)">{{ inst.status }}</span>
                                <p class="text-xs text-gray-400 mt-1">{{ inst.days_until_expiry ?? '—' }}d left</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="space-y-6">
                <!-- Quick send -->
                <!-- Quick send -->
                <div class="card">
                    <h2 class="card-title mb-3">Quick Actions</h2>
                    <div class="space-y-2">
                        <Link :href="route('user.send')"
                            class="flex items-center gap-3 p-3 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors">
                            <PaperAirplaneIcon class="w-5 h-5 text-blue-600 flex-shrink-0" />
                            <span class="text-sm font-medium text-blue-700">Send Message</span>
                        </Link>
                        <!-- <Link :href="route('user.inbox')"
                            class="flex items-center gap-3 p-3 bg-green-50 hover:bg-green-100 rounded-xl transition-colors">
                            <InboxIcon class="w-5 h-5 text-green-600 flex-shrink-0" />
                            <span class="text-sm font-medium text-green-700">View Inbox</span>
                        </Link> -->
                        <Link :href="route('user.tokens')"
                            class="flex items-center gap-3 p-3 bg-purple-50 hover:bg-purple-100 rounded-xl transition-colors">
                            <KeyIcon class="w-5 h-5 text-purple-600 flex-shrink-0" />
                            <span class="text-sm font-medium text-purple-700">API Tokens</span>
                        </Link>
                        <Link :href="route('user.webhooks')"
                            class="flex items-center gap-3 p-3 bg-orange-50 hover:bg-orange-100 rounded-xl transition-colors">
                            <GlobeAltIcon class="w-5 h-5 text-orange-600 flex-shrink-0" />
                            <span class="text-sm font-medium text-orange-700">Webhooks</span>
                        </Link>
                    </div>
                </div>

                <!-- API quick-start -->
                <div class="card">
                    <h2 class="card-title mb-3">External API</h2>
                    <div class="bg-gray-900 rounded-xl p-3 text-xs font-mono text-green-400 overflow-x-auto">
                        <p class="text-gray-500 mb-1"># Send from your app:</p>
                        <p>curl -X POST \</p>
                        <p class="pl-2 text-yellow-300">{{ appUrl }}/api/gateway/send/text \</p>
                        <p class="pl-2">-H "Authorization: Bearer <span class="text-yellow-400">&lt;token&gt;</span>" \
                        </p>
                        <p class="pl-2">-H "X-Instance-Token: <span class="text-blue-400">&lt;token&gt;</span>" \</p>
                        <p class="pl-2">-d '{"to":"91XXX","message":"Hi!"}'</p>
                    </div>
                </div>

                <!-- Credit info -->
                <div class="card">
                    <h2 class="card-title mb-4">Credit Balance</h2>
                    <div class="text-center py-4">
                        <p class="text-4xl font-bold text-gray-900">{{ $page.props.auth.user.credit_balance }}</p>
                        <p class="text-sm text-gray-400 mt-1">instance credits</p>
                    </div>
                    <div class="text-xs text-gray-400 bg-gray-50 rounded-lg p-3 space-y-1">
                        <p>• 1 credit = 1 instance × 1 month</p>
                        <p>• Credits assigned to instances are consumed daily</p>
                        <p>• Contact your administrator to top up</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import {
    DevicePhoneMobileIcon, CheckCircleIcon, PaperAirplaneIcon,
    InboxIcon, ChartBarIcon, XCircleIcon, CreditCardIcon,
    KeyIcon, GlobeAltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_instances: 0, active_instances: 0,
            messages_today: 0, messages_received: 0,
            delivery_rate: 0, failed_today: 0,
        }),
    },
    instances: { type: Array, default: () => [] },
})

const page = usePage()
const appUrl = window.location.origin

const firstName = computed(() => page.props.auth.user.name.split(' ')[0])
const greeting = computed(() => {
    const h = new Date().getHours()
    if (h < 12) return 'morning'
    if (h < 17) return 'afternoon'
    return 'evening'
})

const statusBg = (s) => ({
    active: 'bg-green-500', pending: 'bg-yellow-500',
    disconnected: 'bg-gray-400', suspended: 'bg-orange-500', expired: 'bg-red-500',
}[s] ?? 'bg-gray-400')

const badgeClass = (s) => ({
    active: 'badge-active', pending: 'badge-pending',
    disconnected: 'badge-disconnected', suspended: 'badge-suspended', expired: 'badge-expired',
}[s] ?? 'badge')
</script>