<template>
    <!-- Backdrop -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />

                <!-- Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Create Instance</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Add a new WhatsApp connection</p>
                        </div>
                        <button @click="$emit('close')" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
                        <!-- Instance name -->
                        <div>
                            <label class="form-label">Instance Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="form-input"
                                placeholder="e.g. Sales WA, Support Line" maxlength="100" required />
                            <p v-if="errors.name" class="form-error">{{ errors.name }}</p>
                        </div>

                        <!-- Credits to assign -->
                        <div>
                            <label class="form-label">Credits to Assign</label>
                            <div class="flex items-center gap-3">
                                <input v-model.number="form.credits" type="number" class="form-input" min="0"
                                    :max="availableCredits" placeholder="0" />
                                <span class="text-sm text-gray-400 whitespace-nowrap flex-shrink-0">
                                    of {{ availableCredits }} available
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                1 credit = 1 instance active for 1 month. You can add more credits later.
                            </p>
                            <p v-if="errors.credits" class="form-error">{{ errors.credits }}</p>
                        </div>

                        <!-- Validity preview -->
                        <div v-if="form.credits > 0" class="bg-blue-50 rounded-lg px-4 py-3 text-sm text-blue-700">
                            <strong>{{ form.credits }}</strong> credit{{ form.credits > 1 ? 's' : '' }} =
                            this instance will be active for
                            <strong>{{ form.credits }} month{{ form.credits > 1 ? 's' : '' }}</strong>
                            after first connection.
                        </div>

                        <!-- Webhook URL (optional) -->
                        <div>
                            <label class="form-label">Webhook URL <span
                                    class="text-gray-400 font-normal">(optional)</span></label>
                            <input v-model="form.webhook_url" type="url" class="form-input"
                                placeholder="https://yourapp.com/webhook" />
                            <p class="text-xs text-gray-400 mt-1">Inbound messages will be POSTed to this URL.</p>
                            <p v-if="errors.webhook_url" class="form-error">{{ errors.webhook_url }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button type="button" class="btn-secondary flex-1" @click="$emit('close')">
                                Cancel
                            </button>
                            <button type="submit" class="btn-primary flex-1" :disabled="loading">
                                <span v-if="loading">Creating…</span>
                                <span v-else>Create Instance</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    show: { type: Boolean, required: true },
    availableCredits: { type: Number, default: 0 },
})

const emit = defineEmits(['close', 'created'])

const loading = ref(false)
const errors = ref({})
const form = reactive({ name: '', credits: 0, webhook_url: '' })

const submit = async () => {
    loading.value = true
    errors.value = {}

    try {
        const { data } = await axios.post('/api/instances', {
            name: form.name,
            credits: form.credits,
            webhook_url: form.webhook_url || undefined,
        })

        emit('created', data.data)
        form.name = ''
        form.credits = 0
        form.webhook_url = ''
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors ?? {}
        }
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active>div:last-child {
    transition: transform 0.2s ease;
}

.modal-enter-from>div:last-child {
    transform: scale(0.95);
}
</style>