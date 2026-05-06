<template>
    <AppLayout title="User Management">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Users</h1>
                <p class="text-sm text-gray-400 mt-0.5">{{ users.total }} user{{ users.total !== 1 ? 's' : '' }} in your
                    account</p>
            </div>
            <button class="btn-primary" @click="showCreate = true">
                <PlusIcon class="w-4 h-4" />
                Add User
            </button>
        </div>

        <div class="card">
            <div v-if="!users.data.length" class="text-center py-16">
                <UsersIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
                <p class="text-gray-500 text-sm font-medium">No users yet</p>
                <p class="text-gray-400 text-xs mt-1">Add users and assign credits so they can create WhatsApp
                    instances.</p>
                <button class="btn-primary btn-sm mt-4" @click="showCreate = true">Add First User</button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                User</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Credits</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Instances</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Status</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                Joined</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <span class="text-blue-700 text-xs font-bold">{{ user.name.charAt(0) }}</span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ user.name }}</p>
                                        <p class="text-xs text-gray-400">{{ user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-green-700">{{ user.credit_balance }}</span>
                                <button class="ml-2 text-blue-500 hover:text-blue-700" @click="openAllocate(user)"
                                    title="Allocate credits">
                                    <PlusCircleIcon class="w-4 h-4 inline" />
                                </button>
                            </td>
                            <td class="py-3 px-4 text-gray-600">{{ user.instances_count }}</td>
                            <td class="py-3 px-4">
                                <span :class="user.is_active ? 'badge-active' : 'badge-suspended'">
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ formatDate(user.created_at) }}</td>
                            <td class="py-3 px-4">
                                <button class="p-1.5 hover:bg-gray-100 text-gray-400 rounded-lg"
                                    @click="toggleActive(user)" :title="user.is_active ? 'Deactivate' : 'Activate'">
                                    <component :is="user.is_active ? PauseIcon : PlayIcon" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create User Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showCreate = false" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="flex items-center justify-between px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Add New User</h2>
                            <button @click="showCreate = false" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                                <XMarkIcon class="w-5 h-5" />
                            </button>
                        </div>
                        <form @submit.prevent="createUser" class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" class="form-input" required />
                                <p v-if="errors.name" class="form-error">{{ errors.name }}</p>
                            </div>
                            <div>
                                <label class="form-label">Email <span class="text-red-500">*</span></label>
                                <input v-model="form.email" type="email" class="form-input" required />
                                <p v-if="errors.email" class="form-error">{{ errors.email }}</p>
                            </div>
                            <div>
                                <label class="form-label">Password <span class="text-red-500">*</span></label>
                                <input v-model="form.password" type="password" class="form-input" required
                                    minlength="8" />
                                <p v-if="errors.password" class="form-error">{{ errors.password }}</p>
                            </div>
                            <div>
                                <label class="form-label">Initial Credits</label>
                                <input v-model.number="form.credits" type="number" class="form-input" min="0"
                                    :max="clientCredits" />
                                <p class="text-xs text-gray-400 mt-1">Available in client wallet: {{ clientCredits }}
                                </p>
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button type="button" class="btn-secondary flex-1"
                                    @click="showCreate = false">Cancel</button>
                                <button type="submit" class="btn-primary flex-1" :disabled="loading">
                                    {{ loading ? 'Creating…' : 'Create User' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Allocate Credits Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="allocateUser" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="allocateUser = null" />
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                        <div class="px-6 py-5 border-b">
                            <h2 class="font-bold text-gray-900">Allocate Credits — {{ allocateUser.name }}</h2>
                            <p class="text-xs text-gray-400 mt-1">Client wallet: {{ clientCredits }} credits available
                            </p>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <div>
                                <label class="form-label">Credits to Allocate</label>
                                <input v-model.number="allocateAmount" type="number" class="form-input" min="1"
                                    :max="clientCredits" />
                            </div>
                            <div class="bg-blue-50 text-blue-700 text-xs rounded-lg p-3">
                                After allocation: user balance will be
                                <strong>{{ (allocateUser.credit_balance || 0) + (allocateAmount || 0) }}</strong>
                                credits.
                            </div>
                            <div class="flex gap-3">
                                <button class="btn-secondary flex-1" @click="allocateUser = null">Cancel</button>
                                <button class="btn-primary flex-1" @click="doAllocate"
                                    :disabled="!allocateAmount || allocateAmount > clientCredits">
                                    Allocate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { PlusIcon, UsersIcon, XMarkIcon, PauseIcon, PlayIcon, PlusCircleIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
    users: { type: Object, default: () => ({ data: [], total: 0 }) },
})

const page = usePage()
const clientCredits = computed(() => page.props.auth.client?.credit_balance ?? 0)

const showCreate = ref(false)
const loading = ref(false)
const errors = reactive({})
const allocateUser = ref(null)
const allocateAmount = ref(null)

const form = reactive({ name: '', email: '', password: '', credits: 0 })

const createUser = async () => {
    loading.value = true
    Object.keys(errors).forEach(k => delete errors[k])
    try {
        await axios.post(route('client.users.store'), form)
        showCreate.value = false
        Object.assign(form, { name: '', email: '', password: '', credits: 0 })
        router.reload({ only: ['users'] })
    } catch (err) {
        if (err.response?.status === 422) Object.assign(errors, err.response.data.errors ?? {})
    } finally { loading.value = false }
}

const toggleActive = async (user) => {
    if (!confirm(`${user.is_active ? 'Deactivate' : 'Activate'} "${user.name}"?`)) return
    await axios.patch(route('client.users.update', user.id), { is_active: !user.is_active })
    router.reload({ only: ['users'] })
}

const openAllocate = (user) => { allocateUser.value = user; allocateAmount.value = null }

const doAllocate = async () => {
    if (!allocateAmount.value) return
    await axios.post(route('client.users.credits', allocateUser.value.id), { credits: allocateAmount.value })
    allocateUser.value = null
    router.reload({ only: ['users'] })
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