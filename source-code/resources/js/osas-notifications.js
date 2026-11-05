const ITEM_SELECTOR = '[data-osas-notification-item]';
const MARK_ALL_SELECTOR = '[data-osas-notification-mark-all]';
const BADGE_SELECTOR = '[data-osas-notification-unread-badge]';
const COUNT_SELECTOR = '[data-osas-notification-unread-count]';
const PROPOSAL_BADGE_SELECTOR = '[data-osas-proposal-pending-badge]';
const PROPOSAL_COUNT_SELECTOR = '[data-osas-proposal-pending-count]';
const DROPDOWN_CONTENT_SELECTOR = '[data-osas-notification-dropdown-content]';
const UNREAD_SELECTOR = '[data-osas-notification-unread]';
const FEEDBACK_SELECTOR = '[data-osas-notification-feedback]';
const BINDING_STATE = Symbol.for('uniorg.osasNotifications.bindings');

export function csrfToken(documentRef = globalThis.document) {
    return documentRef?.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function requestJson(url, {
    fetchImpl = globalThis.fetch,
    documentRef = globalThis.document,
    method = 'POST',
    body,
    headers = {},
} = {}) {
    const token = csrfToken(documentRef);
    const response = await fetchImpl(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            ...headers,
        },
        ...(body === undefined ? {} : { body: typeof body === 'string' ? body : JSON.stringify(body) }),
    });

    if (!response.ok) {
        const error = new Error(`Notification request failed with status ${response.status}.`);
        error.response = response;
        throw error;
    }

    return response.json();
}

function normalizedUnreadCount(unreadCount) {
    const count = Number(unreadCount);
    return Number.isInteger(count) && count >= 0 ? count : null;
}

function normalizedPendingProposalCount(pendingCount) {
    const count = Number(pendingCount);
    return Number.isInteger(count) && count >= 0 ? count : null;
}

export function updateUnreadCount(root, unreadCount) {
    const count = normalizedUnreadCount(unreadCount);
    if (count === null) return false;

    root.querySelectorAll(COUNT_SELECTOR).forEach((target) => {
        target.textContent = String(count);
    });
    root.querySelectorAll(BADGE_SELECTOR).forEach((badge) => {
        const hidden = count === 0;
        badge.hidden = hidden;
        badge.classList.toggle('hidden', hidden);
    });

    return true;
}

export function updatePendingProposalCount(root, pendingCount) {
    const count = normalizedPendingProposalCount(pendingCount);
    if (count === null) return false;

    root.querySelectorAll(PROPOSAL_COUNT_SELECTOR).forEach((target) => {
        target.textContent = String(count);
    });
    root.querySelectorAll(PROPOSAL_BADGE_SELECTOR).forEach((badge) => {
        const hidden = count === 0;
        badge.hidden = hidden;
        badge.classList.toggle('hidden', hidden);
    });

    return true;
}

export function renderRealtimeNotificationState(root, payload) {
    const unreadCount = normalizedUnreadCount(payload?.unread_count);
    const pendingCount = normalizedPendingProposalCount(payload?.pending_proposals_count);
    const dropdown = root.querySelector(DROPDOWN_CONTENT_SELECTOR);

    if (payload?.success !== true
        || unreadCount === null
        || pendingCount === null
        || !dropdown
        || typeof payload.dropdown_html !== 'string') {
        return false;
    }

    dropdown.innerHTML = payload.dropdown_html;
    updateUnreadCount(root, unreadCount);
    updatePendingProposalCount(root, pendingCount);

    return true;
}

function markRenderedItemRead(item) {
    item.dataset.readState = 'read';
    item.classList.remove('bg-blue-50/20');
    item.querySelectorAll(UNREAD_SELECTOR).forEach((indicator) => indicator.remove());
}

export function renderItemRead(item, payload, root = item.ownerDocument) {
    if (payload?.success !== true || normalizedUnreadCount(payload.unread_count) === null) return false;

    markRenderedItemRead(item);
    return updateUnreadCount(root, payload.unread_count);
}

export function renderAllNotificationsRead(root, payload) {
    const count = normalizedUnreadCount(payload?.unread_count);
    if (payload?.success !== true || count === null) return false;

    root.querySelectorAll(ITEM_SELECTOR).forEach(markRenderedItemRead);
    updateUnreadCount(root, count);

    if (count === 0) {
        root.querySelectorAll(MARK_ALL_SELECTOR).forEach((control) => {
            control.hidden = true;
        });
    }

    return true;
}

export function displayNotificationFailure(
    root = globalThis.document,
    message = 'Unable to mark notifications as read. Please try again.',
) {
    const documentRef = root?.nodeType === 9 ? root : root?.ownerDocument;
    if (!documentRef?.body) return null;

    let feedback = documentRef.querySelector(FEEDBACK_SELECTOR);
    if (!feedback) {
        feedback = documentRef.createElement('div');
        feedback.dataset.osasNotificationFeedback = '';
        feedback.setAttribute('role', 'alert');
        feedback.setAttribute('aria-live', 'assertive');
        feedback.className = 'fixed top-6 right-6 z-[10001] max-w-md rounded-lg border-l-4 border-red-500 bg-white px-6 py-4 text-sm font-semibold text-red-700 shadow-xl';
        documentRef.body.appendChild(feedback);
    }

    feedback.textContent = message;
    feedback.hidden = false;
    return feedback;
}

export function navigateTo(href, locationRef = globalThis.location) {
    locationRef.assign(href);
}

export function createElementLock() {
    const elements = new WeakSet();
    return {
        acquire(element) {
            if (elements.has(element)) return false;
            elements.add(element);
            return true;
        },
        release: (element) => elements.delete(element),
        has: (element) => elements.has(element),
    };
}

const notificationItemLock = createElementLock();
const markAllLock = createElementLock();

export async function activateNotificationItem(item, {
    request = requestJson,
    render = renderItemRead,
    navigate = navigateTo,
    lock = notificationItemLock,
} = {}) {
    if (!lock.acquire(item)) return false;

    const href = item.href;
    try {
        const payload = await request(item.dataset.readUrl);
        if (payload?.success !== true) throw new Error('Notification read request was not successful.');
        render(item, payload, item.ownerDocument);
    } catch {
        // Read failures intentionally preserve the rendered state; navigation must still complete.
    } finally {
        try {
            navigate(href);
        } finally {
            lock.release(item);
        }
    }

    return true;
}

export async function activateMarkAll(control, {
    request = requestJson,
    render = renderAllNotificationsRead,
    displayFailure = displayNotificationFailure,
    lock = markAllLock,
} = {}) {
    if (!lock.acquire(control)) return false;

    try {
        const payload = await request(control.dataset.readUrl);
        if (payload?.success !== true || !render(control.ownerDocument, payload)) {
            throw new Error('Notification mark-all request was not successful.');
        }
        return true;
    } catch {
        displayFailure(control.ownerDocument);
        return false;
    } finally {
        lock.release(control);
    }
}

function isPlainClick(event) {
    return !event.defaultPrevented
        && event.button === 0
        && !event.metaKey
        && !event.ctrlKey
        && !event.shiftKey
        && !event.altKey;
}

function isHandledItemClick(event, item) {
    return isPlainClick(event)
        && !item.hasAttribute('download')
        && (!item.target || item.target === '_self')
        && Boolean(item.href && item.dataset.readUrl);
}

function bindingState(root) {
    if (!root[BINDING_STATE]) {
        Object.defineProperty(root, BINDING_STATE, {
            configurable: true,
            value: {},
        });
    }
    return root[BINDING_STATE];
}

export function bindNotificationItemClicks({
    root = globalThis.document,
    request = requestJson,
    render = renderItemRead,
    navigate = navigateTo,
    lock = notificationItemLock,
} = {}) {
    const bindings = bindingState(root);
    if (bindings.items) return bindings.items.dispose;

    const listener = (event) => {
        const item = event.target?.closest?.(ITEM_SELECTOR) ?? null;
        if (!item || !root.contains(item) || !isHandledItemClick(event, item)) return;

        event.preventDefault();
        void activateNotificationItem(item, { request, render, navigate, lock });
    };
    const dispose = () => {
        if (bindings.items?.listener !== listener) return;
        root.removeEventListener('click', listener);
        delete bindings.items;
    };

    bindings.items = { listener, dispose };
    root.addEventListener('click', listener);
    return dispose;
}

export function bindNotificationMarkAllClicks({
    root = globalThis.document,
    request = requestJson,
    render = renderAllNotificationsRead,
    displayFailure = displayNotificationFailure,
    lock = markAllLock,
} = {}) {
    const bindings = bindingState(root);
    if (bindings.markAll) return bindings.markAll.dispose;

    const listener = (event) => {
        const control = event.target?.closest?.(MARK_ALL_SELECTOR) ?? null;
        if (!control || !root.contains(control) || !isPlainClick(event) || !control.dataset.readUrl) return;

        event.preventDefault();
        void activateMarkAll(control, { request, render, displayFailure, lock });
    };
    const dispose = () => {
        if (bindings.markAll?.listener !== listener) return;
        root.removeEventListener('click', listener);
        delete bindings.markAll;
    };

    bindings.markAll = { listener, dispose };
    root.addEventListener('click', listener);
    return dispose;
}

export function initializeOsasNotifications(options = {}) {
    const disposeItems = bindNotificationItemClicks(options);
    const disposeMarkAll = bindNotificationMarkAllClicks(options);

    return () => {
        disposeItems();
        disposeMarkAll();
    };
}

function realtimeStateUrl(root) {
    return root?.body?.dataset?.osasNotificationStateUrl ?? '';
}

export function bindOsasProposalQueueUpdates({
    root = globalThis.document,
    echo = globalThis.Echo,
    request = requestJson,
    render = renderRealtimeNotificationState,
} = {}) {
    const bindings = bindingState(root);
    if (bindings.proposalQueue) return bindings.proposalQueue.dispose;

    const stateUrl = realtimeStateUrl(root);
    if (!stateUrl || !echo || typeof echo.private !== 'function') return () => {};

    let refreshPromise = null;
    let refreshQueued = false;

    const refreshState = () => {
        if (refreshPromise) {
            refreshQueued = true;
            return refreshPromise;
        }

        refreshPromise = (async () => {
            do {
                refreshQueued = false;

                try {
                    const payload = await request(stateUrl, { method: 'GET' });
                    render(root, payload);
                } catch {
                    // A transient realtime refresh failure must not interrupt OSAS work.
                    // The server-rendered state is still refreshed on the next navigation.
                }
            } while (refreshQueued);
        })().finally(() => {
            refreshPromise = null;
        });

        return refreshPromise;
    };

    const channel = echo.private('osas-proposals');
    channel.listen('.osas-proposal-queue-updated', refreshState);

    const dispose = () => {
        if (bindings.proposalQueue?.dispose !== dispose) return;

        if (typeof channel.stopListening === 'function') {
            channel.stopListening('.osas-proposal-queue-updated', refreshState);
        }

        delete bindings.proposalQueue;
    };

    bindings.proposalQueue = { dispose };
    return dispose;
}

if (typeof document !== 'undefined') {
    initializeOsasNotifications({ root: document });
    bindOsasProposalQueueUpdates({ root: document });
}
