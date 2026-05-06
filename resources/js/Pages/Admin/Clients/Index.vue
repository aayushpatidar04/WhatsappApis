<template>
    <AppLayout title="Client Management">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Clients</h1>
                <p class="text-sm text-gray-400 mt-0.5">{{ clients.total }} tenant{{ clients.total !== 1 ? 's' : '' }}
                    on the platform</p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                New Client
            </button>
        </div>

        <!-- Clients table -->
        <div class="card">
            <div v-if="!clients.data.length" class="text-center py-16">
                <BuildingOfficeIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
                <p class="text-gray-400 text-sm">No clients yet. Create your first tenant.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Client</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Users</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Instances</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Credits</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Status</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Created</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50 transition-colors">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                                        <span class="text-purple-700 text-xs font-bold">{{ client.name.charAt(0)
                                            }}</span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ client.name }}</p>
                                        <p class="text-xs text-gray-400">{{ client.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-gray-600">{{ client.users_count }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ client.all_instances_count }}</td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-green-700">{{ client.credit_balance }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span :class="client.is_active ? 'badge-active' : 'badge-suspended'">
                                    {{ client.is_active ? 'Active' : 'Suspended' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ formatDate(client.created_at) }}</td>
                            <td class="py-3 px-4">
                                <div class="flex gap-1 justify-end">
                                    <button class="p-1.5 hover:bg-blue-50 text-blue-500 rounded-lg"
                                        @click="openCredit(client)" title="Add credits">
                                        <CreditCardIcon class="w-4 h-4" />
                                    </button>
                                    <button class="p-1.5 hover:bg-gray-100 text-gray-500 rounded-lg"
                                        @click="toggleActive(client)"
                                        :title="client.is_active ? 'Suspend' : 'Reactivate'">
                                        <component :is="client.is_active ? PauseIcon : PlayIcon" class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Client Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showCreate = false" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Create New Client</h2>
                            <button @click="showCreate = false" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="createClient" class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Business Name <span class="text-red-500">*</span></label>
                                <input v-model="form.client_name" type="text" class="form-input" required
                                    maxlength="255" placeholder="Acme Corp" />
                                <p v-if="errors.client_name" class="form-error">{{ errors.client_name }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Max Rate (msg/min)</label>
                                    <input v-model.number="form.max_rate_per_minute" type="number" class="form-input"
                                        min="5" max="60" />
                                </div>
                                <div>
                                    <label class="form-label">Max Instances/User</label>
                                    <input v-model.number="form.max_instances_per_user" type="number" class="form-input"
                                        min="1" max="50" />
                                </div>
                            </div>
                            <hr class="border-gray-100" />
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Master Admin Account
                            </p>
                            <div>
                                <label class="form-label">Admin Name <span class="text-red-500">*</span></label>
                                <input v-model="form.admin_name" type="text" class="form-input" required
                                    maxlength="255" />
                                <p v-if="errors.admin_name" class="form-error">{{ errors.admin_name }}</p>
                            </div>
                            <div>
                                <label class="form-label">Admin Email <span class="text-red-500">*</span></label>
                                <input v-model="form.admin_email" type="email" class="form-input" required />
                                <p v-if="errors.admin_email" class="form-error">{{ errors.admin_email }}</p>
                            </div>
                            <div>
                                <label class="form-label">Admin Password <span class="text-red-500">*</span></label>
                                <input v-model="form.admin_password" type="password" class="form-input" required
                                    minlength="8" />
                                <p v-if="errors.admin_password" class="form-error">{{ errors.admin_password }}</p>
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="btn-secondary flex-1"
                                    @click="showCreate = false">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="loading">
                                    {{ loading ? 'Creating…' : 'Create Client' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Add Credits Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="creditClient" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="creditClient = null" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                        <div class="px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Add Credits — {{ creditClient.name }}</h2>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Credits to Add</label>
                                <input v-model.number="creditAmount" type="number" class="form-input" min="1"
                                    placeholder="e.g. 10" />
                            </div>
                            <div>
                                <label class="form-label">Reference / Note</label>
                                <input v-model="creditRef" type="text" class="form-input"
                                    placeholder="e.g. Invoice #1234" />
                            </div>
                            <div class="flex gap-3">
                                <button class="btn-secondary flex-1" @click="creditClient = null">Cancel</button>
                                <button class="btn-primary flex-1" @click="addCredits" :disabled="!creditAmount">Add
                                    Credits</button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import {
    PlusIcon, BuildingOfficeIcon, CreditCardIcon,
    XMarkIcon, PauseIcon, PlayIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    clients: { type: Object, default: () => ({ data: [], total: 0 }) },
})

const showCreate = ref(false)
const loading = ref(false)
const errors = reactive({})
const creditClient = ref(null)
const creditAmount = ref(null)
const creditRef = ref('')

const form = reactive({
    client_name: '', admin_name: '', admin_email: '', admin_password: '',
    max_rate_per_minute: 20, max_instances_per_user: 5,
})

const createClient = async () => {
    loading.value = true
    Object.keys(errors).forEach(k => delete errors[k])
    try {
        await axios.post(route('super.clients.store'), form)
        showCreate.value = false
        Object.assign(form, { client_name: '', admin_name: '', admin_email: '', admin_password: '', max_rate_per_minute: 20, max_instances_per_user: 5 })
        router.reload({ only: ['clients'] })
    } catch (err) {
        if (err.response?.status === 422) Object.assign(errors, err.response.data.errors ?? {})
    } finally { loading.value = false }
}

const toggleActive = async (client) => {
    if (!confirm(`${client.is_active ? 'Suspend' : 'Reactivate'} "${client.name}"?`)) return
    await axios.patch(route('super.clients.update', client.id), { is_active: !client.is_active })
    router.reload({ only: ['clients'] })
}

const openCredit = (client) => {
    creditClient.value = client
    creditAmount.value = null
    creditRef.value = ''
}

const addCredits = async () => {
    if (!creditAmount.value) return
    await axios.post(route('super.credits.adjust'), {
        owner_type: 'client',
        owner_id: creditClient.value.id,
        credits: creditAmount.value,
        reference: creditRef.value || `Manual top-up by Super Admin`,
    })
    creditClient.value = null
    router.reload({ only: ['clients'] })
}

const formatDate = (iso) => new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
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