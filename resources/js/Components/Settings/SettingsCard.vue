<template>
    <div class="card mb-5">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
            <p class="text-xs text-gray-400 mt-0.5">{{ description }}</p>
        </div>
        <div class="space-y-5">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: String,
    description: String,
})
</script>
