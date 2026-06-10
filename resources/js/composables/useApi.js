/**
 * useApi.js — Dashboard axios helpers
 *
 * All calls go to web.php routes (/dashboard/*, /client/*, /super/*).
 * Auth = session cookie (set when user logged in). No Bearer token needed.
 *
 * External API calls (/api/gateway/*) are NOT made from the dashboard.
 * Those are for developer apps using the Bearer token.
 */

import axios from 'axios'

// ── Base axios instance ───────────────────────────────────────────────────────
// withCredentials: true sends the session cookie automatically.
// CSRF token is needed for POST/PATCH/DELETE.

const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content

const webHttp = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken ?? '',
    },
    withCredentials: true,
})

// ── Instance API (/dashboard/instances/*) ─────────────────────────────────────

export const instanceApi = {
    // CRUD
    list: (params = {}) => webHttp.get('/dashboard/instances/api', { params }),
    create: (data) => webHttp.post('/dashboard/instances', data),
    get: (id) => webHttp.get(`/dashboard/instances/${id}`),
    update: (id, data) => webHttp.patch(`/dashboard/instances/${id}`, data),
    delete: (id) => webHttp.delete(`/dashboard/instances/${id}`),

    // Top up credits on an instance (sends add_credits in patch body)
    topUp: (id, credits) => webHttp.patch(`/dashboard/instances/${id}`, { add_credits: credits }),

    // Session management
    connect: (id) => webHttp.post(`/dashboard/instances/${id}/connect`),
    qr: (id) => webHttp.get(`/dashboard/instances/${id}/qr`),
    liveStatus: (id) => webHttp.get(`/dashboard/instances/${id}/live-status`),
    accountInfo: (id) => webHttp.get(`/dashboard/instances/${id}/account-info`),
    logout: (id) => webHttp.post(`/dashboard/instances/${id}/logout`),
    groups: (id) => webHttp.get(`/dashboard/instances/${id}/groups`),

    // System health
    health: () => webHttp.get('/dashboard/baileys-health'),
}

// ── Client Admin instance API (/client/instances/*) ───────────────────────────
// Client admin uses the same InstanceController but under /client/ prefix.

export const clientInstanceApi = {
    list: (params = {}) => webHttp.get('/client/instances/api', { params }),
    create: (data) => webHttp.post('/client/instances', data),
    update: (id, data) => webHttp.patch(`/client/instances/${id}`, data),
    topUp: (id, credits) => webHttp.patch(`/client/instances/${id}`, { add_credits: credits }),
    delete: (id) => webHttp.delete(`/client/instances/${id}`),
    connect: (id) => webHttp.post(`/client/instances/${id}/connect`),
    qr: (id) => webHttp.get(`/client/instances/${id}/qr`),
    logout: (id) => webHttp.post(`/client/instances/${id}/logout`),
    groups: (id) => webHttp.get(`/client/instances/${id}/groups`),
}

// ── Token API (/dashboard/tokens/*) ──────────────────────────────────────────

export const tokenApi = {
    list: () => webHttp.get('/dashboard/tokens/api'),
    create: (data) => webHttp.post('/dashboard/tokens', data),
    revoke: (id) => webHttp.delete(`/dashboard/tokens/${id}`),
}

// ── User management API (/client/users/*) ────────────────────────────────────

export const userApi = {
    list: (params = {}) => webHttp.get('/client/users', { params }),
    create: (data) => webHttp.post('/client/users', data),
    update: (id, data) => webHttp.patch(`/client/users/${id}`, data),
    allocateCredits: (id, credits) => webHttp.post(`/client/users/${id}/credits`, { credits }),
}

// ── Client management API (/super/clients/*) ──────────────────────────────────

export const clientApi = {
    list: (params = {}) => webHttp.get('/super/clients', { params }),
    create: (data) => webHttp.post('/super/clients', data),
    update: (id, data) => webHttp.patch(`/super/clients/${id}`, data),
    destroy: (id) => webHttp.delete(`/super/clients/${id}`),
}

// ── Credit APIs ───────────────────────────────────────────────────────────────

export const creditApi = {
    // User ledger
    userLedger: (params = {}) => webHttp.get('/dashboard/credits/ledger', { params }),
    // Client ledger
    clientLedger: (params = {}) => webHttp.get('/client/credits/ledger', { params }),
    // Super admin adjust
    adjust: (data) => webHttp.post('/super/credits/adjust', data),
    superLedger: (params = {}) => webHttp.get('/super/credits/ledger', { params }),
}

// ── Health check ──────────────────────────────────────────────────────────────

export const healthApi = {
    baileys: () => webHttp.get('/dashboard/baileys-health'),
    superBaileys: () => webHttp.get('/super/baileys-health'),
}

// ── Message API (/dashboard/messages/*) ──────────────────────────────────────

export const messageApi = {
    // Quick send from dashboard
    send: (data) => webHttp.post('/dashboard/messages', data),
    // Message log with filters
    list: (params = {}) => webHttp.get('/dashboard/messages', { params }),
    // Inbound inbox only
    inbox: (params = {}) => webHttp.get('/dashboard/messages/inbox', { params }),
    // Stats for overview widget
    stats: () => webHttp.get('/dashboard/messages/stats'),
}

// ── Webhook API (/dashboard/webhooks/*) ──────────────────────────────────────

export const webhookApi = {
    list: () => webHttp.get('/dashboard/webhooks/api'),
    create: (data) => webHttp.post('/dashboard/webhooks', data),
    update: (id, data) => webHttp.patch(`/dashboard/webhooks/${id}`, data),
    delete: (id) => webHttp.delete(`/dashboard/webhooks/${id}`),
    ping: (id) => webHttp.post(`/dashboard/webhooks/${id}/ping`),
    logs: (id) => webHttp.get(`/dashboard/webhooks/${id}/logs`),
}

// ── Contact API (/dashboard/contacts/*) ──────────────────────────────────────

export const contactApi = {
    list: (params = {}) => webHttp.get('/dashboard/contacts/api', { params }),
    create: (data) => webHttp.post('/dashboard/contacts', data),
    update: (id, data) => webHttp.patch(`/dashboard/contacts/${id}`, data),
    delete: (id) => webHttp.delete(`/dashboard/contacts/${id}`),
    import: (formData) => webHttp.post('/dashboard/contacts/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
    tags: () => webHttp.get('/dashboard/contacts/tags'),
    groups: () => webHttp.get('/dashboard/contacts/groups'),
    createGroup: (data) => webHttp.post('/dashboard/contacts/groups', data),
    addToGroup: (groupId, ids) => webHttp.post(`/dashboard/contacts/groups/${groupId}/add`, { contact_ids: ids }),
    removeFromGroup: (groupId, contactId) => webHttp.post(`/dashboard/contacts/groups/${groupId}/remove/${contactId}`),
    deleteGroup: (groupId) => webHttp.delete(`/dashboard/contacts/groups/${groupId}/destroy`)
}

// ── Campaign API (/dashboard/campaigns/*) ────────────────────────────────────

export const campaignApi = {
    list: (params = {}) => webHttp.get('/dashboard/campaigns/api', { params }),
    create: (data) => webHttp.post('/dashboard/campaigns', data),
    get: (id) => webHttp.get(`/dashboard/campaigns/${id}`),
    update: (id, data) => webHttp.patch(`/dashboard/campaigns/${id}`, data),
    launch: (id) => webHttp.post(`/dashboard/campaigns/${id}/launch`),
    pause: (id) => webHttp.post(`/dashboard/campaigns/${id}/pause`),
    resume: (id) => webHttp.post(`/dashboard/campaigns/${id}/resume`),
    cancel: (id) => webHttp.post(`/dashboard/campaigns/${id}/cancel`),
    recipients: (id, params = {}) => webHttp.get(`/dashboard/campaigns/${id}/recipients`, { params }),
    analytics: (id) => webHttp.get(`/dashboard/campaigns/${id}/analytics`),
}

// ── Report API (/dashboard/reports/*) ────────────────────────────────────────

export const reportApi = {
    overview: (params = {}) => webHttp.get('/dashboard/reports/overview', { params }),
    dailyVolume: (params = {}) => webHttp.get('/dashboard/reports/daily-volume', { params }),
    byInstance: (params = {}) => webHttp.get('/dashboard/reports/by-instance', { params }),
    typeBreakdown: (params = {}) => webHttp.get('/dashboard/reports/type-breakdown', { params }),
    hourlyHeatmap: (params = {}) => webHttp.get('/dashboard/reports/hourly-heatmap', { params }),
    campaignFunnel: (params = {}) => webHttp.get('/dashboard/reports/campaign-funnel', { params }),
    exportUrl: (days = 30) => `/dashboard/reports/export?days=${days}`,
}

export default webHttp