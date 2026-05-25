<template>
    <AppLayout title="Platform Settings">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Platform Settings</h1>
                <p class="text-sm text-gray-400 mt-0.5">Configure global platform behaviour</p>
            </div>
            <div class="flex gap-2">
                <button @click="clearCache" :disabled="caching" class="btn-secondary btn-sm">
                    <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': caching }" />
                    Clear Cache
                </button>
                <button @click="rebuildCache" :disabled="caching" class="btn-secondary btn-sm">
                    <BoltIcon class="w-4 h-4" />
                    Rebuild Cache
                </button>
            </div>
        </div>

        <!-- Tab navigation -->
        <div class="flex gap-1 mb-6 border-b border-gray-200">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key" :class="[
                'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px',
                activeTab === tab.key
                    ? 'border-blue-600 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]">
                <component :is="tab.icon" class="w-4 h-4 inline mr-1.5 -mt-0.5" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" :class="['fixed top-20 right-6 z-50 px-4 py-3 rounded-xl shadow-2xl text-sm font-medium flex items-center gap-3',
                toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white']">
                <CheckCircleIcon v-if="toast.type === 'success'" class="w-5 h-5 flex-shrink-0" />
                <XCircleIcon v-else class="w-5 h-5 flex-shrink-0" />
                {{ toast.message }}
            </div>
        </Transition>

        <!-- ── GENERAL TAB ─────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'general'">
            <SettingsCard title="Platform Identity" description="Basic platform name and contact information.">
                <SettingsRow label="Platform Name" description="Shown in emails, dashboard header and browser tab.">
                    <input v-model="form.app_name" type="text" class="form-input" maxlength="100" />
                </SettingsRow>
                <SettingsRow label="Support Email" description="Clients contact this for billing and support.">
                    <input v-model="form.support_email" type="email" class="form-input" />
                </SettingsRow>
                <SettingsRow label="Platform Timezone"
                    description="Used for reports, expiry checks and campaign scheduling.">
                    <select v-model="form.timezone" class="form-input">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                </SettingsRow>
                <SettingsRow label="Maintenance Mode"
                    description="Show a maintenance page to all non-super-admin users.">
                    <ToggleSwitch v-model="form.maintenance_mode" />
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('general')" class="btn-primary btn-sm" :disabled="saving === 'general'">
                        {{ saving === 'general' ? 'Saving…' : 'Save General Settings' }}
                    </button>
                </div>
            </SettingsCard>
        </div>

        <!-- ── LIMITS TAB ──────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'limits'">
            <SettingsCard title="Message Rate Limits"
                description="Control how many messages per minute users can send.">
                <SettingsRow label="Default Rate (msg/min)"
                    description="Applied to all new clients. Individual clients can override lower.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.default_rate_per_minute" type="number"
                            class="form-input w-24 text-center" min="5" max="200" />
                        <span class="text-sm text-gray-400">msg/min</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Minimum Allowed (msg/min)" description="No user or instance can be set below this.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.min_rate_per_minute" type="number"
                            class="form-input w-24 text-center" min="1" max="60" />
                        <span class="text-sm text-gray-400">msg/min</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Maximum Allowed (msg/min)" description="Ceiling a client can grant to their users.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.max_rate_per_minute" type="number"
                            class="form-input w-24 text-center" min="5" max="300" />
                        <span class="text-sm text-gray-400">msg/min</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Max Bulk Recipients"
                    description="Maximum recipients allowed in a single bulk send API call.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.max_bulk_recipients" type="number"
                            class="form-input w-32 text-center" min="1" max="10000" />
                        <span class="text-sm text-gray-400">recipients</span>
                    </div>
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('limits')" class="btn-primary btn-sm" :disabled="saving === 'limits'">
                        {{ saving === 'limits' ? 'Saving…' : 'Save Limit Settings' }}
                    </button>
                </div>
            </SettingsCard>

            <SettingsCard title="Instance Limits" description="Per-user instance quotas.">
                <SettingsRow label="Max Instances / User"
                    description="Default maximum WhatsApp instances a single user can create.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.max_instances_per_user" type="number"
                            class="form-input w-24 text-center" min="1" max="100" />
                        <span class="text-sm text-gray-400">instances</span>
                    </div>
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('limits')" class="btn-primary btn-sm" :disabled="saving === 'limits'">
                        {{ saving === 'limits' ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </SettingsCard>
        </div>

        <!-- ── INSTANCE TAB ────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'instance'">
            <SettingsCard title="Instance Lifecycle"
                description="Control expiry warnings, grace periods, and reconnect behaviour.">
                <SettingsRow label="Grace Period (Days)"
                    description="Days after expiry before session data is permanently purged.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.instance_grace_days" type="number"
                            class="form-input w-24 text-center" min="1" max="30" />
                        <span class="text-sm text-gray-400">days</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="First Expiry Warning"
                    description="Days before expiry to send the first warning notification.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.expiry_warn_days_first" type="number"
                            class="form-input w-24 text-center" min="1" max="60" />
                        <span class="text-sm text-gray-400">days before</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Second Expiry Warning"
                    description="Days before expiry to send the urgent warning notification.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.expiry_warn_days_second" type="number"
                            class="form-input w-24 text-center" min="1" max="30" />
                        <span class="text-sm text-gray-400">days before</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Max Reconnect Attempts"
                    description="Auto-reconnect attempts before marking instance disconnected.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.max_reconnect_attempts" type="number"
                            class="form-input w-24 text-center" min="1" max="20" />
                        <span class="text-sm text-gray-400">attempts</span>
                    </div>
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('instance')" class="btn-primary btn-sm" :disabled="saving === 'instance'">
                        {{ saving === 'instance' ? 'Saving…' : 'Save Instance Settings' }}
                    </button>
                </div>
            </SettingsCard>
        </div>

        <!-- ── PAYMENT TAB ─────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'payment'">
            <SettingsCard title="Payment Gateway" description="Configure how clients purchase credits.">
                <SettingsRow label="Active Gateway" description="Which payment processor handles credit purchases.">
                    <select v-model="form.payment_gateway" class="form-input w-auto">
                        <option value="razorpay">Razorpay</option>
                        <option value="stripe">Stripe</option>
                        <option value="manual">Manual (admin only)</option>
                    </select>
                </SettingsRow>
                <SettingsRow label="Default Currency" description="Currency for all credit package pricing.">
                    <select v-model="form.currency" class="form-input w-auto">
                        <option value="INR">INR (₹) — Indian Rupee</option>
                        <option value="USD">USD ($) — US Dollar</option>
                        <option value="EUR">EUR (€) — Euro</option>
                        <option value="GBP">GBP (£) — British Pound</option>
                        <option value="AED">AED — UAE Dirham</option>
                    </select>
                </SettingsRow>
                <SettingsRow label="Enable Self-Service Billing"
                    description="Let client admins buy credits directly from dashboard.">
                    <ToggleSwitch v-model="form.enable_billing" />
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('payment')" class="btn-primary btn-sm" :disabled="saving === 'payment'">
                        {{ saving === 'payment' ? 'Saving…' : 'Save Payment Settings' }}
                    </button>
                </div>
            </SettingsCard>

            
            <div class="card bg-amber-50 border border-amber-200">
                <h3 class="text-sm font-semibold text-amber-800 mb-2">Gateway Credentials</h3>
                <p class="text-xs text-amber-700 mb-3">
                    API keys for Razorpay and Stripe are stored in your <code
                        class="bg-amber-100 px-1 rounded">.env</code> file, not in the database.
                    Edit the server's .env file to change them.
                </p>
                <div class="grid grid-cols-2 gap-3 text-xs font-mono text-amber-700">
                    <div class="bg-amber-100 rounded-lg p-3">
                        <p class="font-semibold mb-1">Razorpay</p>
                        <p>RAZORPAY_KEY_ID</p>
                        <p>RAZORPAY_KEY_SECRET</p>
                    </div>
                    <div class="bg-amber-100 rounded-lg p-3">
                        <p class="font-semibold mb-1">Stripe</p>
                        <p>STRIPE_PUBLISHABLE_KEY</p>
                        <p>STRIPE_SECRET_KEY</p>
                        <p>STRIPE_WEBHOOK_SECRET</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── NOTIFICATIONS TAB ───────────────────────────────────────────────── -->
        <div v-show="activeTab === 'notifications'">
            <SettingsCard title="Email Notifications" description="Control which automated emails the platform sends.">
                <SettingsRow label="Expiry Warning Emails"
                    description="Send email to users when their instance is about to expire.">
                    <ToggleSwitch v-model="form.notify_expiry_email" />
                </SettingsRow>
                <SettingsRow label="Credit Low Alerts"
                    description="Alert client admin when their wallet balance reaches zero.">
                    <ToggleSwitch v-model="form.notify_credit_low" />
                </SettingsRow>
                <SettingsRow label="Campaign Complete Email"
                    description="Send summary email to user when a campaign finishes.">
                    <ToggleSwitch v-model="form.notify_campaign_done" />
                </SettingsRow>
                <SettingsRow label="Admin Alert Email"
                    description="Email for critical alerts (Baileys down, high queue depth).">
                    <input v-model="form.admin_alert_email" type="email" class="form-input"
                        placeholder="admin@yourcompany.com" />
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('notifications')" class="btn-primary btn-sm"
                        :disabled="saving === 'notifications'">
                        {{ saving === 'notifications' ? 'Saving…' : 'Save Notification Settings' }}
                    </button>
                </div>
            </SettingsCard>

           
            <SettingsCard title="Test Email" description="Send a test email to verify SMTP is configured correctly.">
                <div class="flex gap-3">
                    <input v-model="testEmailAddr" type="email" class="form-input" placeholder="your@email.com" />
                    <button @click="sendTestEmail" class="btn-secondary btn-sm whitespace-nowrap"
                        :disabled="testingEmail">
                        {{ testingEmail ? 'Sending…' : 'Send Test Email' }}
                    </button>
                </div>
                <p v-if="testEmailResult"
                    :class="['text-xs mt-2', testEmailResult.success ? 'text-green-600' : 'text-red-600']">
                    {{ testEmailResult.message }}
                </p>
            </SettingsCard>
        </div>

        <!-- ── SECURITY TAB ────────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'security'">
            <SettingsCard title="Security" description="Session, HTTPS, and API rate limiting settings.">
                <SettingsRow label="Session Lifetime (minutes)"
                    description="Web dashboard session timeout. Users are logged out after inactivity.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.session_lifetime_minutes" type="number"
                            class="form-input w-28 text-center" min="15" max="43200" />
                        <span class="text-sm text-gray-400">minutes</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="API Requests / Minute"
                    description="HTTP-level throttle on the external gateway API per token.">
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.api_rate_limit_per_min" type="number"
                            class="form-input w-24 text-center" min="10" max="1000" />
                        <span class="text-sm text-gray-400">req/min</span>
                    </div>
                </SettingsRow>
                <SettingsRow label="Enforce HTTPS" description="Redirect all HTTP traffic to HTTPS in production.">
                    <ToggleSwitch v-model="form.require_https" />
                </SettingsRow>
                <div class="flex justify-end pt-2">
                    <button @click="save('security')" class="btn-primary btn-sm" :disabled="saving === 'security'">
                        {{ saving === 'security' ? 'Saving…' : 'Save Security Settings' }}
                    </button>
                </div>
            </SettingsCard>

            
            <SettingsCard title="Cache Management" description="Flush or rebuild application caches.">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-1">Clear All Caches</h4>
                        <p class="text-xs text-gray-500 mb-3">Flushes config, route, view, and settings caches. Use
                            after changing .env or code.</p>
                        <button @click="clearCache" class="btn-secondary btn-sm w-full justify-center"
                            :disabled="caching">
                            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': caching }" />
                            Clear Caches
                        </button>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-1">Rebuild for Production</h4>
                        <p class="text-xs text-gray-500 mb-3">Re-caches config, routes, and views. Run after deployment
                            for best performance.</p>
                        <button @click="rebuildCache" class="btn-primary btn-sm w-full justify-center"
                            :disabled="caching">
                            <BoltIcon class="w-4 h-4" />
                            Rebuild Caches
                        </button>
                    </div>
                </div>
            </SettingsCard>

            
            <SettingsCard title="System Information" description="Current environment and runtime details.">
                <div v-if="systemHealth" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <div class="bg-gray-50 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-gray-900">{{ systemHealth.queue_depth }}</p>
                        <p class="text-xs text-gray-400">Queue depth</p>
                    </div>
                    <div
                        :class="['rounded-xl p-3 text-center', systemHealth.failed_jobs > 0 ? 'bg-red-50' : 'bg-green-50']">
                        <p
                            :class="['text-lg font-bold', systemHealth.failed_jobs > 0 ? 'text-red-700' : 'text-green-700']">
                            {{ systemHealth.failed_jobs }}</p>
                        <p class="text-xs text-gray-400">Failed jobs</p>
                    </div>
                    <div
                        :class="['rounded-xl p-3 text-center', systemHealth.baileys?.online ? 'bg-green-50' : 'bg-red-50']">
                        <p
                            :class="['text-sm font-bold', systemHealth.baileys?.online ? 'text-green-700' : 'text-red-600']">
                            {{ systemHealth.baileys?.online ? 'Online' : 'Offline' }}
                        </p>
                        <p class="text-xs text-gray-400">Baileys service</p>
                    </div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center">
                        <p class="text-sm font-bold text-blue-700">{{ systemHealth.active_instances }}</p>
                        <p class="text-xs text-gray-400">Active instances</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div v-for="(val, key) in systemInfo" :key="key"
                        class="flex justify-between bg-gray-50 rounded-lg px-3 py-2">
                        <span class="text-gray-400 capitalize">{{ key.replace(/_/g, ' ') }}</span>
                        <span class="font-mono text-gray-700">{{ val }}</span>
                    </div>
                </div>
                <button @click="refreshHealth" class="btn-secondary btn-sm mt-4" :disabled="refreshingHealth">
                    <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': refreshingHealth }" />
                    Refresh Health
                </button>
            </SettingsCard>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import {
    Cog6ToothIcon, AdjustmentsHorizontalIcon, DevicePhoneMobileIcon,
    CreditCardIcon, BellIcon, ShieldCheckIcon,
    ArrowPathIcon, BoltIcon, CheckCircleIcon, XCircleIcon,
} from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

import SettingsCard from '@/Components/Settings/SettingsCard.vue'
import SettingsRow from '@/Components/Settings/SettingsRow.vue'
import ToggleSwitch from '@/Components/Settings/ToggleSwitch.vue'

// ── Page data ─────────────────────────────────────────────────────────────────

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    systemInfo: { type: Object, default: () => ({}) },
})

const activeTab = ref('general')
const saving = ref(null)
const caching = ref(false)
const testingEmail = ref(false)
const refreshingHealth = ref(false)
const testEmailAddr = ref('')
const testEmailResult = ref(null)
const systemHealth = ref(null)

const toast = reactive({ show: false, message: '', type: 'success' })

const tabs = [
    { key: 'general', label: 'General', icon: Cog6ToothIcon },
    { key: 'limits', label: 'Limits', icon: AdjustmentsHorizontalIcon },
    { key: 'instance', label: 'Instances', icon: DevicePhoneMobileIcon },
    { key: 'payment', label: 'Payment', icon: CreditCardIcon },
    { key: 'notifications', label: 'Notifications', icon: BellIcon },
    { key: 'security', label: 'Security', icon: ShieldCheckIcon },
]

const timezones = [
    'Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Asia/Karachi',
    'UTC', 'Europe/London', 'Europe/Paris', 'America/New_York',
    'America/Chicago', 'America/Los_Angeles', 'Australia/Sydney',
]

// ── Build form from props.settings ────────────────────────────────────────────

const form = reactive({})

onMounted(() => {
    // Flatten grouped settings into form
    for (const group of Object.values(props.settings)) {
        for (const [key, setting] of Object.entries(group)) {
            form[key] = setting.value
        }
    }

    // Load system health
    refreshHealth()
})

// ── Save settings by group ────────────────────────────────────────────────────

const groupKeys = {
    general: ['app_name', 'support_email', 'timezone', 'maintenance_mode'],
    limits: ['default_rate_per_minute', 'min_rate_per_minute', 'max_rate_per_minute', 'max_bulk_recipients', 'max_instances_per_user'],
    instance: ['instance_grace_days', 'expiry_warn_days_first', 'expiry_warn_days_second', 'max_reconnect_attempts'],
    payment: ['payment_gateway', 'currency', 'enable_billing'],
    notifications: ['notify_expiry_email', 'notify_credit_low', 'notify_campaign_done', 'admin_alert_email'],
    security: ['session_lifetime_minutes', 'api_rate_limit_per_min', 'require_https'],
}

async function save(group) {
    saving.value = group
    const payload = {}
    for (const key of groupKeys[group] ?? []) {
        if (form[key] !== undefined) payload[key] = form[key]
    }

    try {
        await webHttp.patch('/super/settings', payload)
        showToast('Settings saved successfully.', 'success')
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Failed to save settings.', 'error')
    } finally {
        saving.value = null
    }
}

// ── Cache management ──────────────────────────────────────────────────────────

async function clearCache() {
    caching.value = true
    try {
        await webHttp.post('/super/settings/cache/clear')
        showToast('All caches cleared.', 'success')
    } catch (err) {
        showToast('Failed to clear caches.', 'error')
    } finally {
        caching.value = false
    }
}

async function rebuildCache() {
    caching.value = true
    try {
        await webHttp.post('/super/settings/cache/rebuild')
        showToast('Caches rebuilt for production.', 'success')
    } catch (err) {
        showToast('Failed to rebuild caches.', 'error')
    } finally {
        caching.value = false
    }
}

// ── Test email ────────────────────────────────────────────────────────────────

async function sendTestEmail() {
    if (!testEmailAddr.value) return
    testingEmail.value = true
    testEmailResult.value = null
    try {
        const { data } = await webHttp.post('/super/settings/test-email', { email: testEmailAddr.value })
        testEmailResult.value = { success: true, message: data.message }
    } catch (err) {
        testEmailResult.value = { success: false, message: err.response?.data?.message ?? 'Failed to send.' }
    } finally {
        testingEmail.value = false
    }
}

// ── System health ─────────────────────────────────────────────────────────────

async function refreshHealth() {
    refreshingHealth.value = true
    try {
        const { data } = await webHttp.get('/super/settings/system-health')
        systemHealth.value = data.data
    } finally {
        refreshingHealth.value = false
    }
}

// ── Toast ─────────────────────────────────────────────────────────────────────

function showToast(message, type = 'success') {
    toast.message = message
    toast.type = type
    toast.show = true
    setTimeout(() => { toast.show = false }, 3500)
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}
</style>