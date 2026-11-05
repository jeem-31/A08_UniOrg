import { describe, expect, it, vi } from 'vitest';
import {
    bindMembershipFeeEligibilityUpdates,
    isProtectedMembershipFeeUpdate,
    refreshMembershipFeePages,
} from './membership-fee-realtime.js';

describe('Membership Fee realtime behavior', () => {
    it('identifies membership template updates without matching ordinary payments', () => {
        expect(isProtectedMembershipFeeUpdate({ payment: { is_required_membership_fee: true } })).toBe(true);
        expect(isProtectedMembershipFeeUpdate({ is_required_membership_fee: true })).toBe(true);
        expect(isProtectedMembershipFeeUpdate({ payment: { is_required_membership_fee: false } })).toBe(false);
    });

    it('refreshes only in-app event and payment fragments', () => {
        const refresh = vi.fn();

        expect(refreshMembershipFeePages(refresh)).toBe(true);
        expect(refresh).toHaveBeenNthCalledWith(1, 'events');
        expect(refresh).toHaveBeenNthCalledWith(2, 'payments');
    });

    it('subscribes to the authenticated students private eligibility channel', () => {
        let listener;
        const stopListening = vi.fn();
        const channel = {
            listen: vi.fn((eventName, callback) => {
                expect(eventName).toBe('.membership-fee-eligibility-updated');
                listener = callback;
                return channel;
            }),
            stopListening,
        };
        const echo = { private: vi.fn(() => channel) };
        const refresh = vi.fn();

        const dispose = bindMembershipFeeEligibilityUpdates({ echo, userId: 42, refresh });
        listener({ eligible: true });

        expect(echo.private).toHaveBeenCalledWith('membership-fee.42');
        expect(refresh).toHaveBeenCalledWith('events');
        expect(refresh).toHaveBeenCalledWith('payments');

        dispose();
        expect(stopListening).toHaveBeenCalledWith('.membership-fee-eligibility-updated');
    });

    it('does not subscribe or trigger a browser reload without an authenticated channel', () => {
        const refresh = vi.fn();

        const dispose = bindMembershipFeeEligibilityUpdates({ echo: null, userId: null, refresh });
        dispose();

        expect(refresh).not.toHaveBeenCalled();
    });
});
