<template>
    <AppLayout title="My Instances">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">WhatsApp Instances</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    <span v-if="loading">Loading…</span>
                    <span v-else>{{ instances.length }} instances · {{ creditBalance }} credits available</span>
                </p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                New Instance
            </button>
        </div>

        <!-- External API tip -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700">
            <strong>External API:</strong> To send messages from your own app, use
            <code class="bg-blue-100 px-1 rounded font-mono text-xs">Authorization: Bearer &lt;token&gt;</code>
            + <code class="bg-blue-100 px-1 rounded font-mono text-xs">X-Instance-Token: &lt;token&gt;</code>
            on <code class="bg-blue-100 px-1 rounded font-mono text-xs">POST /api/gateway/send/text</code>.
            Get your token from <Link :href="route('user.tokens')" class="underline font-medium">API Tokens</Link>.
        </div>

        <!-- Empty state -->
        <div v-if="loading" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="n in 3" :key="n" class="card animate-pulse h-48 bg-gray-50" />
        </div>

        <div v-else-if="!instanceList.length" class="card text-center py-16">
            <DevicePhoneMobileIcon class="w-16 h-16 text-gray-200 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-700">No instances yet</h3>
            <p class="text-gray-400 text-sm mt-2 max-w-sm mx-auto">
                Create an instance, scan the QR code, then send messages via the API.
            </p>
            <button class="btn-primary mt-6" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />Create First Instance
            </button>
        </div>

        <!-- Instance grid -->
        <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <InstanceCardLive v-for="inst in instanceList" :key="inst.id" :instance="inst" @connect="openQr(inst)"
                @disconnect="handleDisconnect(inst)" @delete="handleDelete(inst)" @details="handleDetails(inst)" />
        </div>

        <!-- QR Modal -->
        <QrCodeModal :show="!!connectingInstance" :instance="connectingInstance" @close="connectingInstance = null"
            @connected="onConnected" />

        <!-- Create Modal -->
        <CreateInstanceModal :show="showCreate" :available-credits="creditBalance" @close="showCreate = false"
            @created="onInstanceCreated" />

        <!-- Details Drawer -->
        <InstanceDetailsDrawer :instance="detailsInstance" :credit-balance="creditBalance"
            @close="detailsInstance = null" @credits-added="onCreditsAdded" />

    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import InstanceCardLive from '@/Components/Instance/InstanceCardLive.vue'
import QrCodeModal from '@/Components/Instance/QrCodeModal.vue'
import CreateInstanceModal from '@/Components/Instance/CreateInstanceModal.vue'
import InstanceDetailsDrawer from '@/Components/Instance/InstanceDetailsDrawer.vue'
import { PlusIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'
import { instanceApi } from '@/composables/useApi'  // ← all calls go to web.php
import { useInstances } from '@/composables/useInstances'

const props = defineProps({
    instances: { type: Object, default: () => ({ data: [] }) },
})

const { instances: instanceList, loading, error, fetchInstances, addInstance, removeInstance, refreshInstance } = useInstances()

const page = usePage()
const showCreate = ref(false)
const connectingInstance = ref(null)
const detailsInstance = ref(null)
const creditBalance = computed(() => page.props.auth.user.credit_balance)

onMounted(() => fetchInstances())

const openQr = (inst) => { connectingInstance.value = inst }
const handleDetails = (inst) => { detailsInstance.value = inst }

const onConnected = (payload) => {
    const inst = instanceList.value.find(i => i.id === connectingInstanceId.value)

    if (inst) {
        inst.status = 'active'
        inst.phone_number = payload?.phone_number ?? inst.phone_number

        if (!inst.activated_at) {
            inst.activated_at = new Date().toISOString()
        }
    }
    connectingInstanceId.value = null
}
const onInstanceCreated = (newInst) => { showCreate.value = false; addInstance(newInst) }
const onCreditsAdded = (updated) => { detailsInstance.value = null; if (updated) refreshInstance(updated) }

const handleDisconnect = async (inst) => {
    if (!confirm(`Disconnect "${inst.name}"?`)) return
    try {
        const res = await instanceApi.logout(inst.id)  // POST /dashboard/instances/{id}/logout

        if (!res.data.success) {
            alert(res.data.message ?? 'Failed to disconnect.')
            return
        }
        const index = instanceList.value.findIndex(i => i.id === inst.id)
        if (index !== -1) {
            instanceList.value[index] = {
                ...instanceList.value[index],
                status: 'pending', // or 'disconnected' depending on your backend logic
                phone_number: null
            }
        }
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to disconnect.')
    }

}

const handleDelete = async (inst) => {
    if (!confirm(`Delete "${inst.name}"? Unused credits will be returned.`)) return
    try {
        await instanceApi.delete(inst.id)  // DELETE /dashboard/instances/{id}
        // ✅ remove from array
        instanceList.value = instanceList.value.filter(i => i.id !== inst.id)
        // router.reload({ only: ['instances'] })
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to delete.')
    }
}
</script>