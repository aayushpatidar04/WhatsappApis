<template>
    <AppLayout title="Revenue">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Revenue Dashboard</h1>
                <p class="text-sm text-gray-400 mt-0.5">Credit sales and payment analytics</p>
            </div>
            <select v-model="days" @change="loadAll" class="form-input text-sm w-36">
                <option :value="7">Last 7 days</option>
                <option :value="30">Last 30 days</option>
                <option :value="90">Last 90 days</option>
            </select>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" v-if="overview">
            <StatCard :icon="BanknotesIcon" label="Total Revenue" :value="formatCurrency(overview.total_revenue)"
                color="green" />
            <StatCard :icon="ShoppingCartIcon" label="Total Orders" :value="overview.total_orders" color="blue" />
            <StatCard :icon="CreditCardIcon" label="Credits Sold" :value="overview.total_credits" color="purple" />
            <StatCard :icon="ChartBarIcon" label="Avg Order" :value="formatCurrency(overview.avg_order)"
                color="orange" />
        </div>

        <div class="grid lg:grid-cols-3 gap-6 mb-6">

            <!-- Daily Revenue Bar Chart -->
            <div class="lg:col-span-2 card">
                <h2 class="card-title mb-4">Daily Revenue</h2>
                <div v-if="!daily.length || maxRevenue == 0"
                    class="h-48 flex items-center justify-center text-gray-300 text-sm">
                    No revenue data for this period.
                </div>
                <div v-else class="h-48 flex items-end gap-1 overflow-hidden">
                    <div v-for="d in daily" :key="d.date" class="flex-1 flex flex-col justify-end group cursor-default"
                        :title="`${fmtDate(d.date)}: ${formatCurrency(d.revenue)} (${d.orders} orders)`">
                        <div class="bg-blue-500 hover:bg-blue-600 rounded-t-sm transition-all duration-300 min-h-px"
                            :style="{ height: barH(d.revenue) + '%' }" />
                    </div>
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-400" v-if="daily.length">
                    <span>{{ fmtDate(daily[0]?.date) }}</span>
                    <span>{{ fmtDate(daily[daily.length - 1]?.date) }}</span>
                </div>
            </div>

            <!-- By Gateway -->
            <div class="card">
                <h2 class="card-title mb-4">By Gateway</h2>
                <div v-if="!overview?.by_gateway?.length" class="text-center py-8 text-gray-300 text-sm">No data</div>
                <div v-else class="space-y-4">
                    <div v-for="gw in overview.by_gateway" :key="gw.gateway">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700 capitalize">{{ gw.gateway }}</span>
                            <span class="text-gray-900 font-semibold">{{ formatCurrency(gw.revenue) }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mb-1.5">
                            <span>{{ gw.orders }} orders</span>
                            <span>{{ gwPct(gw.revenue) }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full bg-blue-500 transition-all duration-500"
                                :style="{ width: gwPct(gw.revenue) + '%' }" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue by client -->
        <div class="card mb-6">
            <h2 class="card-title mb-4">Revenue by Client</h2>
            <div v-if="!byClient.length" class="text-center py-8 text-gray-300 text-sm">No data</div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Client</th>
                            <th
                                class="text-right py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Revenue</th>
                            <th
                                class="text-right py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Orders</th>
                            <th
                                class="text-right py-3 px-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Credits</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="c in byClient" :key="c.client_id" class="hover:bg-gray-50">
                            <td class="py-2.5 px-2 font-medium text-gray-900">{{ c.client_name }}</td>
                            <td class="py-2.5 px-2 text-right text-green-700 font-semibold">{{ formatCurrency(c.revenue)
                                }}</td>
                            <td class="py-2.5 px-2 text-right text-gray-500">{{ c.orders }}</td>
                            <td class="py-2.5 px-2 text-right text-gray-500">{{ c.credits }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent orders -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Orders</h2>
                <div class="flex gap-2">
                    <select v-model="orderFilter.status" @change="loadOrders" class="form-input text-xs w-36">
                        <option value="">All statuses</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                    <select v-model="orderFilter.gateway" @change="loadOrders" class="form-input text-xs w-36">
                        <option value="">All gateways</option>
                        <option value="razorpay">Razorpay</option>
                        <option value="stripe">Stripe</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Order</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Client</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Package</th>
                            <th
                                class="text-right py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Amount</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Gateway</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Status</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="o in orders" :key="o.id" class="hover:bg-gray-50">
                            <td class="py-2.5 px-3 font-mono text-xs text-gray-500">{{ o.order_number }}</td>
                            <td class="py-2.5 px-3 text-gray-800">{{ o.client?.name }}</td>
                            <td class="py-2.5 px-3 text-gray-600">{{ o.package?.name }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                                {{ o.currency == 'INR' ? '₹' : '$' }}{{ o.amount }}
                            </td>
                            <td class="py-2.5 px-3 capitalize text-gray-500 text-xs">{{ o.gateway }}</td>
                            <td class="py-2.5 px-3">
                                <span :class="{
                                    'badge-active': o.status == 'paid',
                                    'badge-pending': o.status == 'pending',
                                    'badge-expired': o.status == 'failed',
                                    'badge-disconnected': o.status == 'refunded',
                                }">{{ o.status }}</span>
                            </td>
                            <td class="py-2.5 px-3 text-gray-400 text-xs">{{ fmtDate(o.paid_at ?? o.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!orders.length" class="text-center py-10 text-gray-300 text-sm">No orders found.</div>
            </div>
        </div>

    </AppLayout>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { BanknotesIcon, ShoppingCartIcon, CreditCardIcon, ChartBarIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const days = ref(30)
const overview = ref(null)
const daily = ref([])
const byClient = ref([])
const orders = ref([])
const orderFilter = reactive({ status: '', gateway: '' })

const maxRevenue = computed(() => Math.max(...daily.value.map(d => d.revenue), 1))
const totalRev = computed(() => overview.value?.total_revenue ?? 1)

onMounted(loadAll)

async function loadAll() {
    const p = { days: days.value }
    const [ov, bc] = await Promise.all([
        webHttp.get('/super/revenue/overview', { params: p }),
        webHttp.get('/super/revenue/by-client', { params: p }),
    ])
    overview.value = ov.data.data
    daily.value = ov.data.data.daily
    byClient.value = bc.data.data
    await loadOrders()
}

async function loadOrders() {
    const { data } = await webHttp.get('/super/revenue/orders', { params: { ...orderFilter, per_page: 20 } })
    orders.value = data.data.data
}

const barH = (rev) => maxRevenue.value > 0 ? Math.max(2, (rev / maxRevenue.value) * 100) : 0
const gwPct = (rev) => totalRev.value > 0 ? Math.round((rev / totalRev.value) * 100) : 0
const formatCurrency = (v) => v != null ? `₹${Number(v).toLocaleString('en-IN')}` : '₹0'
const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
</script>