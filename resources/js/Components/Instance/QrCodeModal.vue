<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="handleClose" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
                        <div>
                            <h2 class="font-bold text-gray-900">Connect WhatsApp</h2>
                            <p class="text-xs text-gray-400 mt-0.5">{{ instance?.name }}</p>
                        </div>
                        <button @click="handleClose" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="px-6 py-6 text-center">

                        <!-- Connected -->
                        <Transition name="fade">
                            <div v-if="isConnected" class="py-6">
                                <div
                                    class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <CheckCircleIcon class="w-9 h-9 text-green-600" />
                                </div>
                                <h3 class="text-lg font-bold text-gray-900">Connected!</h3>
                                <p class="text-sm text-gray-500 mt-1">{{ phoneNumber ?? 'Session is active.' }}</p>
                                <button class="btn-primary w-full justify-center mt-6"
                                    @click="handleClose">Done</button>
                            </div>
                        </Transition>

                        <!-- Loading QR -->
                        <div v-if="!isConnected && !qrCode && !error" class="py-6">
                            <div
                                class="w-48 h-48 bg-gray-100 rounded-xl mx-auto flex flex-col items-center justify-center">
                                <div
                                    class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-3" />
                                <p class="text-xs text-gray-400">Starting session…</p>
                            </div>
                        </div>

                        <!-- QR code -->
                        <div v-if="!isConnected && qrCode && !error">
                            <div
                                class="relative inline-block p-3 bg-white border-2 border-gray-200 rounded-xl shadow-sm">
                                <img :src="qrCode" alt="WhatsApp QR Code" class="w-48 h-48 rounded-lg" />
                                <!-- Expiry overlay -->
                                <div v-if="qrSecondsLeft <= 30 && qrSecondsLeft > 0"
                                    class="absolute inset-0 bg-white/80 rounded-xl flex items-center justify-center">
                                    <div>
                                        <p class="text-3xl font-bold text-orange-500">{{ qrSecondsLeft }}</p>
                                        <p class="text-xs text-gray-500">seconds left</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2">
                                <p class="text-sm font-medium text-gray-700">Scan with WhatsApp</p>
                                <ol class="text-xs text-gray-400 space-y-1 text-left inline-block">
                                    <li>1. Open WhatsApp on your phone</li>
                                    <li>2. Tap <strong>Linked Devices</strong></li>
                                    <li>3. Tap <strong>Link a Device</strong></li>
                                    <li>4. Point your camera at this QR</li>
                                </ol>
                            </div>
                            <button v-if="qrSecondsLeft == 0" class="btn-secondary w-full justify-center mt-4"
                                @click="refreshQr" :disabled="refreshing">
                                <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
                                {{ refreshing ? 'Refreshing…' : 'New QR Code' }}
                            </button>
                        </div>

                        <!-- Error -->
                        <div v-if="error" class="py-4">
                            <div
                                class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <XCircleIcon class="w-8 h-8 text-red-500" />
                            </div>
                            <p class="text-sm font-medium text-red-700">{{ error }}</p>
                            <button class="btn-secondary w-full justify-center mt-4" @click="startSession">Try
                                Again</button>
                        </div>
                    </div>

                    <!-- Status footer -->
                    <div v-if="!isConnected" class="px-6 pb-4">
                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            <span :class="['w-2 h-2 rounded-full flex-shrink-0', statusDot]" />
                            <span>{{ statusText }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { XMarkIcon, CheckCircleIcon, XCircleIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
import { instanceApi } from '@/composables/useApi'

const props = defineProps({
    show: { type: Boolean, required: true },
    instance: { type: Object, default: null },
})

const emit = defineEmits(['close', 'connected'])

const qrCode = ref(null)
const isConnected = ref(false)
const phoneNumber = ref(null)
const error = ref(null)
const refreshing = ref(false)
const qrSecondsLeft = ref(180)
const sessionStatus = ref('initialising')

let pusherChannel = null
let qrCountdown = null
let qrPollInterval = null

// Extracted handler so we can stop listening to it specifically
const handlePusherEvent = ({ event, payload }) => {
    if (event == 'qr.updated') { qrCode.value = payload.qr; sessionStatus.value = 'qr_pending'; resetCountdown() }
    if (event == 'session.connected') { isConnected.value = true; phoneNumber.value = payload.phone_number; sessionStatus.value = 'connected'; stopPolling(); clearCountdown(); emit('connected', { phone_number: phoneNumber.value }) }
    if (event == 'session.disconnected') { sessionStatus.value = 'disconnected' }
    if (event == 'session.error') { error.value = payload.error ?? 'Session error.' }
}

const statusDot = computed(() => ({
    initialising: 'bg-yellow-400 animate-pulse',
    qr_pending: 'bg-blue-500 animate-pulse',
    connected: 'bg-green-500',
    disconnected: 'bg-red-500',
}[sessionStatus.value] ?? 'bg-gray-400'))

const statusText = computed(() => ({
    initialising: 'Starting session…',
    qr_pending: 'Waiting for scan…',
    connected: 'Connected',
    disconnected: 'Disconnected',
}[sessionStatus.value] ?? 'Unknown'))

watch(() => props.show, async (val) => {
    if (val && props.instance) await startSession()
    else cleanup()
})

async function startSession() {
    error.value = null
    qrCode.value = null
    isConnected.value = false
    sessionStatus.value = 'initialising'

    try {
        // Uses session auth — no Bearer token needed
        await instanceApi.connect(props.instance.id)
        subscribePusher()
        startPolling()
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to start session.'
    }
}

function subscribePusher() {
    if (!window.Echo || !props.instance) return
    pusherChannel = window.Echo.private(`instance.${props.instance.instance_token}`)
    // Attach listener using the referenced function
    pusherChannel.listen('InstanceEvent', handlePusherEvent)
}

function startPolling() {
    qrPollInterval = setInterval(async () => {
        if (isConnected.value) return stopPolling()
        try {
            const { data } = await instanceApi.qr(props.instance.id)
            if (data.qr && !qrCode.value) { qrCode.value = data.qr; sessionStatus.value = 'qr_pending'; resetCountdown() }
            if (data.status == 'connected') { isConnected.value = true; stopPolling(); clearCountdown() }
        } catch (_) { }
    }, 5000)
}

function stopPolling() { clearInterval(qrPollInterval) }

function resetCountdown() {
    clearCountdown()
    qrSecondsLeft.value = 180
    qrCountdown = setInterval(() => {
        if (qrSecondsLeft.value > 0) qrSecondsLeft.value--
        else { clearCountdown(); qrCode.value = null }
    }, 1000)
}

function clearCountdown() { clearInterval(qrCountdown) }

async function refreshQr() {
    refreshing.value = true
    qrCode.value = null
    qrSecondsLeft.value = 180
    try {
        await instanceApi.connect(props.instance.id)
        startPolling()
    } catch (_) {
        error.value = 'Could not refresh QR.'
    } finally {
        refreshing.value = false
    }
}

function cleanup() {
    stopPolling(); clearCountdown()
    // ✅ FIX: Only stop this specific listener, DO NOT leave the channel
    if (pusherChannel) {
        pusherChannel.stopListening('InstanceEvent', handlePusherEvent)
    }
    // if (window.Echo && props.instance) window.Echo.leave(`instance.${props.instance.instance_token}`)
    pusherChannel = null
}

function handleClose() {
    if (!isConnected.value && !confirm('Close? The session will continue in the background.')) return
    cleanup(); emit('close')
    window.location.reload();
}

onUnmounted(cleanup)
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.25s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active>div:last-child {
    transition: transform 0.25s ease, opacity 0.25s ease;
}

.modal-enter-from>div:last-child {
    transform: scale(0.95);
    opacity: 0;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>