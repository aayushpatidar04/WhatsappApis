<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="visible" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="close(false)" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                    <div class="flex items-center justify-between px-6 py-4 border-b">
                        <h2 class="font-bold text-gray-900">{{ title }}</h2>
                        <button @click="close(false)" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                            ✕
                        </button>
                    </div>
                    <div class="px-6 py-5 text-sm text-gray-700">
                        {{ message }}
                    </div>
                    <div class="flex justify-end gap-2 px-6 py-4 border-t">
                        <button @click="close(false)" class="btn-secondary btn-sm">Cancel</button>
                        <button @click="close(true)" class="btn-danger btn-sm">Confirm</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    title: { type: String, default: 'Confirm Action' },
    message: { type: String, default: 'Are you sure?' }
})

const emit = defineEmits(['close'])

const visible = ref(true)

function close(result) {
    visible.value = false
    emit('close', result)
}
</script>
