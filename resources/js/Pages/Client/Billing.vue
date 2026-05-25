<template>
  <AppLayout title="Credits & Billing">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Credits & Billing</h1>
        <p class="text-sm text-gray-400 mt-0.5">Purchase instance credits for your account</p>
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

    <!-- Credit rules info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700">
      <strong>How credits work:</strong>
      1 credit = 1 WhatsApp instance active for 1 month.
      Assign credits to instances from the Instances page.
      Credits can be stacked on one instance or spread across multiple.
    </div>

    <!-- Packages -->
    <div class="mb-8">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Choose a Package</h2>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="pkg in packages"
          :key="pkg.id"
          @click="selectedPackage = pkg"
          :class="[
            'card cursor-pointer border-2 transition-all hover:shadow-md',
            selectedPackage?.id === pkg.id
              ? 'border-blue-500 bg-blue-50 shadow-md'
              : 'border-transparent hover:border-blue-200'
          ]"
        >
          <!-- Popular badge -->
          <div v-if="pkg.credits === 5" class="text-center mb-2">
            <span class="text-xs bg-orange-500 text-white font-bold px-2 py-0.5 rounded-full">POPULAR</span>
          </div>

          <div class="text-center py-2">
            <p class="text-3xl font-bold text-gray-900">{{ pkg.credits }}</p>
            <p class="text-xs text-gray-400 mt-0.5">instance credits</p>
          </div>

          <div class="border-t border-gray-100 my-3" />

          <div class="text-center">
            <p class="text-2xl font-bold text-blue-600">
              {{ pkg.currency === 'INR' ? '₹' : '$' }}{{ pkg.price }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ pkg.name }}</p>
          </div>

          <p v-if="pkg.description" class="text-xs text-gray-400 text-center mt-3 leading-relaxed">
            {{ pkg.description }}
          </p>

          <div v-if="selectedPackage?.id === pkg.id" class="mt-3 text-center">
            <CheckCircleIcon class="w-6 h-6 text-blue-600 mx-auto" />
          </div>
        </div>
      </div>
    </div>

    <!-- Checkout -->
    <div v-if="selectedPackage" class="max-w-md">
      <div class="card mb-4">
        <h2 class="card-title mb-4">Order Summary</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">Package</span>
            <span class="font-medium text-gray-900">{{ selectedPackage.name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Credits</span>
            <span class="font-medium text-gray-900">{{ selectedPackage.credits }} instance credits</span>
          </div>
          <div class="flex justify-between border-t border-gray-100 pt-2 mt-2">
            <span class="font-semibold text-gray-700">Total</span>
            <span class="font-bold text-lg text-blue-600">
              {{ selectedPackage.currency === 'INR' ? '₹' : '$' }}{{ selectedPackage.price }}
            </span>
          </div>
        </div>
      </div>

      <!-- Gateway selector -->
      <div class="card mb-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Payment Method</h2>
        <div class="flex gap-3">
          <button
            v-for="gw in availableGateways"
            :key="gw.value"
            @click="selectedGateway = gw.value"
            :class="['flex-1 flex items-center justify-center gap-2 p-3 rounded-xl border-2 text-sm font-medium transition-all',
              selectedGateway === gw.value
                ? 'border-blue-500 bg-blue-50 text-blue-700'
                : 'border-gray-200 text-gray-600 hover:border-gray-300']"
          >
            {{ gw.label }}
          </button>
        </div>
      </div>

      <!-- Success message -->
      <Transition name="fade">
        <div v-if="paymentSuccess" class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4 text-green-700 text-sm">
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
      <button
        class="btn-primary w-full justify-center py-3 text-base"
        @click="startPayment"
        :disabled="processing"
      >
        <span v-if="processing" class="flex items-center gap-2">
          <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          Processing…
        </span>
        <span v-else>
          Pay {{ selectedPackage.currency === 'INR' ? '₹' : '$' }}{{ selectedPackage.price }}
          via {{ selectedGateway === 'razorpay' ? 'Razorpay' : 'Stripe' }}
        </span>
      </button>

      <p class="text-xs text-gray-400 text-center mt-3">
        Payments are processed securely. Credits are added instantly after payment.
      </p>
    </div>

    <!-- Order History -->
    <div class="mt-10">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Order History</h2>

      <div v-if="!orders.length" class="card text-center py-10">
        <p class="text-gray-400 text-sm">No orders yet.</p>
      </div>

      <div v-else class="card overflow-hidden p-0">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Order</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Package</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Credits</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Gateway</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="order in orders" :key="order.id" class="hover:bg-gray-50">
              <td class="py-3 px-4 font-mono text-xs text-gray-600">{{ order.order_number }}</td>
              <td class="py-3 px-4 text-gray-700">{{ order.package?.name }}</td>
              <td class="py-3 px-4 font-semibold text-gray-900">{{ order.credits }}</td>
              <td class="py-3 px-4 text-gray-700">
                {{ order.currency === 'INR' ? '₹' : '$' }}{{ order.amount }}
              </td>
              <td class="py-3 px-4 capitalize text-gray-500 text-xs">{{ order.gateway }}</td>
              <td class="py-3 px-4">
                <span :class="{
                  'badge-active':       order.status === 'paid',
                  'badge-pending':      order.status === 'pending',
                  'badge-expired':      order.status === 'failed',
                  'badge-disconnected': order.status === 'refunded',
                }">{{ order.status }}</span>
              </td>
              <td class="py-3 px-4 text-gray-400 text-xs">{{ fmt(order.paid_at ?? order.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { CreditCardIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const props = defineProps({
  packages:       { type: Array,  default: () => [] },
  orders:         { type: Array,  default: () => [] },
  credit_balance: { type: Number, default: 0 },
  gateway:        { type: String, default: 'razorpay' },
})

const page            = usePage()
const creditBalance   = computed(() => props.credit_balance)
const selectedPackage = ref(null)
const selectedGateway = ref(props.gateway)
const processing      = ref(false)
const paymentSuccess  = ref(null)
const paymentError    = ref(null)

const availableGateways = [
  { value: 'razorpay', label: 'Razorpay' },
  { value: 'stripe',   label: 'Stripe' },
]

const startPayment = async () => {
  if (!selectedPackage.value) return

  processing.value   = true
  paymentError.value = null
  paymentSuccess.value = null

  try {
    const { data } = await webHttp.post('/client/billing/initiate', {
      package_id: selectedPackage.value.id,
      gateway:    selectedGateway.value,
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

// ── Razorpay checkout ─────────────────────────────────────────────────────────
const openRazorpay = (orderData) => new Promise((resolve, reject) => {
  if (!window.Razorpay) {
    const script = document.createElement('script')
    script.src   = 'https://checkout.razorpay.com/v1/checkout.js'
    document.head.appendChild(script)
    script.onload = () => launchRazorpay(orderData, resolve, reject)
  } else {
    launchRazorpay(orderData, resolve, reject)
  }
})

const launchRazorpay = (d, resolve, reject) => {
  const rzp = new window.Razorpay({
    key:         d.key_id,
    amount:      d.amount,
    currency:    d.currency,
    name:        d.name,
    description: d.description,
    order_id:    d.razorpay_order_id,
    prefill:     d.prefill,
    theme:       { color: '#1A56A0' },
    handler: async (response) => {
      processing.value = true
      try {
        const { data } = await webHttp.post('/client/billing/verify/razorpay', {
          order_id:             d.order_id,
          razorpay_payment_id:  response.razorpay_payment_id,
          razorpay_order_id:    response.razorpay_order_id,
          razorpay_signature:   response.razorpay_signature,
        })
        paymentSuccess.value = data
        // Update credit balance in page props
        page.props.auth.client.credit_balance = data.new_balance
        resolve()
      } catch (err) {
        paymentError.value = err.response?.data?.message ?? 'Payment verification failed.'
        reject(err)
      } finally {
        processing.value = false
      }
    },
    modal: { ondismiss: () => { processing.value = false; resolve() } },
  })
  rzp.open()
}

// ── Stripe checkout ───────────────────────────────────────────────────────────
const openStripe = async (intentData) => {
  // Load Stripe.js if not already loaded
  if (!window.Stripe) {
    await new Promise((res) => {
      const s = document.createElement('script')
      s.src   = 'https://js.stripe.com/v3/'
      s.onload = res
      document.head.appendChild(s)
    })
  }

  const stripe  = window.Stripe(intentData.publishable_key)
  const result  = await stripe.confirmCardPayment(intentData.client_secret, {
    payment_method: { card: { /* Stripe Elements would go here */ } },
  })

  if (result.error) {
    paymentError.value = result.error.message
  } else if (result.paymentIntent.status === 'succeeded') {
    paymentSuccess.value = { message: 'Payment confirmed! Credits will be added shortly via webhook.' }
  }
}

const fmt = (iso) => iso ? new Date(iso).toLocaleDateString('en-IN', { day:'numeric', month:'short', year:'numeric' }) : '—'
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.4s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>