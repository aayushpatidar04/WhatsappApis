<template>
    <AppLayout title="Settings">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">Workspace Settings</h1>
            <p class="text-sm text-gray-400 mt-0.5">Manage your account and company information</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 border-b border-gray-200">
            <button v-for="tab in ['company', 'profile', 'security', 'activity']" :key="tab" @click="activeTab = tab"
                :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px',
                    activeTab === tab ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
            </button>
        </div>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" :class="['fixed top-20 right-6 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-medium',
                toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white']">
                {{ toast.message }}
            </div>
        </Transition>

        <!-- Company Tab -->
        <div v-show="activeTab === 'company'">
            <div class="card max-w-2xl">
                <h2 class="text-lg font-semibold text-gray-900 mb-5">Company Information</h2>
                <form @submit.prevent="saveCompany" class="space-y-5">
                    <div>
                        <label class="form-label">Company Name</label>
                        <input v-model="company.name" type="text" class="form-input" maxlength="100" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Contact Email</label>
                            <input v-model="company.contact_email" type="email" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="company.contact_phone" type="tel" class="form-input" />
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Website</label>
                        <input v-model="company.website" type="url" class="form-input" placeholder="https://" />
                    </div>
                    <div>
                        <label class="form-label">Timezone</label>
                        <select v-model="company.timezone" class="form-input">
                            <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" :disabled="savingCompany">
                        {{ savingCompany ? 'Saving…' : 'Save Company Info' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Profile Tab -->
        <div v-show="activeTab === 'profile'">
            <div class="card max-w-2xl">
                <h2 class="text-lg font-semibold text-gray-900 mb-5">Your Profile</h2>
                <form @submit.prevent="saveProfile" class="space-y-5">
                    <div>
                        <label class="form-label">Full Name</label>
                        <input v-model="profile.name" type="text" class="form-input" maxlength="100" />
                    </div>
                    <div>
                        <label class="form-label">Email Address</label>
                        <input v-model="profile.email" type="email" class="form-input" />
                    </div>
                    <button type="submit" class="btn-primary" :disabled="savingProfile">
                        {{ savingProfile ? 'Saving…' : 'Save Profile' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div v-show="activeTab === 'security'">
            <div class="space-y-6">
                <!-- Password change -->
                <div class="card max-w-2xl">
                    <h2 class="text-lg font-semibold text-gray-900 mb-5">Change Password</h2>
                    <form @submit.prevent="changePassword" class="space-y-5">
                        <div>
                            <label class="form-label">Current Password</label>
                            <input v-model="pwd.current" type="password" class="form-input" required />
                        </div>
                        <div>
                            <label class="form-label">New Password</label>
                            <input v-model="pwd.password" type="password" class="form-input" minlength="8" required />
                        </div>
                        <div>
                            <label class="form-label">Confirm Password</label>
                            <input v-model="pwd.password_confirmation" type="password" class="form-input" required />
                        </div>
                        <button type="submit" class="btn-primary" :disabled="savingPwd">
                            {{ savingPwd ? 'Updating…' : 'Update Password' }}
                        </button>
                    </form>
                </div>

                <!-- Sessions -->
                <div class="card max-w-2xl">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Active Sessions</h2>
                    <p class="text-sm text-gray-600 mb-4">Sign out all other sessions on this account.</p>
                    <button @click="signOutOther" class="btn-secondary" :disabled="signingOut">
                        {{ signingOut ? 'Signing Out…' : 'Sign Out Other Sessions' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Activity Tab -->
        <div v-show="activeTab === 'activity'">
            <div class="card">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h2>
                <div v-if="loadingActivity" class="text-center py-8">
                    <div
                        class="w-6 h-6 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto" />
                </div>
                <div v-else-if="!activity.length" class="text-center py-8 text-gray-400 text-sm">
                    No activity yet.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="log in activity" :key="log.id"
                        class="flex items-start gap-3 pb-3 border-b border-gray-100 last:border-0 last:pb-0">
                        <div class="w-2 h-2 rounded-full bg-blue-600 mt-1.5 flex-shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ log.event }}</p>
                            <p class="text-xs text-gray-400">{{ formatTime(log.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import webHttp from '@/composables/useApi'

const props = defineProps({
    client_name: String,
    client_contact_email: String,
    user_name: String,
    user_email: String,
})

const activeTab = ref('company')
const savingCompany = ref(false)
const savingProfile = ref(false)
const savingPwd = ref(false)
const signingOut = ref(false)
const loadingActivity = ref(false)
const activity = ref([])

const company = reactive({
    name: props.client_name,
    contact_email: props.client_contact_email,
    contact_phone: '',
    website: '',
    timezone: 'Asia/Kolkata',
})

const profile = reactive({
    name: props.user_name,
    email: props.user_email,
})

const pwd = reactive({
    current: '',
    password: '',
    password_confirmation: '',
})

const toast = reactive({ show: false, message: '', type: 'success' })

const timezones = ['Asia/Kolkata', 'Asia/Dubai', 'UTC', 'Europe/London', 'America/New_York']

const showToast = (msg, type = 'success') => {
    toast.message = msg
    toast.type = type
    toast.show = true
    setTimeout(() => { toast.show = false }, 3500)
}

const saveCompany = async () => {
    savingCompany.value = true
    try {
        await webHttp.patch('/client/settings/company', company)
        showToast('Company information saved.')
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Failed.', 'error')
    } finally {
        savingCompany.value = false
    }
}

const saveProfile = async () => {
    savingProfile.value = true
    try {
        await webHttp.patch('/client/settings/profile', profile)
        showToast('Profile updated.')
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Failed.', 'error')
    } finally {
        savingProfile.value = false
    }
}

const changePassword = async () => {
    savingPwd.value = true
    try {
        await webHttp.post('/client/settings/change-password', pwd)
        showToast('Password changed successfully.')
        pwd.current = ''
        pwd.password = ''
        pwd.password_confirmation = ''
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Failed.', 'error')
    } finally {
        savingPwd.value = false
    }
}

const signOutOther = async () => {
    signingOut.value = true
    try {
        await webHttp.delete('/client/settings/sessions')
        showToast('Other sessions signed out.')
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Failed.', 'error')
    } finally {
        signingOut.value = false
    }
}

const formatTime = (iso) => {
    const d = new Date(iso)
    return d.toLocaleString('en-IN', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>