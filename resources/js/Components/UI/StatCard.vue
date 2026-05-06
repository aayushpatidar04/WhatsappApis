<template>
    <div class="stat-card">
        <div :class="['stat-icon', colorClass]">
            <component :is="icon" class="w-5 h-5" />
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-500 font-medium truncate">{{ label }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ value }}</p>
            <p v-if="sub" class="text-xs text-gray-400 mt-0.5">{{ sub }}</p>
        </div>
    </div>
</template>

<script setup>

import { computed } from 'vue'
const props = defineProps({
    icon: { type: Object, required: true },
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    sub: { type: String, default: null },
    color: { type: String, default: 'blue' },
})

const colorClass = computed(() => ({
    blue: 'bg-blue-600',
    green: 'bg-green-600',
    orange: 'bg-orange-500',
    purple: 'bg-purple-600',
    red: 'bg-red-600',
    teal: 'bg-teal-600',
}[props.color] ?? 'bg-blue-600'))
</script>