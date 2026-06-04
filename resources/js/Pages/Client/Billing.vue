<template>
  <AppLayout title="Credits & Billing">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Credits & Billing</h1>
        <p class="text-sm text-gray-400 mt-0.5">Manage your account credits and billing</p>
      </div>
      <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-2">
        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
          <CreditCardIcon class="w-5 h-5 text-green-600" />
        </div>
        <div>
          <p class="text-xs text-gray-400">Available Credits</p>
          <p class="text-xl font-bold text-green-700">{{ creditBalance }}</p>
        </div>
      </div>
    </div>

    <!-- Info banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700">
      <strong>How credits work:</strong>
      1 credit = 1 WhatsApp instance active for 1 calendar month.
      Assign credits when creating an instance.
      Once activated, the instance expires after the month regardless of usage.
    </div>

    <!-- Credit packages -->
    <div class="mb-8">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Purchase Credits</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="pkg in packages" :key="pkg.id" @click="selectedPackage = pkg" :class="[
          'card cursor-pointer border-2 transition-all hover:shadow-md',
          selectedPackage?.id === pkg.id ? 'border-blue-500 bg-blue-50' : 'border-transparent hover:border-blue-200'
        ]">
          <div class="text-center py-3">
            <p class="text-3xl font-bold text-gray-900">{{ pkg.credits }}</p>
            <p class="text-xs text-gray-400 mt-0.5">instance credits</p>
          </div>
          <div class="border-t border-gray-100 my-3" />
          <div class="text-center">
            <p class="text-2xl font-bold text-blue-600">{{ pkg.currency === 'INR' ? '₹' : '$' }}{{ pkg.price }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ pkg.name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Checkout -->
    <div v-if="selectedPackage" class="max-w-md mb-8">
      <div class="card mb-4">
        <h2 class="card-title mb-4">Order Summary</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">Package</span>
            <span class="font-medium">{{ selectedPackage.name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Credits</span>
            <span class="font-medium">{{ selectedPackage.credits }}</span>
          </div>
          <div class="flex justify-between border-t border-gray-100 pt-2 mt-2">
            <span class="font-bold">Total</span>
            <span class="text-lg font-bold text-blue-600">
              {{ selectedPackage.currency === 'INR' ? '₹' : '$' }}{{ selectedPackage.price }}
            </span>
          </div>
        </div>
      </div>

      <!-- Gateway selector -->
      <div class="card mb-4">
        <label class="text-sm font-semibold text-gray-700 mb-3 block">Payment Method</label>
        <div class="flex gap-3">
          <button v-for="gw in ['razorpay', 'stripe']" :key="gw" @click="selectedGateway = gw" :class="['flex-1 py-2 px-3 rounded-lg border-2 text-sm font-medium transition-all',
            selectedGateway === gw
              ? 'border-blue-500 bg-blue-50 text-blue-700'
              : 'border-gray-200 text-gray-600 hover:border-gray-300']">
            {{ gw === 'razorpay' ? 'Razorpay' : 'Stripe' }}
          </button>
        </div>
      </div>

      <!-- Success message -->
      <Transition name="fade">
        <div v-if="paymentSuccess"
          class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 text-green-700 text-sm">
          <p class="font-bold mb-1">✓ Payment Successful!</p>
          <p>{{ paymentSuccess.message }}</p>
          <p class="mt-1">New balance: <strong>{{ paymentSuccess.new_balance }} credits</strong></p>
        </div>
      </Transition>

      <!-- Error -->
      <div v-if="paymentError" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 text-red-700 text-sm">
        {{ paymentError }}
      </div>

      <!-- Pay button -->
      <button class="btn-primary w-full justify-center py-3" @click="startPayment" :disabled="processing">
        <span v-if="processing">Processing…</span>
        <span v-else>
          Pay {{ selectedPackage.currency === 'INR' ? '₹' : '$' }}{{ selectedPackage.price }}
        </span>
      </button>
    </div>

    <!-- Recent orders -->
    <div class="mb-8">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Orders</h2>
      <div class="card overflow-hidden p-0">
        <div v-if="!orders.length" class="p-8 text-center text-gray-400 text-sm">
          No orders yet.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Order #</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Package</th>
              <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Amount</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="o in orders" :key="o.id" class="hover:bg-gray-50">
              <td class="py-3 px-4 font-mono text-xs text-gray-500">{{ o.order_number }}</td>
              <td class="py-3 px-4">{{ o.package?.name }}</td>
              <td class="py-3 px-4 text-right font-semibold">{{ o.currency === 'INR' ? '₹' : '$' }}{{ o.amount }}</td>
              <td class="py-3 px-4">
                <span
                  :class="o.status === 'paid' ? 'badge-active' : (o.status === 'pending' ? 'badge-pending' : 'badge-expired')">
                  {{ o.status }}
                </span>
              </td>
              <td class="py-3 px-4 text-gray-400 text-xs">{{ fmtDate(o.paid_at ?? o.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Transaction ledger -->
    <div>
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Transaction Ledger</h2>
      <div class="card overflow-hidden p-0">
        <div v-if="!transactions.length" class="p-8 text-center text-gray-400 text-sm">
          No transactions yet.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Type</th>
              <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Credits</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Reference</th>
              <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Balance After</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-gray-50">
              <td class="py-3 px-4">
                <span
                  :class="['badge text-xs', tx.type === 'allocation' ? 'bg-red-100 text-red-700' : (tx.type === 'purchase' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600')]">
                  {{ tx.type }}
                </span>
              </td>
              <td class="py-3 px-4 text-right font-semibold"
                :class="tx.credits > 0 ? 'text-green-600' : 'text-red-600'">
                {{ tx.credits > 0 ? '+' : '' }}{{ tx.credits }}
              </td>
              <td class="py-3 px-4 text-gray-600 text-xs">{{ tx.reference }}</td>
              <td class="py-3 px-4 text-right text-gray-700 font-mono">{{ tx.balance_after }}</td>
              <td class="py-3 px-4 text-gray-400 text-xs">{{ fmtDate(tx.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const props = defineProps({
  packages: { type: Array, default: () => [] },
  orders: { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
  credit_balance: { type: Number, default: 0 },
  gateway: { type: String, default: 'razorpay' },
})

const page = usePage()
const creditBalance = ref(props.credit_balance)
const selectedPackage = ref(null)
const selectedGateway = ref(props.gateway)
const processing = ref(false)
const paymentSuccess = ref(null)
const paymentError = ref(null)

const startPayment = async () => {
  if (!selectedPackage.value) return
  processing.value = true
  paymentError.value = null
  paymentSuccess.value = null

  try {
    const { data } = await webHttp.post('/client/billing/initiate', {
      package_id: selectedPackage.value.id,
      gateway: selectedGateway.value,
    })

    if (selectedGateway.value === 'razorpay') {
      await openRazorpay(data.data)
    } else {
      await openStripe(data.data)
    }
  } catch (err) {
    paymentError.value = err.response?.data?.message ?? 'Failed to initiate payment.'
  } finally {
    processing.value = false
  }
}

const openRazorpay = (orderData) => new Promise((resolve) => {
  if (!window.Razorpay) {
    const script = document.createElement('script')
    script.src = 'https://checkout.razorpay.com/v1/checkout.js'
    document.head.appendChild(script)
    script.onload = () => launchRazorpay(orderData, resolve)
  } else {
    launchRazorpay(orderData, resolve)
  }
})

const launchRazorpay = (d, resolve) => {
  const rzp = new window.Razorpay({
    key: d.key_id,
    amount: d.amount,
    currency: d.currency,
    order_id: d.razorpay_order_id,
    handler: async (response) => {
      processing.value = true
      try {
        const { data } = await webHttp.post('/client/billing/verify/razorpay', {
          order_id: d.order_id,
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_order_id: response.razorpay_order_id,
          razorpay_signature: response.razorpay_signature,
        })
        paymentSuccess.value = data
        creditBalance.value = data.new_balance
        resolve()
      } catch (err) {
        paymentError.value = err.response?.data?.message ?? 'Payment verification failed.'
        resolve()
      } finally {
        processing.value = false
      }
    },
    modal: { ondismiss: () => { processing.value = false; resolve() } },
  })
  rzp.open()
}

const openStripe = async () => {
  alert('Stripe integration coming soon.')
}

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>