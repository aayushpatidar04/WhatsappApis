<template>
  <AppLayout title="Audit Log">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Audit Log</h1>
        <p class="text-sm text-gray-400 mt-0.5">All admin actions across the platform</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input v-model="filter.event" type="search" class="form-input text-sm w-48"
             placeholder="Filter by event…" @input="debouncedFetch" />
      <button @click="fetch" class="btn-secondary btn-sm">
        <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
        Refresh
      </button>
    </div>

    <!-- Log table -->
    <div class="card overflow-hidden p-0">
      <div v-if="loading" class="p-8 text-center">
        <div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto" />
      </div>

      <div v-else-if="!logs.length" class="text-center py-14 text-gray-300 text-sm">
        No audit events found.
      </div>

      <div v-else>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Event</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Target</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">IP</th>
              <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">Time</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
              <td class="py-3 px-4">
                <span :class="['badge text-xs font-mono', eventBadge(log.event)]">{{ log.event }}</span>
              </td>
              <td class="py-3 px-4">
                <p class="text-gray-900 text-sm">{{ log.user?.name ?? 'System' }}</p>
                <p class="text-xs text-gray-400">{{ log.user?.email }}</p>
              </td>
              <td class="py-3 px-4 text-gray-500 text-xs">
                <span v-if="log.auditable_type">{{ log.auditable_type }} #{{ log.auditable_id }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="py-3 px-4 font-mono text-gray-400 text-xs">{{ log.ip_address }}</td>
              <td class="py-3 px-4 text-gray-400 text-xs">{{ timeAgo(log.created_at) }}</td>
              <td class="py-3 px-4">
                <button
                  v-if="log.new_values || log.old_values"
                  @click="expanded = expanded === log.id ? null : log.id"
                  class="text-blue-500 text-xs hover:underline"
                >
                  {{ expanded === log.id ? 'Hide' : 'Details' }}
                </button>
              </td>
            </tr>

            <!-- Expanded detail row -->
            <template v-for="log in logs" :key="`detail-${log.id}`">
              <tr v-if="expanded === log.id" class="bg-blue-50">
                <td colspan="6" class="px-4 py-3">
                  <div class="grid grid-cols-2 gap-4 text-xs font-mono">
                    <div v-if="log.old_values">
                      <p class="font-semibold text-gray-500 mb-1">Before</p>
                      <pre class="bg-white rounded p-2 overflow-auto max-h-32 text-gray-700">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                    </div>
                    <div v-if="log.new_values">
                      <p class="font-semibold text-gray-500 mb-1">After</p>
                      <pre class="bg-white rounded p-2 overflow-auto max-h-32 text-green-700">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
          <p class="text-xs text-gray-400">{{ pagination.total ?? 0 }} events</p>
          <div class="flex gap-2">
            <button :disabled="page === 1" @click="goTo(page-1)" class="btn-secondary btn-sm px-3">‹</button>
            <span class="text-xs text-gray-500 px-2 py-1.5">{{ page }} / {{ pagination.last_page ?? 1 }}</span>
            <button :disabled="page >= (pagination.last_page ?? 1)" @click="goTo(page+1)" class="btn-secondary btn-sm px-3">›</button>
          </div>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import webHttp from '@/composables/useApi'

const logs       = ref([])
const loading    = ref(true)
const expanded   = ref(null)
const page       = ref(1)
const pagination = ref({})
const filter     = reactive({ event: '' })

let debounceTimer = null
const debouncedFetch = () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fetch, 400) }

onMounted(fetch)

async function fetch() {
  loading.value = true
  try {
    const { data } = await webHttp.get('/super/audit/logs', { params: { ...filter, page: page.value, per_page: 25 } })
    logs.value       = data.data.data
    pagination.value = { total: data.data.total, last_page: data.data.last_page }
  } finally {
    loading.value = false
  }
}

const goTo = (p) => { page.value = p; fetch() }

const eventBadge = (event) => {
  if (event.includes('credit'))  return 'bg-green-100 text-green-700'
  if (event.includes('delete'))  return 'bg-red-100 text-red-700'
  if (event.includes('created')) return 'bg-blue-100 text-blue-700'
  if (event.includes('updated')) return 'bg-yellow-100 text-yellow-700'
  return 'bg-gray-100 text-gray-600'
}

const timeAgo = (iso) => {
  const m = Math.floor((Date.now() - new Date(iso)) / 60000)
  if (m < 1)    return 'just now'
  if (m < 60)   return `${m}m ago`
  if (m < 1440) return `${Math.floor(m/60)}h ago`
  return new Date(iso).toLocaleString('en-IN', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' })
}
</script>