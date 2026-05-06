<template>
    <AppLayout :title="title">
        <div class="card text-center py-20">
            <component :is="icon" class="w-16 h-16 text-gray-200 mx-auto mb-4" />
            <h2 class="text-lg font-semibold text-gray-700">{{ title }}</h2>
            <p class="text-gray-400 text-sm mt-2 max-w-sm mx-auto">{{ description }}</p>
            <div
                class="mt-6 inline-flex items-center gap-2 bg-blue-50 text-blue-700 text-sm px-4 py-2 rounded-full font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Coming in {{ phase }}
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { computed } from 'vue'
import {
    PaperAirplaneIcon, InboxIcon, MegaphoneIcon, UserGroupIcon,
    ChartBarIcon, GlobeAltIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    page: { type: String, required: true },
})

const pageConfig = {
    send: { title: 'Send Message', icon: PaperAirplaneIcon, description: 'Send text, media, documents and interactive messages via your connected WhatsApp instances.', phase: 'Phase 3' },
    inbox: { title: 'Inbox', icon: InboxIcon, description: 'View all inbound messages across all your WhatsApp instances in one unified inbox.', phase: 'Phase 3' },
    campaigns: { title: 'Campaigns', icon: MegaphoneIcon, description: 'Create, schedule and monitor bulk WhatsApp campaigns with delivery tracking.', phase: 'Phase 4' },
    contacts: { title: 'Contacts', icon: UserGroupIcon, description: 'Import contacts from CSV/Excel, tag and segment them for targeted campaigns.', phase: 'Phase 4' },
    reports: { title: 'Reports', icon: ChartBarIcon, description: 'View delivery success rates, message volume charts and campaign analytics.', phase: 'Phase 4' },
    webhooks: { title: 'Webhooks', icon: GlobeAltIcon, description: 'Register webhook URLs to receive inbound message events and delivery status updates.', phase: 'Phase 3' },
    settings: { title: 'Settings', icon: Cog6ToothIcon, description: 'Manage your account preferences, timezone, and notification settings.', phase: 'Phase 5' },
}

const config = computed(() => pageConfig[props.page] ?? { title: props.page, icon: Cog6ToothIcon, description: '', phase: 'a future phase' })
const title = computed(() => config.value.title)
const icon = computed(() => config.value.icon)
const description = computed(() => config.value.description)
const phase = computed(() => config.value.phase)
</script>