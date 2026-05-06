<template>
    <AppLayout title="API Tokens">
        <div class="max-w-3xl">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">API Tokens</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Manage bearer tokens for API access</p>
                </div>
                <button class="btn-primary" @click="showCreate = true">
                    <PlusIcon class="w-4 h-4" />
                    New Token
                </button>
            </div>

            <!-- How to use -->
            <div class="card mb-6">
                <h2 class="card-title mb-3">How to Authenticate</h2>
                <p class="text-sm text-gray-500 mb-3">
                    Include your API token in every request header. Pair it with an
                    <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs font-mono">X-Instance-Token</code>
                    to route messages through a specific WhatsApp number.
                </p>
                <div class="bg-gray-900 rounded-xl p-4 text-xs font-mono text-green-400">
                    <p class="text-gray-500 mb-2"># Example API call</p>
                    <p>curl -X GET {{ appUrl }}/api/me \</p>
                    <p class="pl-4">-H "Authorization: Bearer <span class="text-yellow-400">wap_your_token_here</span>"
                        \</p>
                    <p class="pl-4">-H "X-Instance-Token: <span class="text-blue-400">your_instance_token</span>"</p>
                </div>
            </div>

            <!-- Token list -->
            <div class="card">
                <h2 class="card-title mb-4">Active Tokens</h2>

                <div v-if="!tokens.length" class="text-center py-10">
                    <KeyIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
                    <p class="text-gray-400 text-sm">No tokens yet. Create one to start using the API.</p>
                </div>

                <div v-else class="divide-y divide-gray-100">
                    <div v-for="token in tokens" :key="token.id"
                        class="flex items-center gap-4 py-4 first:pt-0 last:pb-0">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
                            <KeyIcon class="w-5 h-5 text-blue-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 text-sm">{{ token.name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Created {{ formatDate(token.created_at) }}
                                <span v-if="token.last_used_at">· Last used {{ formatDate(token.last_used_at) }}</span>
                                <span v-if="token.expires_at">· Expires {{ formatDate(token.expires_at) }}</span>
                                <span v-else>· Never expires</span>
                            </p>
                        </div>
                        <button @click="revokeToken(token)"
                            class="text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors flex-shrink-0"
                            title="Revoke token">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create token modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="closeCreate" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Create API Token</h2>
                            <button @click="closeCreate" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Show new token (one-time) -->
                        <div v-if="newToken" class="px-6 py-5">
                            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
                                <p class="text-sm font-semibold text-green-800 mb-2">
                                    ⚠ Copy your token now — it won't be shown again
                                </p>
                                <div class="flex items-center gap-2 bg-white rounded-lg border border-green-200 p-3">
                                    <code class="text-sm font-mono text-gray-800 flex-1 break-all">{{ newToken }}</code>
                                    <button @click="copyToken" class="text-green-600 flex-shrink-0">
                                        <ClipboardDocumentIcon class="w-5 h-5" />
                                    </button>
                                </div>
                                <p v-if="copied" class="text-xs text-green-600 mt-2">✓ Copied to clipboard!</p>
                            </div>
                            <button class="btn-primary w-full justify-center" @click="closeCreate">Done</button>
                        </div>

                        <!-- Create form -->
                        <form v-else @submit.prevent="createToken" class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Token Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="form-input"
                                    placeholder="e.g. Production App, My CRM" required maxlength="100" />
                                <p v-if="errors.name" class="form-error">{{ errors.name }}</p>
                            </div>
                            <div>
                                <label class="form-label">Expiry Date <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <input v-model="form.expires_at" type="datetime-local" class="form-input"
                                    :min="minExpiry" />
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="btn-secondary flex-1" @click="closeCreate">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="loading">
                                    {{ loading ? 'Creating…' : 'Create Token' }}
                                </button>
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
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { PlusIcon, TrashIcon, KeyIcon, XMarkIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    tokens: { type: Array, default: () => [] },
})

const appUrl = window.location.origin
const showCreate = ref(false)
const loading = ref(false)
const newToken = ref(null)
const copied = ref(false)
const errors = reactive({})
const form = reactive({ name: '', expires_at: '' })

const minExpiry = computed(() => new Date().toISOString().slice(0, 16))

const closeCreate = () => {
    showCreate.value = false
    newToken.value = null
    copied.value = false
    form.name = ''
    form.expires_at = ''
}

const createToken = async () => {
    loading.value = true
    Object.keys(errors).forEach(k => delete errors[k])
    try {
        const { data } = await axios.post('/api/tokens', {
            name: form.name,
            expires_at: form.expires_at || undefined,
        })
        newToken.value = data.data.token
        router.reload({ only: ['tokens'] })
    } catch (err) {
        if (err.response?.status === 422) {
            Object.assign(errors, err.response.data.errors ?? {})
        }
    } finally {
        loading.value = false
    }
}

const copyToken = async () => {
    await navigator.clipboard.writeText(newToken.value)
    copied.value = true
}

const revokeToken = async (token) => {
    if (!confirm(`Revoke token "${token.name}"? Any app using it will stop working.`)) return
    await axios.delete(`/api/tokens/${token.id}`)
    router.reload({ only: ['tokens'] })
}

const formatDate = (iso) => iso
    ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—'
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
</style>