<template>
    <button @click="$emit('update:modelValue', !modelValue)" :class="[
        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none',
        modelValue ? 'bg-blue-600' : 'bg-gray-300'
    ]">
        <span :class="[
            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
            modelValue ? 'translate-x-6' : 'translate-x-1'
        ]" />
    </button>
</template>

<script setup>
defineProps({
    modelValue: Boolean,
})
defineEmits(['update:modelValue'])
</script>
