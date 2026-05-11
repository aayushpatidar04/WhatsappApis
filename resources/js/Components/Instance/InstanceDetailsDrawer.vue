<template>
    <Teleport to="body">
        <Transition name="slide">
            <div v-if="instance" class="fixed inset-0 z-50 flex justify-end">
                <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
                <div class="relative bg-white w-full max-w-md shadow-2xl overflow-y-auto">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-5 border-b sticky top-0 bg-white z-10">
                        <div>
                            <h2 class="font-bold text-gray-900">{{ instance.name }}</h2>
                            <p class="text-xs text-gray-400 mt-0.5">Instance details</p>
                        </div>
                        <button @click="$emit('close')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-6 space-y-6">

                        <!-- Status -->
                        <div class="flex items-center gap-4 p-4 rounded-xl" :class="statusBg">
                            <div
                                :class="['w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0', statusIcon]">
                                <DevicePhoneMobileIcon class="w-6 h-6 text-white" />
                            </div>
                            <div class="min-w-0">
                                <span :class="badgeClass">{{ statusLabel }}</span>
                                <p class="text-sm font-medium text-gray-900 mt-1 truncate">
                                    {{ instance.phone_number ?? 'Not connected yet' }}
                                </p>
                            </div>
                        </div>

                        <!-- Instance token -->
                        <div>
                            <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-2">Instance Token
                            </p>
                            <div class="bg-gray-900 rounded-xl p-4">
                                <code
                                    class="text-xs font-mono text-green-400 break-all select-all">{{ instance.instance_token }}</code>
                            </div>
                            <button @click="copyToken"
                                class="flex items-center gap-1.5 text-xs text-blue-600 hover:underline mt-2">
                                <ClipboardDocumentCheckIcon v-if="tokenCopied" class="w-3.5 h-3.5 text-green-500" />
                                <ClipboardDocumentIcon v-else class="w-3.5 h-3.5" />
                                {{ tokenCopied ? 'Copied!' : 'Copy token' }}
                            </button>
                            <p class="text-xs text-gray-400 mt-1">
                                Use as <code class="bg-gray-100 px-1 rounded">X-Instance-Token</code>
                                header in external API calls to <code
                                    class="bg-gray-100 px-1 rounded">/api/gateway/send/*</code>
                            </p>
                        </div>

                        <!-- Credits -->
                        <div>
                            <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-3">Credits</p>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="text-center bg-gray-50 rounded-xl p-3">
                                    <p class="text-xl font-bold text-gray-900">{{ instance.credits_assigned }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Assigned</p>
                                </div>
                                <div class="text-center bg-gray-50 rounded-xl p-3">
                                    <p class="text-xl font-bold text-orange-500">{{ consumed }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Consumed</p>
                                </div>
                                <div class="text-center bg-gray-50 rounded-xl p-3">
                                    <p class="text-xl font-bold"
                                        :class="instance.credits_remaining > 0 ? 'text-green-600' : 'text-red-500'">
                                        {{ instance.credits_remaining }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">Remaining</p>
                                </div>
                            </div>
                            <div v-if="instance.credits_assigned > 0" class="mt-3">
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Usage</span><span>{{ usagePct }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div :class="['h-2 rounded-full', usagePct > 80 ? 'bg-red-500' : usagePct > 60 ? 'bg-orange-400' : 'bg-green-500']"
                                        :style="{ width: `${usagePct}%` }" />
                                </div>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div>
                            <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-3">Timeline</p>
                            <div class="space-y-2 text-sm">
                                <div v-if="instance.activated_at" class="flex justify-between">
                                    <span class="text-gray-500">Activated</span>
                                    <span class="text-gray-800 font-medium">{{ fmt(instance.activated_at) }}</span>
                                </div>
                                <div v-if="instance.expires_at" class="flex justify-between">
                                    <span class="text-gray-500">Expires</span>
                                    <span class="font-medium"
                                        :class="instance.days_until_expiry <= 7 ? 'text-red-600' : 'text-gray-800'">
                                        {{ fmt(instance.expires_at) }}
                                        <span v-if="instance.days_until_expiry != null" class="text-xs opacity-60">({{
                                            instance.days_until_expiry }}d)</span>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Created</span>
                                    <span class="text-gray-800 font-medium">{{ fmt(instance.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Top Up -->
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <p class="text-sm font-semibold text-blue-900 mb-1">Top Up Credits</p>
                            <p class="text-xs text-blue-600 mb-3">Wallet: <strong>{{ creditBalance }}</strong> credits ·
                                1 credit = 1 month active</p>
                            <div class="flex gap-2">
                                <input v-model.number="addAmount" type="number" class="form-input text-sm"
                                    :max="creditBalance" min="1" placeholder="e.g. 2" />
                                <button class="btn-primary btn-sm whitespace-nowrap" @click="topUp"
                                    :disabled="!addAmount || addAmount < 1 || addAmount > creditBalance || topping">
                                    {{ topping ? '…' : 'Top Up' }}
                                </button>
                            </div>
                            <p v-if="topUpError" class="text-xs text-red-600 mt-2">{{ topUpError }}</p>
                            <p v-if="topUpSuccess" class="text-xs text-green-600 mt-2 font-medium">✓ Credits added!</p>
                        </div>

                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'
import { XMarkIcon, DevicePhoneMobileIcon, ClipboardDocumentIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'
import { instanceApi } from '@/composables/useApi'  // ← PATCH /dashboard/instances/{id}

const props = defineProps({
    instance: { type: Object, default: null },
    creditBalance: { type: Number, default: 0 },
})
const emit = defineEmits(['close', 'credits-added'])

const tokenCopied = ref(false)
const addAmount = ref(null)
const topping = ref(false)
const topUpError = ref(null)
const topUpSuccess = ref(false)

const consumed = computed(() => parseFloat(props.instance?.credits_consumed ?? 0).toFixed(2))
const usagePct = computed(() => {
    const assigned = props.instance?.credits_assigned ?? 0
    const consumed = props.instance?.credits_consumed ?? 0

    if (assigned <= 0) return 0
    return Math.min(100, Math.round((consumed / assigned) * 100))
})


const statusMap = {
    active: { label: 'Active', badge: 'badge-active', bg: 'bg-green-50', icon: 'bg-green-500' },
    pending: { label: 'Pending', badge: 'badge-pending', bg: 'bg-yellow-50', icon: 'bg-yellow-500' },
    disconnected: { label: 'Disconnected', badge: 'badge-disconnected', bg: 'bg-gray-50', icon: 'bg-gray-400' },
    suspended: { label: 'Suspended', badge: 'badge-suspended', bg: 'bg-orange-50', icon: 'bg-orange-500' },
    expired: { label: 'Expired', badge: 'badge-expired', bg: 'bg-red-50', icon: 'bg-red-500' },
}
const info = computed(() => statusMap[props.instance?.status] ?? statusMap.pending)
const badgeClass = computed(() => info.value.badge)
const statusLabel = computed(() => info.value.label)
const statusBg = computed(() => info.value.bg)
const statusIcon = computed(() => info.value.icon)

const copyToken = async () => {
    await navigator.clipboard.writeText(props.instance.instance_token)
    tokenCopied.value = true
    setTimeout(() => { tokenCopied.value = false }, 2000)
}

const topUp = async () => {
    topUpError.value = null; topUpSuccess.value = false; topping.value = true
    try {
        // PATCH /dashboard/instances/{id} with { add_credits: N } — session auth
        await instanceApi.topUp(props.instance.id, addAmount.value)
        topUpSuccess.value = true
        addAmount.value = null
        setTimeout(() => { topUpSuccess.value = false; emit('credits-added') }, 1200)
    } catch (err) {
        topUpError.value = err.response?.data?.message ?? 'Failed to add credits.'
    } finally {
        topping.value = false
    }
}

const fmt = (iso) => iso ? new Date(iso).toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'
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