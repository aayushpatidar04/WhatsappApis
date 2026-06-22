/**
 * useInstances — composable that manages the instance list reactively.
 *
 * The core idea:
 *   - `instances` is a LOCAL reactive ref (not Inertia props)
 *   - Populated by an API call on mount
 *   - Each instance subscribes to its own Pusher channel
 *   - Pusher events mutate the local ref IN-PLACE → UI updates instantly
 *   - router.reload() is NEVER used for status/phone updates
 *   - Only create/delete trigger a fresh list fetch
 */

import { ref, computed, onUnmounted } from 'vue'
import { instanceApi } from '@/composables/useApi'
import { usePage } from '@inertiajs/vue3'

export function useInstances() {

    const page = usePage()
    const role = page.props.auth?.user?.role

    // Pick correct API prefix based on role
    const prefix = role == 'client_admin' ? '/client' : '/dashboard'
    // ── State ─────────────────────────────────────────────────────────────────
    const instances = ref([])
    const loading = ref(false)
    const error = ref(null)
    const creditBalance = ref(0)

    // Map of instance_token → Pusher channel (for cleanup)
    const channels = new Map()

    // ── Fetch ─────────────────────────────────────────────────────────────────

    async function fetchInstances(params = {}) {
        loading.value = true
        error.value = null
        try {
            const qs = new URLSearchParams(params).toString()
            const url = `${prefix}/instances${qs ? '?' + qs : ''}/api`
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
            const json = await res.json()
            instances.value = json.data?.data ?? json.data ?? []
            // Subscribe all to Pusher
            instances.value.forEach(subscribeInstance)
        } catch (err) {
            error.value = 'Failed to load instances.'
            console.error(err)
        } finally {
            loading.value = false
        }
    }

    // ── Pusher subscription per instance ──────────────────────────────────────

    function subscribeInstance(inst) {
        if (!window.Echo || channels.has(inst.instance_token)) return

        const channel = window.Echo.private(`instance.${inst.instance_token}`)

        channel.listen('InstanceEvent', ({ event, payload }) => {
            updateInstance(inst.instance_token, event, payload)
        })

        channels.set(inst.instance_token, channel)
    }

    function unsubscribeInstance(token) {
        if (window.Echo && channels.has(token)) {
            window.Echo.leave(`instance.${token}`)
            channels.delete(token)
        }
    }

    // ── In-place update — NO router.reload() ─────────────────────────────────

    function updateInstance(token, event, payload) {
        // const idx = instances.value.findIndex(i => i.instance_token == token)
        // if (idx == -1) return
        // const inst = { ...instances.value[idx] }  // shallow copy

        const inst = instances.value.find(i => i.instance_token == token)
        if (!inst) return

        switch (event) {
            case 'qr.updated':
                inst.status = 'qr_pending'
                break

            case 'session.connected':
                inst.status = 'active'
                inst.phone_number = payload?.phone_number ?? inst.phone_number
                // Set activated_at if first connection
                if (!inst.activated_at) {
                    inst.activated_at = new Date().toISOString()
                    // Estimate expiry from credits
                    if (inst.credits_assigned > 0) {
                        const d = new Date()
                        d.setDate(d.getDate() + inst.credits_assigned * 30)
                        inst.expires_at = d.toISOString()
                        inst.days_until_expiry = inst.credits_assigned * 30
                    }
                }
                inst.last_connected_at = new Date().toISOString()
                break

            case 'session.disconnected':
                inst.status = 'disconnected'
                break

            case 'session.logged_out':
                inst.status = 'pending'
                inst.phone_number = null
                break

            case 'session.max_reconnects_reached':
                inst.status = 'disconnected'
                break

            case 'instance.expiring':
                // No status change — notification only
                break

            case 'instance.expired':
                inst.status = 'suspended'
                break

            case 'credits.updated':
                // Fired by InstanceService after top-up
                inst.credits_assigned = payload?.credits_assigned ?? inst.credits_assigned
                inst.credits_remaining = payload?.credits_remaining ?? inst.credits_remaining
                inst.credits_consumed = payload?.credits_consumed ?? inst.credits_consumed
                inst.expires_at = payload?.expires_at ?? inst.expires_at
                inst.days_until_expiry = payload?.days_until_expiry ?? inst.days_until_expiry
                break
        }

        // Replace the instance in the array reactively
        // instances.value[idx] = inst
    }

    // ── Add / Remove instance locally (after create/delete) ──────────────────

    function addInstance(inst) {
        instances.value.unshift(inst)
        subscribeInstance(inst)
    }

    function removeInstance(id) {
        const idx = instances.value.findIndex(i => i.id == id)
        if (idx == -1) return
        const token = instances.value[idx].instance_token
        unsubscribeInstance(token)
        instances.value.splice(idx, 1)
    }

    // Update a single instance after a PATCH (e.g. top-up, rename)
    function refreshInstance(updated) {
        const idx = instances.value.findIndex(i => i.id == updated.id)
        if (idx == -1) return
        instances.value[idx] = { ...instances.value[idx], ...updated }
    }

    // ── Cleanup ───────────────────────────────────────────────────────────────

    function cleanup() {
        for (const token of channels.keys()) {
            unsubscribeInstance(token)
        }
    }

    onUnmounted(cleanup)

    // ── Computed ──────────────────────────────────────────────────────────────

    const activeCount = computed(() => instances.value.filter(i => i.status == 'active').length)
    const pendingCount = computed(() => instances.value.filter(i => ['pending', 'disconnected'].includes(i.status)).length)
    const suspendedCount = computed(() => instances.value.filter(i => ['suspended', 'expired'].includes(i.status)).length)

    return {
        instances,
        loading,
        error,
        creditBalance,
        activeCount,
        pendingCount,
        suspendedCount,
        fetchInstances,
        addInstance,
        removeInstance,
        refreshInstance,
        updateInstance,
    }
}