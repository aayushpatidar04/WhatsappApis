<template>
    <AppLayout title="Rate Limits">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Rate Limit Settings</h1>
                <p class="text-sm text-gray-400 mt-0.5">
                    Control how many messages per minute each instance or user can send.
                    Changes take effect immediately — no restart needed.
                </p>
            </div>
        </div>

        <!-- Info banner -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800">
            <strong>Anti-ban protection:</strong> WhatsApp flags accounts that send too many messages too fast.
            Keep rates conservative for new numbers. Limits cascade:
            <span class="font-mono bg-amber-100 px-1 rounded text-xs">Instance override</span> →
            <span class="font-mono bg-amber-100 px-1 rounded text-xs">User override</span> →
            <span class="font-mono bg-amber-100 px-1 rounded text-xs">Client default</span>.
        </div>

        <!-- Client default rate -->
        <div class="card mb-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="card-title">Client Default Rate</h2>
                    <p class="text-xs text-gray-400 mt-1">
                        Applies to all users and instances without an override.
                        Maximum allowed by your plan: 20/min.
                    </p>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <input v-model.number="clientRate" type="number" class="form-input w-24 text-center"
                            :min="minRate" :max="20" @keyup.enter="saveClientRate" />
                        <span class="text-sm text-gray-400">msg/min</span>
                    </div>
                    <button class="btn-primary btn-sm" @click="saveClientRate" :disabled="savingClient">
                        {{ savingClient ? '…' : 'Save' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Instances -->
        <div class="card mb-6">
            <h2 class="card-title mb-4">Instance Rate Limits</h2>
            <p class="text-xs text-gray-400 mb-4">
                Override the default for specific WhatsApp numbers.
                Useful when some instances handle high-volume sending.
            </p>

            <div v-if="!instances.length" class="text-center py-8 text-gray-400 text-sm">
                No instances in this account.
            </div>

            <div v-else class="divide-y divide-gray-50">
                <div v-for="inst in instances" :key="inst.id"
                    class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">

                    <!-- Instance info -->
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <DevicePhoneMobileIcon class="w-5 h-5 text-green-600" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm truncate">{{ inst.name }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ inst.phone ?? 'Not connected' }}</p>
                        </div>
                    </div>

                    <!-- Rate control -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="relative">
                            <input v-model.number="instRates[inst.id]" type="number"
                                class="form-input w-20 text-center text-sm pr-2" :min="minRate" :max="maxRate"
                                :placeholder="clientRate" @keyup.enter="saveInstanceRate(inst.id)" />
                        </div>
                        <span class="text-xs text-gray-400 whitespace-nowrap">/min</span>
                        <button class="btn-primary btn-sm px-3" @click="saveInstanceRate(inst.id)"
                            :disabled="savingInst == inst.id">
                            {{ savingInst == inst.id ? '…' : 'Set' }}
                        </button>
                        <button v-if="instRates[inst.id] && instRates[inst.id] !== clientRate"
                            class="text-xs text-red-400 hover:text-red-600" @click="resetInstanceRate(inst.id)"
                            title="Reset to default">
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users -->
        <div class="card">
            <h2 class="card-title mb-4">User Rate Limits</h2>
            <p class="text-xs text-gray-400 mb-4">
                Override the default for specific users (affects all their instances without an instance override).
            </p>

            <div v-if="!users.length" class="text-center py-8 text-gray-400 text-sm">
                No users in this account.
            </div>

            <div v-else class="divide-y divide-gray-50">
                <div v-for="user in users" :key="user.id"
                    class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">

                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-blue-700 text-xs font-bold">{{ user.name.charAt(0) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm truncate">{{ user.name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ user.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <input v-model.number="userRates[user.id]" type="number"
                            class="form-input w-20 text-center text-sm" :min="minRate" :max="maxRate"
                            :placeholder="clientRate" @keyup.enter="saveUserRate(user.id)" />
                        <span class="text-xs text-gray-400 whitespace-nowrap">/min</span>
                        <button class="btn-primary btn-sm px-3" @click="saveUserRate(user.id)"
                            :disabled="savingUser == user.id">
                            {{ savingUser == user.id ? '…' : 'Set' }}
                        </button>
                        <button v-if="userRates[user.id] && userRates[user.id] !== clientRate"
                            class="text-xs text-red-400 hover:text-red-600" @click="resetUserRate(user.id)"
                            title="Reset to default">
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3">
                <CheckCircleIcon class="w-5 h-5 text-green-400 flex-shrink-0" />
                {{ toast }}
            </div>
        </Transition>

    </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { DevicePhoneMobileIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const props = defineProps({
    client_limit: { type: Number, default: 20 },
    users: { type: Array, default: () => [] },
    instances: { type: Array, default: () => [] },
    min_rate: { type: Number, default: 5 },
    max_rate: { type: Number, default: 20 },
})

const clientRate = ref(props.client_limit)
const minRate = ref(props.min_rate)
const maxRate = ref(props.max_rate)
const savingClient = ref(false)
const savingInst = ref(null)
const savingUser = ref(null)
const toast = ref(null)

// Pre-populate current rates from props
const instRates = reactive(Object.fromEntries(props.instances.map(i => [i.id, i.limit])))
const userRates = reactive(Object.fromEntries(props.users.map(u => [u.id, u.limit])))

const showToast = (msg) => {
    toast.value = msg
    setTimeout(() => { toast.value = null }, 3000)
}

const saveClientRate = async () => {
    savingClient.value = true
    try {
        await webHttp.put('/client/rate-limits/client', { max_per_minute: clientRate.value })
        maxRate.value = clientRate.value
        showToast(`Client default set to ${clientRate.value}/min`)
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to save.')
    } finally {
        savingClient.value = false
    }
}

const saveInstanceRate = async (id) => {
    savingInst.value = id
    try {
        await webHttp.put(`/client/rate-limits/instance/${id}`, { max_per_minute: instRates[id] })
        showToast(`Instance rate updated to ${instRates[id]}/min`)
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed.')
    } finally {
        savingInst.value = null
    }
}

const resetInstanceRate = async (id) => {
    try {
        await webHttp.delete(`/client/rate-limits/instance/${id}`)
        instRates[id] = clientRate.value
        showToast('Instance rate reset to client default.')
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed.')
    }
}

const saveUserRate = async (id) => {
    savingUser.value = id
    try {
        await webHttp.put(`/client/rate-limits/user/${id}`, { max_per_minute: userRates[id] })
        showToast(`User rate updated to ${userRates[id]}/min`)
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed.')
    } finally {
        savingUser.value = null
    }
}

const resetUserRate = async (id) => {
    try {
        await webHttp.delete(`/client/rate-limits/user/${id}`)
        userRates[id] = clientRate.value
        showToast('User rate reset to client default.')
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed.')
    }
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>