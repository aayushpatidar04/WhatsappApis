<template>
    <AppLayout title="Instance Manager">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">All Instances</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    <span v-if="loading">Loading…</span>
                    <span v-else>
                        {{ instances.length }} total ·
                        <span class="text-green-600 font-medium">{{ activeCount }} active</span> ·
                        <span class="text-indigo-600 font-medium">{{ ownCount }} yours</span>
                    </span>
                </p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                Create Instance
            </button>
        </div>

        <!-- Client-owned info banner -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 text-sm text-indigo-800">
            <strong>Master Admin:</strong> Instances you create here are owned by your client account
            (drawn from client wallet — <strong>{{ clientCredits }} credits</strong>).
            They show with a <span
                class="bg-indigo-100 text-indigo-700 text-xs font-medium px-1.5 py-0.5 rounded-full">★ Yours</span>
            badge.
            Status updates in real time via Pusher — no page reload needed.
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-2 mb-5 flex-wrap">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
                :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                    activeTab == tab.key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']">
                {{ tab.label }} <span class="ml-1 opacity-70 text-xs">({{ tab.count }})</span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="n in 6" :key="n" class="card animate-pulse h-48 bg-gray-50" />
        </div>

        <!-- Empty -->
        <div v-else-if="!filteredInstances.length" class="card text-center py-12">
            <DevicePhoneMobileIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <p class="text-gray-400 text-sm">No instances in this filter.</p>
        </div>

        <!-- Grid — all status changes via Pusher, no reloads -->
        <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <InstanceCardLive v-for="inst in filteredInstances" :key="inst.id" :instance="inst" @connect="openQr(inst)"
                @disconnect="handleDisconnect(inst)" @delete="handleDelete(inst)" @details="handleDetails(inst)" />
        </div>

        <QrCodeModal :show="!!connectingInstance" :instance="connectingInstance" @close="connectingInstance = null"
            @connected="onConnected" />
        <CreateInstanceModal :show="showCreate" :available-credits="clientCredits" @close="showCreate = false"
            @created="onInstanceCreated" />
        <InstanceDetailsDrawer :instance="detailsInstance" :credit-balance="clientCredits"
            @close="detailsInstance = null" @credits-added="onCreditsAdded" />

    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import InstanceCardLive from '@/Components/Instance/InstanceCardLive.vue'
import QrCodeModal from '@/Components/Instance/QrCodeModal.vue'
import CreateInstanceModal from '@/Components/Instance/CreateInstanceModal.vue'
import InstanceDetailsDrawer from '@/Components/Instance/InstanceDetailsDrawer.vue'
import { PlusIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'
import { clientInstanceApi } from '@/composables/useApi'
import { useInstances } from '@/composables/useInstances'

const { instances, loading, error, fetchInstances, addInstance, removeInstance, refreshInstance, activeCount } = useInstances('client')
const page = usePage()
const activeTab = ref('all')
const showCreate = ref(false)
const connectingInstance = ref(null)
const detailsInstance = ref(null)

const clientCredits = computed(() => page.props.auth.client?.credit_balance ?? 0)
const ownCount = computed(() => instances.value.filter(i => i.is_own).length)

const tabs = computed(() => [
    { key: 'all', label: 'All', count: instances.value.length },
    { key: 'own', label: 'Mine', count: ownCount.value },
    { key: 'active', label: 'Active', count: activeCount.value },
    { key: 'issues', label: 'Issues', count: instances.value.filter(i => ['disconnected', 'suspended', 'expired'].includes(i.status)).length },
])

const filteredInstances = computed(() => {
    switch (activeTab.value) {
        case 'own': return instances.value.filter(i => i.is_own)
        case 'active': return instances.value.filter(i => i.status == 'active')
        case 'issues': return instances.value.filter(i => ['disconnected', 'suspended', 'expired'].includes(i.status))
        default: return instances.value
    }
})

// Client admin fetches from /client/instances
onMounted(() => fetchInstances())

const openQr = (inst) => { connectingInstance.value = inst }
const handleDetails = (inst) => { detailsInstance.value = inst }
const onConnected = () => { connectingInstance.value = null }
const onInstanceCreated = (newInst) => { showCreate.value = false; addInstance(newInst) }
const onCreditsAdded = (updated) => { detailsInstance.value = null; if (updated) refreshInstance(updated) }

const handleDisconnect = async (inst) => {
    if (!confirm(`Disconnect "${inst.name}"?`)) return
    try { await clientInstanceApi.logout(inst.id) } catch (e) { alert(e.response?.data?.message ?? 'Failed.') }
}

const handleDelete = async (inst) => {
    if (!confirm(`Delete "${inst.name}"? Unused credits returned to client wallet.`)) return
    try { await clientInstanceApi.delete(inst.id); removeInstance(inst.id) } catch (e) { alert(e.response?.data?.message ?? 'Failed.') }
}
</script>