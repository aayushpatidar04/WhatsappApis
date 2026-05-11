<template>
    <AppLayout title="API Tokens">
        <div class="max-w-3xl">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">API Tokens</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Bearer tokens for your external apps</p>
                </div>
                <button class="btn-primary" @click="showCreate = true">
                    <PlusIcon class="w-4 h-4" />
                    New Token
                </button>
            </div>

            <!-- Explanation -->
            <div class="card mb-6">
                <h2 class="card-title mb-3">What are these tokens for?</h2>
                <p class="text-sm text-gray-500 mb-3">
                    These tokens are for <strong>your own external applications</strong> — scripts, bots, CRM
                    integrations.
                    This dashboard uses your login session and does NOT need these tokens.
                </p>
                <div class="bg-gray-900 rounded-xl p-4 text-xs font-mono space-y-1">
                    <p class="text-gray-500"># From your external app:</p>
                    <p class="text-green-400">curl -X POST {{ appUrl }}/api/gateway/send/text \</p>
                    <p class="text-green-400 pl-4">-H "Authorization: Bearer <span
                            class="text-yellow-300">wap_your_token</span>" \</p>
                    <p class="text-green-400 pl-4">-H "X-Instance-Token: <span
                            class="text-blue-300">instance_token</span>" \</p>
                    <p class="text-green-400 pl-4">-d '{"to":"919876543210","message":"Hello!"}'</p>
                </div>
            </div>

            <!-- Token list -->
            <div class="card">
                <h2 class="card-title mb-4">Your Tokens</h2>

                <div v-if="loading" class="text-center py-10">
                    <div
                        class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto" />
                </div>

                <div v-else-if="!tokens.length" class="text-center py-10">
                    <KeyIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
                    <p class="text-gray-400 text-sm">No tokens yet. Create one to start using the external API.</p>
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
                                Created {{ fmt(token.created_at) }}
                                <template v-if="token.last_used_at">· Last used {{ fmt(token.last_used_at) }}</template>
                                <template v-if="token.expires_at">· Expires {{ fmt(token.expires_at) }}</template>
                                <template v-else>· Never expires</template>
                            </p>
                        </div>
                        <span v-if="token.is_expired" class="badge-expired text-xs">Expired</span>
                        <button @click="revokeToken(token)"
                            class="p-2 text-red-400 hover:bg-red-50 hover:text-red-600 rounded-lg" title="Revoke">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create modal -->
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

                        <!-- One-time plain token display -->
                        <div v-if="newToken" class="px-6 py-5">
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                                <p class="text-sm font-bold text-amber-800 mb-1">⚠ Copy now — never shown again</p>
                                <p class="text-xs text-amber-700 mb-3">
                                    Use this in your external app with
                                    <code class="bg-amber-100 px-1 rounded">Authorization: Bearer ...</code>
                                </p>
                                <div class="flex items-center gap-2 bg-white rounded-lg border border-amber-200 p-3">
                                    <code class="text-sm font-mono text-gray-800 flex-1 break-all select-all">{{ newToken
                                }}</code>
                                    <button @click="copyNewToken"
                                        class="text-amber-600 hover:text-amber-800 flex-shrink-0">
                                        <ClipboardDocumentCheckIcon v-if="copied" class="w-5 h-5 text-green-500" />
                                        <ClipboardDocumentIcon v-else class="w-5 h-5" />
                                    </button>
                                </div>
                                <p v-if="copied" class="text-xs text-green-600 mt-2 font-medium">✓ Copied!</p>
                            </div>
                            <button class="btn-primary w-full justify-center" @click="closeCreate">Done</button>
                        </div>

                        <!-- Create form -->
                        <form v-else @submit.prevent="createToken" class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Token Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="form-input"
                                    :class="{ 'border-red-400': errors.name }" placeholder="e.g. My CRM, Production Bot"
                                    required maxlength="100" />
                                <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                            </div>
                            <div>
                                <label class="form-label">Expiry <span
                                        class="text-gray-400 font-normal">(optional)</span></label>
                                <input v-model="form.expires_at" type="datetime-local" class="form-input"
                                    :min="minExpiry" />
                            </div>
                            <p v-if="serverError" class="text-sm text-red-600">{{ serverError }}</p>
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="btn-secondary flex-1" @click="closeCreate">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="creating">
                                    {{ creating ? 'Creating…' : 'Create Token' }}
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
import { ref, reactive, computed, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { tokenApi } from '@/composables/useApi'  // ← /dashboard/tokens/*
import { PlusIcon, TrashIcon, KeyIcon, XMarkIcon, ClipboardDocumentIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const tokens = ref([])
const loading = ref(true)
const showCreate = ref(false)
const creating = ref(false)
const newToken = ref(null)
const copied = ref(false)
const serverError = ref(null)
const errors = reactive({})
const form = reactive({ name: '', expires_at: '' })

const appUrl = window.location.origin
const minExpiry = computed(() => new Date(Date.now() + 60000).toISOString().slice(0, 16))

onMounted(fetchTokens)

async function fetchTokens() {
    loading.value = true
    try {
        const { data } = await tokenApi.list()  // GET /dashboard/tokens (session auth)
        tokens.value = data.data
    } catch (_) {
        tokens.value = []
    } finally {
        loading.value = false
    }
}

const createToken = async () => {
    creating.value = true; serverError.value = null
    Object.keys(errors).forEach(k => delete errors[k])
    try {
        const { data } = await tokenApi.create({  // POST /dashboard/tokens (session auth)
            name: form.name,
            expires_at: form.expires_at || undefined,
        })
        newToken.value = data.data.token  // plain token — shown once
        await fetchTokens()
    } catch (err) {
        if (err.response?.status === 422) Object.assign(errors, err.response.data.errors ?? {})
        else serverError.value = err.response?.data?.message ?? 'Failed.'
    } finally {
        creating.value = false
    }
}

const copyNewToken = async () => { await navigator.clipboard.writeText(newToken.value); copied.value = true }
const closeCreate = () => { showCreate.value = false; newToken.value = null; copied.value = false; form.name = ''; form.expires_at = ''; serverError.value = null }
const revokeToken = async (token) => {
    if (!confirm(`Revoke "${token.name}"? Apps using it will stop working.`)) return
    await tokenApi.revoke(token.id)  // DELETE /dashboard/tokens/{id} (session auth)
    await fetchTokens()
}

const fmt = (iso) => iso ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
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