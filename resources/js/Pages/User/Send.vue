<template>
    <AppLayout title="Send Message">

        <div class="max-w-2xl">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-gray-900">Send Message</h1>
                <p class="text-sm text-gray-400 mt-0.5">Send a message from any of your connected instances</p>
            </div>

            <!-- No active instances warning -->
            <div v-if="!instances.length" class="card text-center py-12">
                <DevicePhoneMobileIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
                <h3 class="text-base font-semibold text-gray-700">No active instances</h3>
                <p class="text-gray-400 text-sm mt-1">Connect a WhatsApp number first.</p>
                <Link :href="route('user.instances')" class="btn-primary btn-sm mt-4 inline-flex">
                    Go to Instances
                </Link>
            </div>

            <div v-else class="space-y-5">

                <!-- Instance selector -->
                <div class="card">
                    <label class="form-label">From (WhatsApp Instance) <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 gap-2 mt-1">
                        <button v-for="inst in instances" :key="inst.id" @click="form.instance_id = inst.id" :class="[
                            'flex items-center gap-3 p-3 rounded-xl border-2 text-left transition-all',
                            form.instance_id == inst.id
                                ? 'border-blue-500 bg-blue-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]">
                            <div class="w-9 h-9 bg-green-500 rounded-xl flex items-center justify-center flex-shrink-0">
                                <DevicePhoneMobileIcon class="w-5 h-5 text-white" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 text-sm">{{ inst.name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ inst.phone_number }}</p>
                            </div>
                            <CheckCircleIcon v-if="form.instance_id == inst.id"
                                class="w-5 h-5 text-blue-600 ml-auto flex-shrink-0" />
                        </button>
                    </div>
                    <p v-if="errors.instance_id" class="form-error mt-1">{{ errors.instance_id[0] }}</p>
                </div>

                <!-- Recipient + message type -->
                <div class="card space-y-4">
                    <div>
                        <label class="form-label">To (Phone Number) <span class="text-red-500">*</span></label>
                        <input v-model="form.to" type="tel" class="form-input" :class="{ 'border-red-400': errors.to }"
                            placeholder="919876543210 (with country code, no +)" />
                        <p v-if="errors.to" class="form-error">{{ errors.to[0] }}</p>
                    </div>

                    <!-- Message type tabs -->
                    <div>
                        <label class="form-label">Message Type</label>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            <button v-for="t in messageTypes" :key="t.value" @click="form.type = t.value" :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                form.type == t.value ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            ]">
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Text message -->
                    <div v-if="form.type == 'text'">
                        <label class="form-label">Message <span class="text-red-500">*</span></label>
                        <textarea v-model="form.message" class="form-input resize-none"
                            :class="{ 'border-red-400': errors.message }" rows="4" maxlength="4096"
                            placeholder="Type your message…" />
                        <div class="flex justify-between mt-1">
                            <p v-if="errors.message" class="form-error">{{ errors.message[0] }}</p>
                            <p class="text-xs text-gray-400 ml-auto">{{ form.message.length }}/4096</p>
                        </div>
                    </div>

                    <!-- Media URL (image/video/audio/document) -->
                    <div v-if="['image', 'video', 'audio', 'document'].includes(form.type)">
                        <label class="form-label">Media URL <span class="text-red-500">*</span></label>
                        <input v-model="form.media_url" type="url" class="form-input"
                            :class="{ 'border-red-400': errors.media_url }" placeholder="https://..." />
                        <p v-if="errors.media_url" class="form-error">{{ errors.media_url[0] }}</p>
                    </div>

                    <!-- Caption (image/video) -->
                    <div v-if="['image', 'video'].includes(form.type)">
                        <label class="form-label">Caption <span
                                class="text-gray-400 font-normal">(optional)</span></label>
                        <input v-model="form.caption" type="text" class="form-input" maxlength="1024"
                            placeholder="Optional caption…" />
                    </div>

                    <!-- Document specific -->
                    <div v-if="form.type == 'document'" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Filename</label>
                            <input v-model="form.filename" type="text" class="form-input" placeholder="report.pdf" />
                        </div>
                        <div>
                            <label class="form-label">MIME type</label>
                            <input v-model="form.mimetype" type="text" class="form-input"
                                placeholder="application/pdf" />
                        </div>
                    </div>

                    <!-- Location -->
                    <div v-if="form.type == 'location'" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Latitude <span class="text-red-500">*</span></label>
                            <input v-model.number="form.latitude" type="number" step="any" class="form-input"
                                placeholder="28.6139" />
                        </div>
                        <div>
                            <label class="form-label">Longitude <span class="text-red-500">*</span></label>
                            <input v-model.number="form.longitude" type="number" step="any" class="form-input"
                                placeholder="77.2090" />
                        </div>
                    </div>

                    <!-- Poll -->
                    <div v-if="form.type == 'poll'" class="space-y-3">
                        <div>
                            <label class="form-label">Question <span class="text-red-500">*</span></label>
                            <input v-model="form.question" type="text" class="form-input"
                                placeholder="What do you prefer?" maxlength="255" />
                        </div>
                        <div>
                            <label class="form-label">Options (min 2, max 12)</label>
                            <div class="space-y-2">
                                <div v-for="(opt, idx) in form.options" :key="idx" class="flex gap-2">
                                    <input v-model="form.options[idx]" type="text" class="form-input"
                                        :placeholder="`Option ${idx + 1}`" />
                                    <button v-if="form.options.length > 2" @click="removeOption(idx)"
                                        class="text-red-400 hover:text-red-600 px-2">
                                        <XMarkIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <button v-if="form.options.length < 12" @click="form.options.push('')"
                                class="text-blue-600 text-sm mt-2 hover:underline">
                                + Add option
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Server error -->
                <div v-if="serverError" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                    {{ serverError }}
                </div>

                <!-- Success message -->
                <Transition name="fade">
                    <div v-if="lastSent"
                        class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-700 flex items-center gap-3">
                        <CheckCircleIcon class="w-5 h-5 flex-shrink-0" />
                        <span>Message queued! ID: <code class="font-mono">{{ lastSent }}</code></span>
                    </div>
                </Transition>

                <!-- Submit -->
                <button class="btn-primary w-full justify-center py-3" @click="send"
                    :disabled="sending || !form.instance_id || !form.to">
                    <span v-if="sending" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Sending…
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <PaperAirplaneIcon class="w-4 h-4" />
                        Send Message
                    </span>
                </button>

            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { DevicePhoneMobileIcon, CheckCircleIcon, PaperAirplaneIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { messageApi } from '@/composables/useApi'

const props = defineProps({
    instances: { type: Array, default: () => [] },
})

const sending = ref(false)
const serverError = ref(null)
const lastSent = ref(null)
const errors = reactive({})

const form = reactive({
    instance_id: props.instances[0]?.id ?? null,
    to: '',
    type: 'text',
    message: '',
    media_url: '',
    caption: '',
    filename: '',
    mimetype: '',
    latitude: null,
    longitude: null,
    question: '',
    options: ['', ''],
})

const messageTypes = [
    { value: 'text', label: 'Text' },
    { value: 'image', label: 'Image' },
    { value: 'video', label: 'Video' },
    { value: 'audio', label: 'Audio' },
    { value: 'document', label: 'Document' },
    { value: 'location', label: 'Location' },
    { value: 'poll', label: 'Poll' },
]

const removeOption = (idx) => form.options.splice(idx, 1)

const send = async () => {
    sending.value = true
    serverError.value = null
    lastSent.value = null
    Object.keys(errors).forEach(k => delete errors[k])

    try {
        const payload = { ...form }
        // Strip empty optional fields
        if (form.type !== 'poll') { delete payload.question; delete payload.options }
        if (!['image', 'video'].includes(form.type)) delete payload.caption
        if (form.type !== 'document') { delete payload.filename; delete payload.mimetype }
        if (form.type !== 'location') { delete payload.latitude; delete payload.longitude }
        if (form.type !== 'text') delete payload.message
        if (!['image', 'video', 'audio', 'document'].includes(form.type)) delete payload.media_url
        
        const { data } = await messageApi.send(payload)
        lastSent.value = data.message_id
        form.to = ''
        form.message = ''
        form.media_url = ''
        setTimeout(() => { lastSent.value = null }, 5000)
    } catch (err) {
        if (err.response?.status == 422) Object.assign(errors, err.response.data.errors ?? {})
        else serverError.value = err.response?.data?.message ?? 'Failed to send.'
    } finally {
        sending.value = false
    }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>