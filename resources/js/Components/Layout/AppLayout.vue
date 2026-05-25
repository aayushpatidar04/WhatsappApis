<template>
    <div class="flex h-screen overflow-hidden bg-gray-50">

        <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col w-64 bg-[#0F1B2D] transition-transform duration-300',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
                <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
                        <path
                            d="M12 0C5.373 0 0 5.373 0 12c0 2.126.556 4.121 1.523 5.854L.057 23.882l6.186-1.438A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.894a9.893 9.893 0 01-5.031-1.369l-.361-.214-3.741.981.998-3.648-.235-.374A9.868 9.868 0 012.106 12C2.106 6.58 6.58 2.106 12 2.106c5.419 0 9.894 4.474 9.894 9.894 0 5.419-4.475 9.894-9.894 9.894z" />
                    </svg>
                </div>
                <div>
                    <p class="text-white font-bold text-sm leading-tight">WhatsApp</p>
                    <p class="text-slate-400 text-xs">API Platform</p>
                </div>
            </div>

            <!-- User info -->
            <div class="px-4 py-4 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-sm font-bold">{{ userInitial }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ $page.props.auth.user.name }}</p>
                        <span :class="roleBadgeClass">{{ roleLabel }}</span>
                    </div>
                </div>
                <!-- Credit balance (not for super admin) -->
                <div v-if="!isSuperAdmin"
                    class="mt-3 flex items-center justify-between bg-white/5 rounded-lg px-3 py-2">
                    <span class="text-slate-400 text-xs">Credits</span>
                    <span class="text-green-400 text-sm font-bold">
                        {{ isClientAdmin ? clientCredits : $page.props.auth.user.credit_balance }}
                    </span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1 hide-scrollbar">
                <template v-for="item in navItems" :key="item.name">
                    <!-- Section header -->
                    <p v-if="item.section"
                        class="px-3 pt-3 pb-1 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        {{ item.section }}
                    </p>
                    <!-- Nav link -->
                    <Link v-else :href="item.href" :class="['sidebar-link', isActive(item.href) ? 'active' : '']">
                        <component :is="item.icon" class="w-4 h-4 flex-shrink-0" />
                        <span>{{ item.name }}</span>
                        <span v-if="item.badge"
                            class="ml-auto text-xs bg-blue-500 text-white px-1.5 py-0.5 rounded-full">
                            {{ item.badge }}
                        </span>
                    </Link>
                </template>
            </nav>

            <!-- Footer -->
            <div class="p-4 border-t border-white/10">
                <button @click="logout" class="sidebar-link w-full justify-start">
                    <ArrowRightOnRectangleIcon class="w-4 h-4" />
                    Sign out
                </button>
            </div>
        </aside>

        <!-- Sidebar overlay (mobile) -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- ── Main content ───────────────────────────────────────────────────── -->
        <div class="flex flex-col flex-1 min-w-0 lg:pl-64">

            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex items-center gap-4 bg-white border-b border-gray-200 px-6 h-16">
                <!-- Mobile menu toggle -->
                <button class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-500"
                    @click="sidebarOpen = !sidebarOpen">
                    <Bars3Icon class="w-5 h-5" />
                </button>

                <!-- Breadcrumb -->
                <div class="flex items-center gap-2 text-sm text-gray-500 min-w-0">
                    <span class="font-medium text-gray-900 truncate">{{ title }}</span>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <!-- Notification bell (Phase 4) -->
                    <button class="relative p-2 rounded-lg hover:bg-gray-100 text-gray-500">
                        <BellIcon class="w-5 h-5" />
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>

                    <!-- User avatar -->
                    <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center">
                        <span class="text-white text-xs font-bold">{{ userInitial }}</span>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6 hide-scrollbar">
                <!-- Flash messages -->
                <FlashMessage />
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import {
    HomeIcon, DevicePhoneMobileIcon, PaperAirplaneIcon, InboxIcon,
    MegaphoneIcon, UserGroupIcon, ChartBarIcon, KeyIcon, GlobeAltIcon,
    Cog6ToothIcon, UsersIcon, BuildingOfficeIcon, CreditCardIcon,
    AdjustmentsHorizontalIcon, ShieldCheckIcon, ServerStackIcon,
    DocumentChartBarIcon, BanknotesIcon, BellIcon, Bars3Icon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import FlashMessage from '@/Components/UI/FlashMessage.vue'

const props = defineProps({
    title: { type: String, default: 'Dashboard' },
})

const page = usePage()
const sidebarOpen = ref(false)
const user = computed(() => page.props.auth.user)
const isSuperAdmin = computed(() => user.value.role === 'super_admin')
const isClientAdmin = computed(() => user.value.role === 'client_admin')
const clientCredits = computed(() => page.props.auth.client?.credit_balance ?? 0)
const userInitial = computed(() => user.value.name?.charAt(0).toUpperCase() ?? '?')

const roleLabel = computed(() => ({
    super_admin: 'Super Admin',
    client_admin: 'Master Admin',
    user: 'User',
}[user.value.role] ?? 'User'))

const roleBadgeClass = computed(() => ({
    super_admin: 'text-xs text-purple-400',
    client_admin: 'text-xs text-blue-400',
    user: 'text-xs text-slate-500',
}[user.value.role]))

// ── Navigation items per role ─────────────────────────────────────────────────
const userNav = [
    { section: 'Workspace' },
    { name: 'Dashboard', href: route('user.dashboard'), icon: HomeIcon },
    { name: 'Instances', href: route('user.instances'), icon: DevicePhoneMobileIcon },
    { name: 'Send Message', href: route('user.send'), icon: PaperAirplaneIcon },
    // { name: 'Inbox', href: route('user.inbox'), icon: InboxIcon },
    { section: 'Marketing' },
    { name: 'Campaigns', href: route('user.campaigns'), icon: MegaphoneIcon },
    { name: 'Contacts', href: route('user.contacts'), icon: UserGroupIcon },
    { section: 'Account' },
    { name: 'Reports', href: route('user.reports'), icon: ChartBarIcon },
    { name: 'API Tokens', href: route('user.tokens'), icon: KeyIcon },
    { name: 'Webhooks', href: route('user.webhooks'), icon: GlobeAltIcon },
    { name: 'Settings', href: route('user.settings'), icon: Cog6ToothIcon },
]

const clientNav = [
    { section: 'Dashboard' },
    { name: 'Overview', href: route('client.dashboard'), icon: HomeIcon },
    { name: 'My Instances', href: route('client.instances'), icon: DevicePhoneMobileIcon },
    { section: 'Management' },
    { name: 'Users', href: route('client.users'), icon: UsersIcon },
    { name: 'Credits', href: route('client.credits'), icon: CreditCardIcon },
    { name: 'Billing', href: route('client.billing'), icon: CreditCardIcon },
    { name: 'Rate Limits', href: route('client.rate-limits'), icon: AdjustmentsHorizontalIcon },
    { section: 'Data' },
    { name: 'Reports', href: route('client.reports'), icon: ChartBarIcon },
    { name: 'Templates', href: route('client.templates'), icon: DocumentChartBarIcon },
    { name: 'Settings', href: route('client.settings'), icon: Cog6ToothIcon },
]

const superNav = [
    { section: 'Platform' },
    { name: 'Dashboard', href: route('super.dashboard'), icon: HomeIcon },
    { name: 'Clients', href: route('super.clients'), icon: BuildingOfficeIcon },
    { name: 'Packages', href: route('super.packages'), icon: CreditCardIcon },
    { section: 'Finance' },
    { name: 'Revenue', href: route('super.revenue'), icon: BanknotesIcon },
    { name: 'Credit Ledger', href: route('super.credits.ledger'), icon: DocumentChartBarIcon },
    { section: 'System' },
    { name: 'Monitor', href: route('super.monitor'), icon: ServerStackIcon },
    { name: 'Audit Log', href: route('super.audit'), icon: ShieldCheckIcon },
    { name: 'Settings', href: route('super.settings'), icon: Cog6ToothIcon },
]

const navItems = computed(() => {
    if (isSuperAdmin.value) return superNav
    if (isClientAdmin.value) return clientNav
    return userNav
})

const isActive = (href) => page.url === new URL(href, window.location.origin).pathname

const logout = () => router.post(route('logout'))
</script>