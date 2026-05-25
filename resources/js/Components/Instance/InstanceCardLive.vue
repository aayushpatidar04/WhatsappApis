<template>
    <div class="card hover:shadow-md transition-shadow duration-200">

        <!-- Header -->
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 min-w-0">
                <div
                    :class="['w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors duration-300', iconBg]">
                    <DevicePhoneMobileIcon class="w-5 h-5 text-white" />
                </div>
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate">{{ instance.name }}</h3>
                    <p class="text-xs text-gray-400 font-mono mt-0.5 truncate">
                        {{ instance.phone_number ?? 'Not connected' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                <!-- Live status badge -->
                <span :class="['badge flex items-center gap-1.5 transition-all duration-300', badgeClass]">
                    <span
                        :class="['w-1.5 h-1.5 rounded-full', instance.status === 'active' ? 'bg-green-500 animate-pulse' : 'bg-current opacity-60']" />
                    {{ statusLabel }}
                </span>
                <!-- Client-owned badge -->
                <span v-if="instance.is_own && isClientAdmin"
                    class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-medium">
                    ★ Yours
                </span>
            </div>
        </div>

        <!-- Instance token -->
        <div class="bg-gray-50 rounded-lg px-3 py-2 mb-4">
            <div class="flex items-center justify-between mb-0.5">
                <p class="text-xs text-gray-400 font-medium">Instance Token</p>
                <div class="flex gap-1.5">
                    <button @click="showToken = !showToken" class="text-gray-300 hover:text-gray-500">
                        <EyeIcon v-if="!showToken" class="w-3.5 h-3.5" />
                        <EyeSlashIcon v-else class="w-3.5 h-3.5" />
                    </button>
                    <button @click="copyToken" class="text-gray-300 hover:text-blue-500">
                        <ClipboardDocumentCheckIcon v-if="copied" class="w-3.5 h-3.5 text-green-500" />
                        <ClipboardDocumentIcon v-else class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
            <code class="text-xs font-mono text-gray-600 truncate block">
        {{ showToken ? instance.instance_token : maskedToken }}
      </code>
        </div>

        <!-- Credit stats -->
        <div class="grid grid-cols-3 gap-2 mb-4 text-center">
            <div class="bg-gray-50 rounded-lg py-2">
                <p class="text-base text-sm font-bold text-gray-900">
                    {{ instance.activated_at ? dayjs(instance.activated_at).format('DD MMM YYYY') : '—' }}
                </p>
                <p class="text-xs text-gray-400">Activated On</p>
            </div>
            <div class="bg-gray-50 rounded-lg py-2">
                <p class="text-base text-sm font-bold text-gray-900">
                    {{ instance.expires_at ? dayjs(instance.expires_at).format('DD MMM YYYY') : '—' }}
                </p>
                <p class="text-xs text-gray-400">Expires On</p>
            </div>
            <div class="bg-gray-50 rounded-lg py-2">
                <p class="text-base text-sm font-bold" :class="expiryClass">
                    {{ instance.days_until_expiry ?? '—' }}
                </p>
                <p class="text-xs text-gray-400">Days Left</p>
            </div>
        </div>


        <!-- Actions -->
        <div class="flex gap-2 pt-3 border-t border-gray-100">
            <!-- Connect -->
            <button v-if="canConnect" class="btn-primary btn-sm flex-1" @click="$emit('connect', instance)" :disabled="instance.status === 'suspended'
                || instance.status === 'expired'
                || instance.credits_assigned === 0">
                <QrCodeIcon class="w-3.5 h-3.5" />
                {{ (instance.status === 'suspended' || instance.credits_assigned === 0)
                    ? 'Top Up First'
                    : 'Connect' }}
            </button>


            <!-- Disconnect -->
            <button v-if="canDisconnect"
                class="btn-sm flex-1 bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 rounded-lg text-sm font-medium flex items-center justify-center gap-1"
                @click="$emit('disconnect', instance)">
                <ArrowRightOnRectangleIcon class="w-3.5 h-3.5" />
                Disconnect
            </button>

            <!-- Details -->
            <button class="btn-secondary btn-sm" @click="$emit('details', instance)" title="View details">
                <InformationCircleIcon class="w-4 h-4" />
            </button>

            <!-- Delete -->
            <button class="btn-sm p-2 text-red-400 hover:bg-red-50 hover:text-red-600 rounded-lg border border-red-100"
                @click="$emit('delete', instance)" title="Delete">
                <TrashIcon class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
    DevicePhoneMobileIcon, EyeIcon, EyeSlashIcon,
    ClipboardDocumentIcon, ClipboardDocumentCheckIcon,
    QrCodeIcon, ArrowRightOnRectangleIcon, TrashIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'
import dayjs from 'dayjs'

/**
 * This component is STATELESS regarding live updates.
 * The parent page uses useInstances() which handles all Pusher subscriptions.
 * When Pusher fires an event, useInstances mutates the instance object in-place.
 * Because instance is a reactive ref item, Vue re-renders this card automatically.
 * No duplicated Pusher subscriptions. No stale state.
 */
const props = defineProps({
    instance: { type: Object, required: true },
})

defineEmits(['connect', 'disconnect', 'delete', 'details'])

const page = usePage()
const isClientAdmin = computed(() => page.props.auth.user.role === 'client_admin')

const showToken = ref(false)
const copied = ref(false)

// Status display
const statusMap = {
    pending: { label: 'Pending', badge: 'badge-pending', bg: 'bg-yellow-500' },
    qr_pending: { label: 'Scan QR', badge: 'badge-pending', bg: 'bg-blue-500' },
    active: { label: 'Active', badge: 'badge-active', bg: 'bg-green-500' },
    disconnected: { label: 'Disconnected', badge: 'badge-disconnected', bg: 'bg-gray-400' },
    suspended: { label: 'Suspended', badge: 'badge-suspended', bg: 'bg-orange-500' },
    expired: { label: 'Expired', badge: 'badge-expired', bg: 'bg-red-500' },
}

const info = computed(() => statusMap[props.instance.status] ?? statusMap.pending)
const iconBg = computed(() => info.value.bg)
const badgeClass = computed(() => info.value.badge)
const statusLabel = computed(() => info.value.label)

const canConnect = computed(() => ['pending', 'disconnected', 'suspended'].includes(props.instance.status))
const canDisconnect = computed(() => ['active', 'qr_pending'].includes(props.instance.status))

const maskedToken = computed(() => {
    const t = props.instance.instance_token
    return t ? `${t.slice(0, 8)}••••••••${t.slice(-4)}` : ''
})

const expiryClass = computed(() => {
    const d = props.instance.days_until_expiry
    if (d == null) return 'text-gray-400'
    if (d <= 3) return 'text-red-600 font-bold'
    if (d <= 7) return 'text-orange-500'
    return 'text-gray-900'
})

const copyToken = async () => {
    await navigator.clipboard.writeText(props.instance.instance_token)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}
</script>