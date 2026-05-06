<template>
    <AppLayout title="My Instances">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">WhatsApp Instances</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ instances.data?.length ?? 0 }} instance{{ instances.data?.length !== 1 ? 's' : '' }}
                    · {{ creditBalance }} credit{{ creditBalance !== 1 ? 's' : '' }} available
                </p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                New Instance
            </button>
        </div>

        <!-- Token usage tip -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700">
            <strong>How to use instance tokens:</strong> Every instance has a unique
            <code class="bg-blue-100 px-1 rounded font-mono text-xs">instance_token</code>.
            Use it in the <code class="bg-blue-100 px-1 rounded font-mono text-xs">X-Instance-Token</code>
            header alongside your API token to route messages through that specific WhatsApp number.
        </div>

        <!-- Empty state -->
        <div v-if="!instances.data?.length" class="card text-center py-16">
            <DevicePhoneMobileIcon class="w-16 h-16 text-gray-200 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-700">No instances yet</h3>
            <p class="text-gray-400 text-sm mt-2 max-w-sm mx-auto">
                Create a WhatsApp instance, scan the QR code with your phone, and start sending messages via API.
            </p>
            <button class="btn-primary mt-6" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                Create First Instance
            </button>
        </div>

        <!-- Instance grid -->
        <div v-else class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <InstanceCard v-for="inst in instances.data" :key="inst.id" :instance="inst" @connect="handleConnect"
                @disconnect="handleDisconnect" @details="handleDetails">
                <template #actions>
                    <button class="btn-secondary btn-sm flex-1" @click="handleDetails(inst)">
                        Details
                    </button>
                    <button v-if="inst.status === 'pending' || inst.status === 'disconnected'"
                        class="btn-primary btn-sm flex-1" @click="handleConnect(inst)" disabled>
                        Connect (Phase 2)
                    </button>
                    <button v-if="inst.status === 'active'"
                        class="btn-sm flex-1 bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 rounded-lg text-sm font-medium"
                        @click="handleDisconnect(inst)" disabled>
                        Disconnect (Phase 2)
                    </button>
                    <button class="btn-sm p-2 text-red-500 hover:bg-red-50 rounded-lg border border-red-200"
                        @click="deleteInstance(inst)" title="Delete instance">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </template>
            </InstanceCard>
        </div>

        <!-- Modals -->
        <CreateInstanceModal :show="showCreate" :available-credits="creditBalance" @close="showCreate = false"
            @created="onInstanceCreated" />

        <!-- Details drawer (slide-over) -->
        <Teleport to="body">
            <Transition name="slide">
                <div v-if="detailsInstance" class="fixed inset-0 z-50 flex justify-end">
                    <div class="absolute inset-0 bg-black/40" @click="detailsInstance = null" />
                    <div class="relative bg-white w-full max-w-md shadow-2xl overflow-y-auto">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Instance Details</h2>
                            <button @click="detailsInstance = null"
                                class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="p-6 space-y-6">
                            <!-- Status -->
                            <div>
                                <p class="text-xs text-gray-400 font-medium mb-2">STATUS</p>
                                <span :class="badgeClass(detailsInstance.status)">{{ detailsInstance.status }}</span>
                            </div>

                            <!-- Full token -->
                            <div>
                                <p class="text-xs text-gray-400 font-medium mb-2">INSTANCE TOKEN (Routing Key)</p>
                                <div class="bg-gray-50 rounded-xl p-3">
                                    <code class="text-xs font-mono text-gray-700 break-all">{{ detailsInstance.instance_token
                                }}</code>
                                </div>
                                <p class="text-xs text-gray-400 mt-2">
                                    Use this as the <code class="bg-gray-100 px-1 rounded">X-Instance-Token</code>
                                    header in API
                                    calls.
                                </p>
                            </div>

                            <!-- Credit info -->
                            <div>
                                <p class="text-xs text-gray-400 font-medium mb-2">CREDITS</p>
                                <div class="grid grid-cols-3 gap-3 text-center">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-lg font-bold text-gray-900">{{ detailsInstance.credits_assigned
                                            }}</p>
                                        <p class="text-xs text-gray-400">Assigned</p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-lg font-bold text-green-600">{{ detailsInstance.credits_remaining
                                            }}</p>
                                        <p class="text-xs text-gray-400">Remaining</p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-lg font-bold"
                                            :class="expiryColor(detailsInstance.days_until_expiry)">
                                            {{ detailsInstance.days_until_expiry ?? '—' }}
                                        </p>
                                        <p class="text-xs text-gray-400">Days left</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Add credits -->
                            <div>
                                <p class="text-xs text-gray-400 font-medium mb-2">ADD CREDITS</p>
                                <div class="flex gap-2">
                                    <input v-model.number="addCreditsAmount" type="number" min="1" :max="creditBalance"
                                        class="form-input" placeholder="e.g. 3" />
                                    <button class="btn-primary btn-sm whitespace-nowrap"
                                        :disabled="!addCreditsAmount || addCreditsAmount < 1" @click="addCredits">
                                        Top Up
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Available: {{ creditBalance }} credits</p>
                            </div>

                            <!-- Timestamps -->
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Created</span>
                                    <span class="text-gray-700">{{ formatDate(detailsInstance.created_at) }}</span>
                                </div>
                                <div class="flex justify-between" v-if="detailsInstance.activated_at">
                                    <span class="text-gray-400">Activated</span>
                                    <span class="text-gray-700">{{ formatDate(detailsInstance.activated_at) }}</span>
                                </div>
                                <div class="flex justify-between" v-if="detailsInstance.expires_at">
                                    <span class="text-gray-400">Expires</span>
                                    <span class="text-gray-700">{{ formatDate(detailsInstance.expires_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import InstanceCard from '@/Components/Instance/InstanceCard.vue'
import CreateInstanceModal from '@/Components/Instance/CreateInstanceModal.vue'
import { PlusIcon, TrashIcon, XMarkIcon, DevicePhoneMobileIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    instances: { type: Object, default: () => ({ data: [] }) },
})

const page = usePage()
const showCreate = ref(false)
const detailsInstance = ref(null)
const addCreditsAmount = ref(null)

const creditBalance = computed(() => page.props.auth.user.credit_balance)

const onInstanceCreated = () => {
    showCreate.value = false
    router.reload({ only: ['instances'] })
}

const handleDetails = (inst) => {
    detailsInstance.value = inst
}

const handleConnect = (inst) => {
    // Phase 2: opens QR modal
    alert('QR login will be implemented in Phase 2.')
}

const handleDisconnect = (inst) => {
    // Phase 2
    alert('Disconnect will be implemented in Phase 2.')
}

const addCredits = async () => {
    if (!addCreditsAmount.value || !detailsInstance.value) return
    try {
        await axios.patch(`/api/instances/${detailsInstance.value.id}`, {
            add_credits: addCreditsAmount.value,
        })
        addCreditsAmount.value = null
        detailsInstance.value = null
        router.reload({ only: ['instances'] })
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to add credits.')
    }
}

const deleteInstance = async (inst) => {
    if (!confirm(`Delete "${inst.name}"? Unused credits will be returned to your wallet.`)) return
    try {
        await axios.delete(`/api/instances/${inst.id}`)
        router.reload({ only: ['instances'] })
    } catch (err) {
        alert(err.response?.data?.message ?? 'Could not delete instance.')
    }
}

const badgeClass = (s) => ({
    active: 'badge-active', pending: 'badge-pending',
    disconnected: 'badge-disconnected', suspended: 'badge-suspended', expired: 'badge-expired',
}[s] ?? 'badge')

const expiryColor = (d) => {
    if (d == null) return 'text-gray-400'
    if (d <= 3) return 'text-red-600'
    if (d <= 7) return 'text-orange-500'
    return 'text-gray-900'
}

const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
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