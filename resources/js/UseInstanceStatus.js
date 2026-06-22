/**
 * useInstanceStatus
 *
 * Vue composable that subscribes to a Pusher private channel for a given
 * WhatsApp instance and provides reactive state updates.
 *
 * Usage:
 *   const { status, phoneNumber, qrCode, lastEvent } = useInstanceStatus(instanceToken)
 */

import { ref, onUnmounted, watch } from 'vue'

export function useInstanceStatus(instanceToken) {
  const status      = ref('unknown')
  const phoneNumber = ref(null)
  const qrCode      = ref(null)
  const lastEvent   = ref(null)
  const error       = ref(null)

  let channel = null

  function subscribe(token) {
    if (!token || !window.Echo) return

    unsubscribe()

    channel = window.Echo.private(`instance.${token}`)

    channel.listen('InstanceEvent', (data) => {
      lastEvent.value = { event: data.event, payload: data.payload, ts: data.ts }

      switch (data.event) {
        case 'qr.updated':
          status.value  = 'qr_pending'
          qrCode.value  = data.payload?.qr ?? null
          break

        case 'session.connected':
          status.value      = 'active'
          phoneNumber.value = data.payload?.phone_number ?? null
          qrCode.value      = null
          error.value       = null
          break

        case 'session.disconnected':
          status.value = 'disconnected'
          break

        case 'session.logged_out':
          status.value      = 'pending'
          phoneNumber.value = null
          qrCode.value      = null
          break

        case 'session.max_reconnects_reached':
          status.value = 'disconnected'
          error.value  = 'Max reconnect attempts reached. Manual reconnect required.'
          break

        case 'instance.expiring':
          // Handled by notification centre — no status change here
          break

        case 'instance.expired':
          status.value = 'suspended'
          break

        case 'session.error':
          error.value  = data.payload?.error ?? 'Session error.'
          break
      }
    })

    channel.error((err) => {
      console.error(`Pusher channel error for instance ${token}:`, err)
    })
  }

  function unsubscribe() {
    if (channel && window.Echo) {
      window.Echo.leave(`instance.${instanceToken.value ?? instanceToken}`)
      channel = null
    }
  }

  // Support both ref and plain string
  if (typeof instanceToken == 'object' && 'value' in instanceToken) {
    watch(instanceToken, (newToken) => {
      if (newToken) subscribe(newToken)
      else unsubscribe()
    }, { immediate: true })
  } else {
    subscribe(instanceToken)
  }

  onUnmounted(unsubscribe)

  return { status, phoneNumber, qrCode, lastEvent, error }
}