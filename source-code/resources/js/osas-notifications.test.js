import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    bindOsasProposalQueueUpdates,
    renderRealtimeNotificationState,
    updatePendingProposalCount,
} from './osas-notifications.js';

let dispose;

function statePayload({ unread = 2, pending = 3 } = {}) {
    return {
        success: true,
        unread_count: unread,
        pending_proposals_count: pending,
        dropdown_html: '<p data-realtime-item>Updated notification list</p>',
    };
}

beforeEach(() => {
    document.body.dataset.osasNotificationStateUrl = '/osas/notifications/state';
    document.body.innerHTML = `
        <span data-osas-notification-unread-badge class="hidden"><span data-osas-notification-unread-count>0</span></span>
        <span data-osas-proposal-pending-badge class="hidden" hidden><span data-osas-proposal-pending-count>0</span></span>
        <div data-osas-notification-dropdown-content><p>Previous notification list</p></div>`;
});

afterEach(() => {
    dispose?.();
    dispose = null;
    document.body.innerHTML = '';
    delete document.body.dataset.osasNotificationStateUrl;
    vi.restoreAllMocks();
});

describe('OSAS realtime proposal notifications', () => {
    it('updates the dropdown, unread bell, and pending proposal badge from server state', () => {
        expect(renderRealtimeNotificationState(document, statePayload())).toBe(true);

        expect(document.querySelector('[data-realtime-item]').textContent).toBe('Updated notification list');
        expect(document.querySelector('[data-osas-notification-unread-count]').textContent).toBe('2');
        expect(document.querySelector('[data-osas-notification-unread-badge]').hidden).toBe(false);
        expect(document.querySelector('[data-osas-proposal-pending-count]').textContent).toBe('3');
        expect(document.querySelector('[data-osas-proposal-pending-badge]').hidden).toBe(false);
    });

    it('hides the outstanding-work badge when the final pending proposal is decided', () => {
        expect(updatePendingProposalCount(document, 0)).toBe(true);

        const badge = document.querySelector('[data-osas-proposal-pending-badge]');
        expect(badge.hidden).toBe(true);
        expect(badge.classList.contains('hidden')).toBe(true);
    });

    it('refreshes silently after the private queue event', async () => {
        let listener;
        const channel = {
            listen: vi.fn((eventName, callback) => {
                expect(eventName).toBe('.osas-proposal-queue-updated');
                listener = callback;
                return channel;
            }),
            stopListening: vi.fn(),
        };
        const echo = { private: vi.fn(() => channel) };
        const request = vi.fn().mockResolvedValue(statePayload({ unread: 4, pending: 1 }));

        dispose = bindOsasProposalQueueUpdates({ root: document, echo, request });
        listener();

        await vi.waitFor(() => {
            expect(request).toHaveBeenCalledWith('/osas/notifications/state', { method: 'GET' });
        });

        expect(document.querySelector('[data-osas-notification-unread-count]').textContent).toBe('4');
        expect(document.querySelector('[data-osas-proposal-pending-count]').textContent).toBe('1');
        expect(echo.private).toHaveBeenCalledWith('osas-proposals');
    });

    it('does not mutate the UI when the realtime state request fails', async () => {
        let listener;
        const channel = {
            listen: vi.fn((_, callback) => {
                listener = callback;
                return channel;
            }),
            stopListening: vi.fn(),
        };
        const echo = { private: vi.fn(() => channel) };
        const request = vi.fn().mockRejectedValue(new Error('network unavailable'));

        dispose = bindOsasProposalQueueUpdates({ root: document, echo, request });
        listener();

        await vi.waitFor(() => expect(request).toHaveBeenCalledOnce());

        expect(document.querySelector('[data-osas-notification-unread-count]').textContent).toBe('0');
        expect(document.querySelector('[data-osas-proposal-pending-count]').textContent).toBe('0');
    });
});
