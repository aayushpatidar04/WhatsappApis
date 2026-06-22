<template>
    <TransitionGroup name="flash" tag="div" class="space-y-2 mb-4">
        <div v-for="flash in flashes" :key="flash.id" :class="[
            'flex items-start gap-3 p-4 rounded-lg text-sm font-medium border',
            flash.type == 'success' && 'bg-green-50 text-green-800 border-green-200',
            flash.type == 'error' && 'bg-red-50 text-red-800 border-red-200',
            flash.type == 'warning' && 'bg-yellow-50 text-yellow-800 border-yellow-200',
            flash.type == 'info' && 'bg-blue-50 text-blue-800 border-blue-200',
        ]">
            <component :is="iconFor(flash.type)" class="w-4 h-4 flex-shrink-0 mt-0.5" />
            <span class="flex-1">{{ flash.message }}</span>
            <button @click="dismiss(flash.id)" class="opacity-60 hover:opacity-100">
                <XMarkIcon class="w-4 h-4" />
            </button>
        </div>
    </TransitionGroup>
</template>

<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
    CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon,
    InformationCircleIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const flashes = ref([])
let counter = 0

const iconFor = (type) => ({
    success: CheckCircleIcon,
    error: XCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
}[type] ?? InformationCircleIcon)

const dismiss = (id) => {
    flashes.value = flashes.value.filter(f => f.id !== id)
}

const addFlash = (type, message) => {
    const id = ++counter
    flashes.value.push({ id, type, message })
    // Auto-dismiss after 5 seconds
    setTimeout(() => dismiss(id), 5000)
}

// Watch Inertia flash messages
watch(() => page.props.flash, (flash) => {
    if (!flash) return
    if (flash.success) addFlash('success', flash.success)
    if (flash.error) addFlash('error', flash.error)
    if (flash.warning) addFlash('warning', flash.warning)
    if (flash.info) addFlash('info', flash.info)
}, { immediate: true, deep: true })
</script>

<style scoped>
.flash-enter-active,
.flash-leave-active {
    transition: all 0.3s ease;
}

.flash-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

.flash-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>