export function isProtectedMembershipFeeUpdate(payload = {}) {
    return Boolean(payload?.is_required_membership_fee ?? payload?.payment?.is_required_membership_fee);
}

export function refreshMembershipFeePages(refresh = globalThis.window?.refreshCurrentRealtimePage) {
    if (typeof refresh !== 'function') return false;

    refresh('events');
    refresh('payments');

    return true;
}

export function bindMembershipFeeEligibilityUpdates({
    echo = globalThis.window?.Echo,
    userId = globalThis.window?.Laravel?.userId,
    refresh = globalThis.window?.refreshCurrentRealtimePage,
} = {}) {
    if (!echo || typeof echo.private !== 'function' || !userId) return () => {};

    const channel = echo.private(`membership-fee.${userId}`);
    const listener = (payload) => refreshMembershipFeePages(refresh, payload);
    channel.listen('.membership-fee-eligibility-updated', listener);

    return () => channel.stopListening?.('.membership-fee-eligibility-updated');
}
