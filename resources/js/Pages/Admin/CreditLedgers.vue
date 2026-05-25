<template>
    <AppLayout title="Credit Ledger">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Credit Ledger</h1>
                <p class="text-sm text-gray-400 mt-0.5">All credit transactions across the platform</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-5">
            <input v-model="localFilters.client_name" type="search" class="form-input text-sm w-48"
                placeholder="Filter by client name…" @input="applyFilters" />
            <select v-model="localFilters.type" class="form-input text-sm w-48" @change="applyFilters">
                <option value="">All Types</option>
                <option value="purchase">Purchase</option>
                <option value="allocation">Allocation</option>
                <option value="deallocation">Deallocation</option>
                <option value="consumption">Consumption</option>
                <option value="refund">Refund</option>
                <option value="manual_adjustment">Manual Adjustment</option>
            </select>
        </div>

        <!-- Ledger table -->
        <div class="card overflow-hidden p-0">
            <div v-if="!transactions.data.length" class="text-center py-14 text-gray-300 text-sm">
                No credit transactions found.
            </div>

            <div v-else>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Client</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Type</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Credits</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Balance After</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Reference</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Created By</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="tx in transactions.data" :key="tx.id" class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <p class="text-gray-900 text-sm">{{ tx.client?.name ?? '—' }}</p>
                            </td>
                            <td class="py-3 px-4">
                                <span :class="['badge text-xs font-mono', typeBadge(tx.type)]">{{ tx.type }}</span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-green-700">{{ tx.credits }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ tx.balance_after }}</td>
                            <td class="py-3 px-4 text-gray-500 text-xs">{{ tx.reference || '—' }}</td>
                            <td class="py-3 px-4">
                                <p class="text-gray-900 text-sm">{{ tx.created_by?.name ?? 'System' }}</p>
                                <p class="text-xs text-gray-400">{{ tx.created_by?.email }}</p>
                            </td>
                            <td class="py-3 px-4 text-gray-400 text-xs">{{ timeAgo(tx.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400">{{ transactions.total ?? 0 }} transactions</p>
                    <div class="flex gap-2">
                        <button :disabled="transactions.current_page === 1" @click="goTo(transactions.current_page - 1)"
                            class="btn-secondary btn-sm px-3">‹</button>
                        <span class="text-xs text-gray-500 px-2 py-1.5">
                            {{ transactions.current_page }} / {{ transactions.last_page ?? 1 }}
                        </span>
                        <button :disabled="transactions.current_page >= (transactions.last_page ?? 1)"
                            @click="goTo(transactions.current_page + 1)" class="btn-secondary btn-sm px-3">›</button>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
    transactions: Object,
    filters: Object,
})

const localFilters = reactive({
    client_name: props.filters.client_name || '',
    type: props.filters.type || '',
})

const applyFilters = () => {
    router.get(route('super.credits.ledger'), { ...localFilters }, { preserveState: true })
}

const goTo = (page) => {
    router.get(route('super.credits.ledger'), { ...localFilters, page }, { preserveState: true })
}

const typeBadge = (type) => {
    switch (type) {
        case 'purchase': return 'bg-blue-100 text-blue-700'
        case 'allocation': return 'bg-green-100 text-green-700'
        case 'deallocation': return 'bg-red-100 text-red-700'
        case 'consumption': return 'bg-yellow-100 text-yellow-700'
        case 'refund': return 'bg-purple-100 text-purple-700'
        case 'manual_adjustment': return 'bg-gray-100 text-gray-600'
        default: return 'bg-gray-100 text-gray-600'
    }
}

const timeAgo = (iso) => {
    const m = Math.floor((Date.now() - new Date(iso)) / 60000)
    if (m < 1) return 'just now'
    if (m < 60) return `${m}m ago`
    if (m < 1440) return `${Math.floor(m / 60)}h ago`
    return new Date(iso).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>
