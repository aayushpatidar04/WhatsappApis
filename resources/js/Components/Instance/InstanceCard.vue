<template>
    <div class="card hover:shadow-md transition-shadow duration-200">
        <!-- Header row -->
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 min-w-0">
                <!-- Status dot -->
                <div :class="['w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0', statusBg]">
                    <DevicePhoneMobileIcon class="w-5 h-5 text-white" />
                </div>
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate">{{ instance.name }}</h3>
                    <p class="text-xs text-gray-400 mt-0.5 font-mono">
                        {{ instance.phone_number ?? 'Not connected' }}
                    </p>
                </div>
            </div>
            <!-- Status badge -->
            <span :class="statusBadge">{{ statusLabel }}</span>
        </div>

        <!-- Instance token (routing key) -->
        <div class="bg-gray-50 rounded-lg px-3 py-2 mb-4">
            <p class="text-xs text-gray-400 mb-1 font-medium">Instance Token</p>
            <div class="flex items-center gap-2">
                <code class="text-xs text-gray-700 font-mono truncate flex-1">
          {{ showToken ? instance.instance_token : masked }}
        </code>
                <button @click="showToken = !showToken" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                    <EyeIcon v-if="!showToken" class="w-4 h-4" />
                    <EyeSlashIcon v-else class="w-4 h-4" />
                </button>
                <button @click="copyToken" class="text-gray-400 hover:text-blue-600 flex-shrink-0">
                    <ClipboardDocumentIcon class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Credit & expiry info -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="text-center">
                <p class="text-lg font-bold text-gray-900">{{ instance.credits_assigned }}</p>
                <p class="text-xs text-gray-400">Assigned</p>
            </div>
            <div class="text-center border-x border-gray-100">
                <p class="text-lg font-bold"
                    :class="instance.credits_remaining > 0 ? 'text-green-600' : 'text-red-500'">
                    {{ instance.credits_remaining }}
                </p>
                <p class="text-xs text-gray-400">Remaining</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-bold" :class="expiryColor">
                    {{ instance.days_until_expiry ?? '—' }}
                </p>
                <p class="text-xs text-gray-400">Days left</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 pt-3 border-t border-gray-100">
            <slot name="actions">
                <!-- Default action slot — override per page -->
                <button class="btn-secondary btn-sm flex-1" @click="$emit('details', instance)">
                    Details
                </button>
                <button v-if="instance.status === 'pending' || instance.status === 'disconnected'"
                    class="btn-primary btn-sm flex-1" @click="$emit('connect', instance)">
                    Connect
                </button>
                <button v-if="instance.status === 'active'"
                    class="btn-sm flex-1 bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 rounded-lg text-sm font-medium"
                    @click="$emit('disconnect', instance)">
                    Disconnect
                </button>
            </slot>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import {
    DevicePhoneMobileIcon, EyeIcon, EyeSlashIcon, ClipboardDocumentIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    instance: { type: Object, required: true },
})

defineEmits(['connect', 'disconnect', 'details'])

const showToken = ref(false)

const masked = computed(() =>
    props.instance.instance_token
        ? props.instance.instance_token.slice(0, 8) + '••••••••••••••••••••' + props.instance.instance_token.slice(-4)
        : ''
)

const copyToken = async () => {
    await navigator.clipboard.writeText(props.instance.instance_token)
    // Could show a toast here
}

const statusMap = {
    pending: { label: 'Pending', badge: 'badge-pending', bg: 'bg-yellow-500' },
    active: { label: 'Active', badge: 'badge-active', bg: 'bg-green-500' },
    disconnected: { label: 'Disconnected', badge: 'badge-disconnected', bg: 'bg-gray-400' },
    suspended: { label: 'Suspended', badge: 'badge-suspended', bg: 'bg-orange-500' },
    expired: { label: 'Expired', badge: 'badge-expired', bg: 'bg-red-500' },
}

const statusInfo = computed(() => statusMap[props.instance.status] ?? statusMap.pending)
const statusBadge = computed(() => statusInfo.value.badge)
const statusLabel = computed(() => statusInfo.value.label)
const statusBg = computed(() => statusInfo.value.bg)

const expiryColor = computed(() => {
    const d = props.instance.days_until_expiry
    if (d == null) return 'text-gray-400'
    if (d <= 3) return 'text-red-600'
    if (d <= 7) return 'text-orange-500'
    return 'text-gray-900'
})
</script>