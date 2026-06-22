<template>
  <AppLayout title="Credit Packages">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Credit Packages</h1>
        <p class="text-sm text-gray-400 mt-0.5">Manage packages available for clients to purchase</p>
      </div>
      <button class="btn-primary" @click="showCreate = true">
        <PlusIcon class="w-4 h-4" />
        New Package
      </button>
    </div>

    <!-- Packages grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <div v-for="pkg in packages" :key="pkg.id"
        class="card hover:shadow-md transition-shadow">

        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="font-semibold text-gray-900">{{ pkg.name }}</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ pkg.description }}</p>
          </div>
          <span :class="pkg.is_active ? 'badge-active' : 'badge-disconnected'">
            {{ pkg.is_active ? 'Active' : 'Hidden' }}
          </span>
        </div>

        <div class="flex items-end gap-4 mb-4">
          <div>
            <p class="text-3xl font-bold text-gray-900">{{ pkg.credits }}</p>
            <p class="text-xs text-gray-400">credits</p>
          </div>
          <div class="ml-auto text-right">
            <p class="text-2xl font-bold text-blue-600">
              {{ pkg.currency == 'INR' ? '₹' : '$' }}{{ pkg.price }}
            </p>
            <p class="text-xs text-gray-400">{{ pkg.currency }}</p>
          </div>
        </div>

        <div class="flex gap-2 pt-3 border-t border-gray-100">
          <button @click="editPackage(pkg)" class="btn-secondary btn-sm flex-1">
            <PencilIcon class="w-3.5 h-3.5" />
            Edit
          </button>
          <button @click="toggleActive(pkg)"
            :class="['btn-sm flex-1', pkg.is_active ? 'bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100 rounded-lg text-sm font-medium' : 'btn-primary']">
            {{ pkg.is_active ? 'Hide' : 'Show' }}
          </button>
          <button @click="deletePackage(pkg)"
            class="btn-sm p-2 text-red-400 hover:bg-red-50 rounded-lg border border-red-100">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!packages.length" class="col-span-3 card text-center py-14">
        <CreditCardIcon class="w-12 h-12 text-gray-200 mx-auto mb-3" />
        <p class="text-gray-400 text-sm">No packages yet. Create the first one.</p>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showCreate || editingPkg" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/50" @click="closeForm" />
          <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-5 border-b">
              <h2 class="font-bold text-gray-900">{{ editingPkg ? 'Edit Package' : 'New Package' }}</h2>
              <button @click="closeForm" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <form @submit.prevent="savePackage" class="px-6 py-5 space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="form-label">Name <span class="text-red-500">*</span></label>
                  <input v-model="form.name" type="text" class="form-input" required maxlength="100"
                         placeholder="Growth" />
                  <p v-if="errors.name" class="form-error">{{ errors.name[0] }}</p>
                </div>
                <div>
                  <label class="form-label">Credits <span class="text-red-500">*</span></label>
                  <input v-model.number="form.credits" type="number" class="form-input" required min="1"
                         placeholder="5" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="form-label">Price <span class="text-red-500">*</span></label>
                  <input v-model.number="form.price" type="number" step="0.01" class="form-input" required min="0"
                         placeholder="1299.00" />
                </div>
                <div>
                  <label class="form-label">Currency</label>
                  <select v-model="form.currency" class="form-input">
                    <option value="INR">INR (₹)</option>
                    <option value="USD">USD ($)</option>
                    <option value="EUR">EUR (€)</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="form-label">Description</label>
                <textarea v-model="form.description" class="form-input resize-none" rows="2"
                          placeholder="Brief description shown to clients…" maxlength="500" />
              </div>
              <div class="flex items-center gap-2">
                <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded text-blue-600" />
                <label for="is_active" class="text-sm text-gray-700 select-none">Active (visible to clients)</label>
              </div>
              <p v-if="serverError" class="text-sm text-red-600">{{ serverError }}</p>
              <div class="flex gap-3 pt-2">
                <button type="button" class="btn-secondary flex-1" @click="closeForm">Cancel</button>
                <button type="submit" class="btn-primary flex-1" :disabled="saving">
                  {{ saving ? 'Saving…' : (editingPkg ? 'Save Changes' : 'Create Package') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { PlusIcon, PencilIcon, TrashIcon, CreditCardIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const props = defineProps({
  packages: { type: Array, default: () => [] },
})

const packages    = ref([...props.packages])
const showCreate  = ref(false)
const editingPkg  = ref(null)
const saving      = ref(false)
const serverError = ref(null)
const errors      = reactive({})

const form = reactive({
  name: '', credits: null, price: null, currency: 'INR',
  description: '', is_active: true,
})

const editPackage = (pkg) => {
  editingPkg.value = pkg
  Object.assign(form, { name: pkg.name, credits: pkg.credits, price: pkg.price,
    currency: pkg.currency, description: pkg.description ?? '', is_active: pkg.is_active })
}

const closeForm = () => {
  showCreate.value = false; editingPkg.value = null; serverError.value = null
  Object.keys(errors).forEach(k => delete errors[k])
  Object.assign(form, { name:'', credits:null, price:null, currency:'INR', description:'', is_active:true })
}

const savePackage = async () => {
  saving.value = true; serverError.value = null
  try {
    if (editingPkg.value) {
      const { data } = await webHttp.patch(`/super/packages/${editingPkg.value.id}`, form)
      const idx = packages.value.findIndex(p => p.id == editingPkg.value.id)
      if (idx !== -1) packages.value[idx] = data.data
    } else {
      const { data } = await webHttp.post('/super/packages', form)
      packages.value.push(data.data)
    }
    closeForm()
  } catch (err) {
    if (err.response?.status == 422) Object.assign(errors, err.response.data.errors ?? {})
    else serverError.value = err.response?.data?.message ?? 'Failed to save.'
  } finally {
    saving.value = false
  }
}

const toggleActive = async (pkg) => {
  const { data } = await webHttp.patch(`/super/packages/${pkg.id}`, { is_active: !pkg.is_active })
  const idx = packages.value.findIndex(p => p.id == pkg.id)
  if (idx !== -1) packages.value[idx] = data.data
}

const deletePackage = async (pkg) => {
  if (!confirm(`Delete "${pkg.name}"? Existing orders are not affected.`)) return
  await webHttp.delete(`/super/packages/${pkg.id}`)
  packages.value = packages.value.filter(p => p.id !== pkg.id)
}
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to       { opacity: 0; }
</style>