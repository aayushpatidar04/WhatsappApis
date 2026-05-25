<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

                    <div class="flex items-center justify-between px-6 py-5 border-b sticky top-0 bg-white z-10">
                        <div>
                            <h2 class="font-bold text-gray-900">{{ isEditing ? 'Edit Campaign' : 'New Campaign' }}</h2>
                            <p class="text-xs text-gray-400 mt-0.5">Step {{ step }} of 3</p>
                        </div>
                        <button @click="$emit('close')" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="px-6 py-3 border-b bg-gray-50">
                        <div class="flex gap-2">
                            <div v-for="s in 3" :key="s"
                                :class="['flex-1 h-1.5 rounded-full transition-colors', s <= step ? 'bg-blue-600' : 'bg-gray-200']" />
                        </div>
                        <div class="flex justify-between mt-1.5 text-xs text-gray-400">
                            <span :class="step >= 1 ? 'text-blue-600 font-medium' : ''">Setup</span>
                            <span :class="step >= 2 ? 'text-blue-600 font-medium' : ''">Message</span>
                            <span :class="step >= 3 ? 'text-blue-600 font-medium' : ''">Recipients</span>
                        </div>
                    </div>

                    <div v-show="step === 1" class="px-6 py-5 space-y-4">
                        <div>
                            <label class="form-label">Campaign Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="form-input"
                                :class="{ 'border-red-400': errors.name }" placeholder="e.g. Diwali Offer 2024"
                                required />
                            <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                        </div>

                        <div>
                            <label class="form-label">WhatsApp Instance <span class="text-red-500">*</span></label>
                            <select v-model="form.instance_id" class="form-input"
                                :class="{ 'border-red-400': errors.instance_id }" :disabled="isEditing">
                                <option :value="null">Select instance…</option>
                                <option v-for="inst in instances" :key="inst.id" :value="inst.id">
                                    {{ inst.name }} — {{ inst.phone_number }}
                                </option>
                            </select>
                            <p v-if="errors.instance_id" class="form-error">{{ errors.instance_id[0] }}</p>
                            <p v-if="!instances.length && !isEditing" class="text-xs text-orange-500 mt-1">
                                No active instances. Connect a WhatsApp number first.
                            </p>
                            <p v-if="isEditing" class="text-xs text-gray-400 mt-1">Instance cannot be changed after
                                creation.</p>
                        </div>

                        <div>
                            <label class="form-label">Schedule <span class="text-gray-400 font-normal">(optional — blank
                                    = send immediately on launch)</span></label>
                            <input v-model="form.schedule_time" type="datetime-local" class="form-input"
                                :min="minSchedule" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Send Window Start <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <input v-model="form.send_window_start" type="time" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label">Send Window End</label>
                                <input v-model="form.send_window_end" type="time" class="form-input" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-400">Send window limits the hours during which messages are
                            dispatched.</p>
                    </div>

                    <div v-show="step === 2" class="px-6 py-5 space-y-4">
                        <div>
                            <label class="form-label">Message Type</label>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <button v-for="t in messageTypes" :key="t.value"
                                    @click="!isEditing && (form.message_type = t.value)" :disabled="isEditing" :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                        form.message_type === t.value ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600',
                                        !isEditing ? 'hover:bg-gray-200' : 'opacity-70 cursor-not-allowed']">
                                    {{ t.label }}
                                </button>
                            </div>
                            <p v-if="isEditing" class="text-xs text-gray-400 mt-2">Message type cannot be changed after
                                creation.</p>
                        </div>

                        <div v-if="form.message_type === 'text'">
                            <label class="form-label">Message <span class="text-red-500">*</span></label>
                            <textarea v-model="form.message_payload.message" class="form-input resize-none" rows="4"
                                placeholder="Hi {{name}}, …" maxlength="4096" />
                            <p class="text-xs text-gray-400 mt-1">Use <code
                                    class="bg-gray-100 px-1 rounded">{{ name }}</code>, <code
                                    class="bg-gray-100 px-1 rounded">{{ phone }}</code> for personalisation.</p>
                        </div>

                        <div v-if="['image', 'video'].includes(form.message_type)" class="space-y-3">
                            <div>
                                <label class="form-label">Media URL <span class="text-red-500">*</span></label>
                                <input v-model="form.message_payload.media_url" type="url" class="form-input"
                                    placeholder="https://…" />
                            </div>
                            <div>
                                <label class="form-label">Caption <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <input v-model="form.message_payload.caption" type="text" class="form-input"
                                    placeholder="Optional caption with {{name}}" />
                            </div>
                        </div>

                        <div v-if="form.message_type === 'document'" class="space-y-3">
                            <div>
                                <label class="form-label">Document URL <span class="text-red-500">*</span></label>
                                <input v-model="form.message_payload.media_url" type="url" class="form-input"
                                    placeholder="https://…" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="form-label">Filename</label>
                                    <input v-model="form.message_payload.filename" type="text" class="form-input"
                                        placeholder="document.pdf" />
                                </div>
                                <div>
                                    <label class="form-label">MIME type</label>
                                    <input v-model="form.message_payload.mimetype" type="text" class="form-input"
                                        placeholder="application/pdf" />
                                </div>
                            </div>
                        </div>

                        <div v-if="form.message_type === 'poll'" class="space-y-3">
                            <div>
                                <label class="form-label">Question <span class="text-red-500">*</span></label>
                                <input v-model="form.message_payload.question" type="text" class="form-input"
                                    maxlength="255" />
                            </div>
                            <div>
                                <label class="form-label">Options (min 2)</label>
                                <div class="space-y-2">
                                    <div v-for="(opt, idx) in form.message_payload.options" :key="idx"
                                        class="flex gap-2">
                                        <input v-model="form.message_payload.options[idx]" type="text"
                                            class="form-input" :placeholder="`Option ${idx + 1}`" />
                                        <button v-if="form.message_payload.options.length > 2"
                                            @click="removeOption(idx)" class="text-red-400 px-2">
                                            <XMarkIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <button v-if="form.message_payload.options.length < 12"
                                    @click="form.message_payload.options.push('')"
                                    class="text-blue-600 text-sm mt-2 hover:underline">+ Add option</button>
                            </div>
                        </div>

                        <div v-if="messagePreview" class="bg-green-50 border border-green-200 rounded-xl p-4">
                            <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-2">Preview (sample
                                recipient)</p>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ messagePreview }}</p>
                        </div>
                    </div>

                    <div v-show="step === 3" class="px-6 py-5 space-y-4">
                        <div class="flex gap-2">
                            <button @click="recipientMode = 'group'"
                                :class="['flex-1 py-2.5 rounded-xl text-sm font-medium border transition-colors',
                                    recipientMode === 'group' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200']">
                                From Contact Group
                            </button>
                            <button @click="recipientMode = 'phones'"
                                :class="['flex-1 py-2.5 rounded-xl text-sm font-medium border transition-colors',
                                    recipientMode === 'phones' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200']">
                                Manual Phone List
                            </button>
                        </div>

                        <div v-if="recipientMode === 'group'">
                            <label class="form-label">Contact Group</label>
                            <select v-model="form.contact_group_id" class="form-input">
                                <option :value="null">Select group…</option>
                                <option v-for="g in groups" :key="g.id" :value="g.id">
                                    {{ g.name }} ({{ g.contacts_count }} contacts)
                                </option>
                            </select>
                            <p v-if="!groups.length" class="text-xs text-orange-500 mt-1">
                                No groups yet. Create groups in the Contacts page first.
                            </p>
                        </div>

                        <div v-if="recipientMode === 'phones'">
                            <label class="form-label">Phone Numbers</label>
                            <textarea v-model="phonesRaw" class="form-input resize-none font-mono text-xs" rows="8"
                                placeholder="919876543210&#10;917788990011&#10;919012345678&#10;(one per line)" />
                            <p class="text-xs text-gray-400 mt-1">
                                {{ phoneCount }} number{{ phoneCount !== 1 ? 's' : '' }} detected.
                            </p>
                            <p v-if="isEditing" class="text-xs text-blue-500 mt-1">
                                Note: Saving new numbers will replace or append to existing recipients based on your
                                server logic.
                            </p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm space-y-1">
                            <p><strong>Campaign:</strong> {{ form.name || '—' }}</p>
                            <p><strong>Instance:</strong> {{ selectedInstance?.name || '—' }}</p>
                            <p><strong>Type:</strong> {{ form.message_type }}</p>
                            <p><strong>Recipients:</strong> {{ recipientMode === 'group' ? (selectedGroup?.name ?? '—')
                                : phoneCount + ' numbers' }}</p>
                            <p v-if="form.schedule_time"><strong>Scheduled:</strong> {{ formatDate(form.schedule_time)
                            }}</p>
                        </div>
                    </div>

                    <div v-if="serverError" class="px-6 py-3 bg-red-50 border-t border-red-100 text-sm text-red-700">
                        {{ serverError }}
                    </div>

                    <div class="flex gap-3 px-6 py-5 border-t sticky bottom-0 bg-white">
                        <button v-if="step > 1" class="btn-secondary flex-1" @click="step--">← Back</button>
                        <button v-if="step < 3" class="btn-primary flex-1" @click="nextStep">Next →</button>
                        <button v-if="step === 3" class="btn-primary flex-1" @click="submit" :disabled="saving">
                            {{ saving ? (isEditing ? 'Saving…' : 'Creating…') : (isEditing ? 'Save Changes' : 'Create Campaign') }}
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { campaignApi } from '@/composables/useApi'

const props = defineProps({
    show: { type: Boolean, required: true },
    instances: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    editCampaign: { type: Object, default: null }
})

const emit = defineEmits(['close', 'created', 'updated'])

const step = ref(1)
const saving = ref(false)
const serverError = ref(null)
const recipientMode = ref('group')
const phonesRaw = ref('')
const errors = reactive({})

const isEditing = computed(() => !!props.editCampaign)

const form = reactive({
    name: '',
    instance_id: null,
    message_type: 'text',
    message_payload: { message: '', options: ['', ''], media_url: '', caption: '', filename: '', mimetype: '', question: '' },
    schedule_time: '',
    send_window_start: '',
    send_window_end: '',
    contact_group_id: null,
})

// Populate form when modal opens
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        serverError.value = null
        Object.keys(errors).forEach(k => delete errors[k])
        step.value = 1

        if (props.editCampaign) {
            // Edit Flow
            form.name = props.editCampaign.name

            // Explicitly cast to Number so the <select> matches correctly
            form.instance_id = Number(props.editCampaign.instance_id || props.editCampaign.instance?.id) || null
            form.message_type = props.editCampaign.message_type

            // ✅ FIX 1: Safely parse JSON strings if Laravel didn't cast them automatically
            let payload = props.editCampaign.message_payload || {}
            
            if (typeof payload === 'string') {
                try { payload = JSON.parse(payload) } catch (e) { payload = {} }
            }

            // ✅ FIX 2: Explicitly assign deep properties to maintain Vue's reactivity bindings
            form.message_payload.message = payload.message || ''
            form.message_payload.media_url = payload.media_url || ''
            form.message_payload.caption = payload.caption || ''
            form.message_payload.filename = payload.filename || ''
            form.message_payload.mimetype = payload.mimetype || ''
            form.message_payload.question = payload.question || ''
            form.message_payload.options = Array.isArray(payload.options) && payload.options.length >= 2
                ? [...payload.options]
                : ['', '']

            form.schedule_time = props.editCampaign.schedule_time ? new Date(props.editCampaign.schedule_time).toISOString().slice(0, 16) : ''
            form.send_window_start = props.editCampaign.send_window_start ? props.editCampaign.send_window_start.slice(0, 5) : ''
            form.send_window_end = props.editCampaign.send_window_end ? props.editCampaign.send_window_end.slice(0, 5) : ''

            // ✅ FIX 3: Accurately Pre-fill Recipients
            if (props.editCampaign.contact_group_id) {
                recipientMode.value = 'group'
                // Explicit cast to Number for select binding
                form.contact_group_id = Number(props.editCampaign.contact_group_id)
                phonesRaw.value = ''
            } else {
                recipientMode.value = 'phones'
                form.contact_group_id = null
                // If backend provides the recipients array, load them into the manual phones box!
                if (props.editCampaign.recipients && Array.isArray(props.editCampaign.recipients)) {
                    phonesRaw.value = props.editCampaign.recipients.join('\n');
                } else if (props.editCampaign.phones && Array.isArray(props.editCampaign.phones)) {
                    phonesRaw.value = props.editCampaign.phones.join('\n')
                } else {
                    phonesRaw.value = ''
                }
            }

        } else {
            // Create Flow: Reset form completely
            phonesRaw.value = ''
            recipientMode.value = 'group'
            Object.assign(form, {
                name: '', instance_id: null, message_type: 'text',
                message_payload: { message: '', options: ['', ''], media_url: '', caption: '', filename: '', mimetype: '', question: '' },
                schedule_time: '', send_window_start: '', send_window_end: '', contact_group_id: null
            })
        }
    }
})

const messageTypes = [
    { value: 'text', label: 'Text' },
    { value: 'image', label: 'Image' },
    { value: 'video', label: 'Video' },
    { value: 'audio', label: 'Audio' },
    { value: 'document', label: 'Document' },
    { value: 'poll', label: 'Poll' },
]

const minSchedule = computed(() => new Date(Date.now() + 60000).toISOString().slice(0, 16))
const selectedInstance = computed(() => props.instances.find(i => i.id === form.instance_id))
const selectedGroup = computed(() => props.groups.find(g => g.id === form.contact_group_id))
const phoneCount = computed(() => phonesRaw.value.split('\n').map(l => l.trim()).filter(Boolean).length)

const messagePreview = computed(() => {
    const p = form.message_payload
    const text = p.message || p.caption || p.question || ''
    if (!text) return null
    return text.replace(/\{\{name\}\}/g, 'John').replace(/\{\{phone\}\}/g, '919876543210')
})

const removeOption = (idx) => {
    form.message_payload.options.splice(idx, 1)
}

const nextStep = () => {
    serverError.value = null
    if (step.value === 1) {
        if (!form.name) { serverError.value = 'Campaign name is required.'; return }
        if (!form.instance_id && !isEditing.value) { serverError.value = 'Select a WhatsApp instance.'; return }
    }

    if (step.value === 2 && !isEditing.value) {
        const p = form.message_payload
        if (form.message_type === 'text' && !p.message) { serverError.value = 'Message text is required.'; return }
        if (['image', 'video', 'document'].includes(form.message_type) && !p.media_url) { serverError.value = 'Media URL is required.'; return }
        if (form.message_type === 'poll' && !p.question) { serverError.value = 'Poll question is required.'; return }
    }

    step.value++
}

const submit = async () => {
    saving.value = true; serverError.value = null
    const phones = phonesRaw.value.split('\n').map(l => l.trim()).filter(Boolean)

    try {
        if (isEditing.value) {
            // Edit Payload
            const payload = {
                name: form.name,
                message_payload: form.message_payload,
                schedule_time: form.schedule_time || undefined,
                send_window_start: form.send_window_start || undefined,
                send_window_end: form.send_window_end || undefined,
                contact_group_id: recipientMode.value === 'group' ? form.contact_group_id : undefined,
                phones: recipientMode.value === 'phones' ? phones : undefined,
            }
            const { data } = await campaignApi.update(props.editCampaign.id, payload)
            emit('updated', data.data)

        } else {
            // Create Payload
            const payload = {
                name: form.name,
                instance_id: form.instance_id,
                message_type: form.message_type,
                message_payload: form.message_payload,
                schedule_time: form.schedule_time || undefined,
                send_window_start: form.send_window_start || undefined,
                send_window_end: form.send_window_end || undefined,
                contact_group_id: recipientMode.value === 'group' ? form.contact_group_id : undefined,
                phones: recipientMode.value === 'phones' ? phones : undefined,
            }
            const { data } = await campaignApi.create(payload)
            emit('created', data.data)
        }
    } catch (err) {
        if (err.response?.status === 422) Object.assign(errors, err.response.data.errors ?? {})
        serverError.value = err.response?.data?.message ?? (isEditing.value ? 'Failed to update campaign.' : 'Failed to create campaign.')
    } finally {
        saving.value = false
    }
}

const formatDate = (iso) => iso ? new Date(iso).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'
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
    transition: transform 0.25s ease;
}

.modal-enter-from>div:last-child {
    transform: scale(0.96);
}
</style>