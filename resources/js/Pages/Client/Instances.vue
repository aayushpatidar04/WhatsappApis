<template>
    <AppLayout title="Instance Manager">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">All Instances</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ instances.total }} total ·
                    <span class="text-blue-600 font-medium">{{ ownCount }} yours (client-owned)</span>
                </p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                Create Instance
            </button>
        </div>

        <!-- Client owns instances directly banner -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 text-sm text-indigo-800">
            <strong>Master Admin instances:</strong> Instances you create here are owned directly by your client account
            and draw credits from the <strong>client wallet ({{ clientCredits }} credits)</strong>.
            They show with a
            <span
                class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-700 text-xs font-medium px-2 py-0.5 rounded-full">
                ★ Client
            </span>
            badge. User-owned instances are listed below for visibility.
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-2 mb-4">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" :class="[
                'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                activeTab === tab.key
                    ? 'bg-blue-600 text-white'
                    : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'
            ]">
                {{ tab.label }}
                <span class="ml-1.5 text-xs opacity-75">({{ tab.count }})</span>
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="!filteredInstances.length" class="card text-center py-16">
            <DevicePhoneMobileIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <p class="text-gray-400 text-sm">No instances in this category.</p>
        </div>

        <!-- Grid -->
        <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="inst in filteredInstances" :key="inst.id" class="card hover:shadow-md transition-shadow">
                <!-- Header -->
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <div
                            :class="['w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center', statusBg(inst.status)]">
                            <DevicePhoneMobileIcon class="w-4 h-4 text-white" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 text-sm truncate">{{ inst.name }}</p>
                            <p class="text-xs text-gray-400 font-mono truncate">{{ inst.phone_number ?? 'Not connected'
                                }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                        <span :class="badgeClass(inst.status)">{{ inst.status }}</span>
                        <span v-if="inst.is_own"
                            class="text-xs bg-indigo-100 text-indigo-700 font-medium px-1.5 py-0.5 rounded-full">
                            ★ Client
                        </span>
                    </div>
                </div>

                <!-- Instance token -->
                <div class="bg-gray-50 rounded-lg px-3 py-2 mb-3">
                    <p class="text-xs text-gray-400 mb-0.5">Instance Token</p>
                    <code class="text-xs font-mono text-gray-600 truncate block">
            {{ inst.instance_token.slice(0, 12) }}••••••••{{ inst.instance_token.slice(-4) }}
          </code>
                </div>

                <!-- Credit row -->
                <div class="flex justify-between text-xs text-gray-500 mb-3 px-1">
                    <span>Credits: <strong class="text-gray-900">{{ inst.credits_assigned }}</strong></span>
                    <span>Remaining: <strong :class="inst.credits_remaining > 0 ? 'text-green-600' : 'text-red-500'">{{
                            inst.credits_remaining }}</strong></span>
                    <span>Days: <strong :class="expiryClass(inst.days_until_expiry)">{{ inst.days_until_expiry ?? '—'
                            }}</strong></span>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-3 border-t border-gray-100">
                    <button class="btn-secondary btn-sm flex-1" @click="viewDetails(inst)">Details</button>
                    <button v-if="inst.status === 'pending' || inst.status === 'disconnected'"
                        class="btn-primary btn-sm flex-1" disabled>
                        Connect (P2)
                    </button>
                    <button v-if="inst.status === 'active'"
                        class="btn-sm flex-1 bg-orange-50 text-orange-700 border border-orange-200 rounded-lg text-sm font-medium"
                        disabled>
                        Disconnect (P2)
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Instance Modal (reuses user component) -->
        <CreateInstanceModal :show="showCreate" :available-credits="clientCredits" @close="showCreate = false"
            @created="onCreated" />
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import CreateInstanceModal from '@/Components/Instance/CreateInstanceModal.vue'
import { PlusIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    instances: { type: Object, default: () => ({ data: [], total: 0 }) },
})

const page = usePage()
const clientCredits = computed(() => page.props.auth.client?.credit_balance ?? 0)
const showCreate = ref(false)
const activeTab = ref('all')

const ownCount = computed(() => props.instances.data.filter(i => i.is_own).length)

const tabs = computed(() => [
    { key: 'all', label: 'All', count: props.instances.data.length },
    { key: 'own', label: 'Mine', count: ownCount.value },
    { key: 'active', label: 'Active', count: props.instances.data.filter(i => i.status === 'active').length },
])

const filteredInstances = computed(() => {
    if (activeTab.value === 'own') return props.instances.data.filter(i => i.is_own)
    if (activeTab.value === 'active') return props.instances.data.filter(i => i.status === 'active')
    return props.instances.data
})

const onCreated = () => { showCreate.value = false; router.reload({ only: ['instances'] }) }
const viewDetails = (inst) => alert(`Token: ${inst.instance_token}`)

const statusBg = (s) => ({ active: 'bg-green-500', pending: 'bg-yellow-500', disconnected: 'bg-gray-400', suspended: 'bg-orange-500', expired: 'bg-red-500' }[s] ?? 'bg-gray-400')
const badgeClass = (s) => ({ active: 'badge-active', pending: 'badge-pending', disconnected: 'badge-disconnected', suspended: 'badge-suspended', expired: 'badge-expired' }[s] ?? 'badge')
const expiryClass = (d) => d == null ? 'text-gray-400' : d <= 3 ? 'text-red-600' : d <= 7 ? 'text-orange-500' : 'text-gray-900'
</script>