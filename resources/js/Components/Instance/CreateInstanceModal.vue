<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">

                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Create Instance</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Add a new WhatsApp connection</p>
                        </div>
                        <button @click="$emit('close')" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="px-6 py-5 space-y-4">

                        <div>
                            <label class="form-label">Instance Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="form-input"
                                :class="{ 'border-red-400': errors.name }" placeholder="e.g. Sales WA, Support Line"
                                maxlength="100" required />
                            <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                        </div>

                        <div>
                            <label class="form-label">Credits to Assign</label>
                            <div class="flex items-center gap-3">
                                <input v-model.number="form.credits" type="number" class="form-input" min="0"
                                    :max="availableCredits" placeholder="0" />
                                <span class="text-sm text-gray-400 whitespace-nowrap">of {{ availableCredits }}
                                    available</span>
                            </div>
                            <p v-if="errors.credits" class="form-error">{{ errors.credits[0] }}</p>
                        </div>

                        <div v-if="form.credits > 0" class="bg-blue-50 rounded-lg px-4 py-3 text-sm text-blue-700">
                            <strong>{{ form.credits }}</strong> credit{{ form.credits !== 1 ? 's' : '' }} =
                            active for <strong>{{ form.credits }} month{{ form.credits !== 1 ? 's' : '' }}</strong>
                            after first QR scan.
                        </div>

                        <div>
                            <label class="form-label">Webhook URL <span
                                    class="text-gray-400 font-normal">(optional)</span></label>
                            <input v-model="form.webhook_url" type="url" class="form-input"
                                placeholder="https://yourapp.com/webhook" />
                            <p v-if="errors.webhook_url" class="form-error">{{ errors.webhook_url[0] }}</p>
                        </div>

                        <div v-if="serverError"
                            class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                            {{ serverError }}
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" class="btn-secondary flex-1" @click="$emit('close')">Cancel</button>
                            <button type="submit" class="btn-primary flex-1" :disabled="loading">
                                <span v-if="loading" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    Creating…
                                </span>
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
import { ref, reactive, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { instanceApi } from '@/composables/useApi'

const props = defineProps({
    show: { type: Boolean, required: true },
    availableCredits: { type: Number, default: 0 },
    // client admin passes 'client' so the right prefix is used
    ownerContext: { type: String, default: 'user' }, // 'user' | 'client'
})

const emit = defineEmits(['close', 'created'])

const loading = ref(false)
const serverError = ref(null)
const errors = reactive({})
const form = reactive({ name: '', credits: 0, webhook_url: '' })

watch(() => props.show, (val) => {
    if (val) {
        form.name = ''; form.credits = 0; form.webhook_url = ''
        serverError.value = null
        Object.keys(errors).forEach(k => delete errors[k])
    }
})

const submit = async () => {
    loading.value = true; serverError.value = null
    Object.keys(errors).forEach(k => delete errors[k])

    try {
        // instanceApi.create posts to /dashboard/instances (session auth)
        const { data } = await instanceApi.create({
            name: form.name,
            credits: form.credits || undefined,
            webhook_url: form.webhook_url || undefined,
        })
        emit('created', data.data)
    } catch (err) {
        if (err.response?.status === 422) {
            Object.assign(errors, err.response.data.errors ?? {})
        } else {
            serverError.value = err.response?.data?.message ?? 'Something went wrong.'
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