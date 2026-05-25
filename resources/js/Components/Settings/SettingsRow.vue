<template>
    <div class="flex items-start justify-between gap-6">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-800">{{ label }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ description }}</p>
        </div>
        <div class="flex-shrink-0">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: String,
    description: String,
})
</script>
