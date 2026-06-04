<template>
    <AppLayout title="Message Templates">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Message Templates</h1>
                <p class="text-sm text-gray-400 mt-0.5">Save and reuse message templates with variables</p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                New Template
            </button>
        </div>

        <!-- Filter by category -->
        <div class="flex gap-2 mb-5">
            <button v-for="cat in ['all', 'text', 'image', 'video', 'document', 'poll']" :key="cat"
                @click="filter.category = cat"
                :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                    filter.category === cat ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']">
                {{ cat === 'all' ? 'All Templates' : cat.charAt(0).toUpperCase() + cat.slice(1) }}
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="!templates.length" class="card text-center py-14">
            <DocumentTextIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
            <h3 class="text-base font-semibold text-gray-700">No templates yet</h3>
            <p class="text-gray-400 text-sm mt-1">Create templates to save and reuse messages quickly.</p>
            <button class="btn-primary btn-sm mt-4" @click="showCreate = true">Create Template</button>
        </div>

        <!-- Templates grid -->
        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="t in filteredTemplates" :key="t.id" class="card hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-gray-900 truncate">{{ t.name }}</h3>
                        <span
                            class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full capitalize mt-1 inline-block">
                            {{ t.category }}
                        </span>
                    </div>
                    <button @click="editTemplate(t)"
                        class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 flex-shrink-0">
                        <PencilIcon class="w-4 h-4" />
                    </button>
                </div>
                <p class="text-sm text-gray-600 line-clamp-2">{{ t.body }}</p>
                <p v-if="t.description" class="text-xs text-gray-400 mt-2 line-clamp-1">{{ t.description }}</p>
                <button @click="deleteTemplate(t)" class="text-xs text-red-500 mt-3 hover:underline">Delete</button>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate || editingTemplate"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="closeForm" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">{{ editingTemplate ? 'Edit Template' : 'New Template' }}
                            </h2>
                            <button @click="closeForm" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="save" class="px-6 py-5 space-y-4 max-h-[80vh] overflow-y-auto">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Name <span class="text-red-500">*</span></label>
                                    <input v-model="form.name" type="text" class="form-input" required maxlength="100"
                                        placeholder="e.g. Welcome Message" />
                                </div>
                                <div>
                                    <label class="form-label">Category <span class="text-red-500">*</span></label>
                                    <select v-model="form.category" class="form-input" required>
                                        <option value="text">Text</option>
                                        <option value="image">Image</option>
                                        <option value="video">Video</option>
                                        <option value="document">Document</option>
                                        <option value="poll">Poll</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Message Body <span class="text-red-500">*</span></label>
                                <textarea v-model="form.body" class="form-input resize-none" rows="4" required
                                    maxlength="4096" placeholder="Hi {{name}}, welcome to our service..." />
                                <p class="text-xs text-gray-400 mt-1">Use {{ name }}, {{ phone }}, {{ email }} for variables.
                                </p>
                            </div>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700">
                                <strong>Preview:</strong> {{ preview || '(no variables)' }}
                            </div>
                            <div>
                                <label class="form-label">Description <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <textarea v-model="form.description" class="form-input resize-none text-sm" rows="2"
                                    maxlength="500" placeholder="When to use this template..." />
                            </div>
                            <div class="flex gap-3 pt-4">
                                <button type="button" class="btn-secondary flex-1" @click="closeForm">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="saving">{{ saving ?
                                    'Saving…' : 'Save Template' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { PlusIcon, PencilIcon, DocumentTextIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const props = defineProps({ templates: { type: Array, default: () => [] } })

const templates = ref(props.templates ?? [])
const showCreate = ref(false)
const editingTemplate = ref(null)
const saving = ref(false)
const filter = reactive({ category: 'all' })
const form = reactive({ name: '', category: 'text', body: '', description: '', variables: [] })

const filteredTemplates = computed(() => {
    if (filter.category === 'all') return templates.value
    return templates.value.filter(t => t.category === filter.category)
})

const preview = computed(() => {
    let text = form.body
    text = text.replace(/\{\{name\}\}/g, 'John').replace(/\{\{phone\}\}/g, '+91XXXXXXXXXX').replace(/\{\{email\}\}/g, 'john@example.com')
    return text.substring(0, 100)
})

const editTemplate = (t) => {
    editingTemplate.value = t
    Object.assign(form, { name: t.name, category: t.category, body: t.body, description: t.description })
}

const closeForm = () => {
    showCreate.value = false
    editingTemplate.value = null
    Object.assign(form, { name: '', category: 'text', body: '', description: '', variables: [] })
}

const save = async () => {
    saving.value = true
    try {
        if (editingTemplate.value) {
            const { data } = await webHttp.patch(`/client/templates/${editingTemplate.value.id}`, form)
            const idx = templates.value.findIndex(t => t.id === editingTemplate.value.id)
            if (idx !== -1) templates.value[idx] = data.data
        } else {
            const { data } = await webHttp.post('/client/templates', form)
            templates.value.unshift(data.data)
        }
        closeForm()
    } catch (err) {
        alert(err.response?.data?.message ?? 'Failed to save.')
    } finally {
        saving.value = false
    }
}

const deleteTemplate = async (t) => {
    if (!confirm(`Delete template "${t.name}"?`)) return
    await webHttp.delete(`/client/templates/${t.id}`)
    templates.value = templates.value.filter(x => x.id !== t.id)
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>