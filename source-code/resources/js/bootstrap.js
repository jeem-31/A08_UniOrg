/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {
    PUSHER_APP_KEY,
    PUSHER_APP_CLUSTER,
} from './env-config';
import {
    bindMembershipFeeEligibilityUpdates,
    isProtectedMembershipFeeUpdate,
} from './membership-fee-realtime';

window.Pusher = Pusher;

const pusherAnnouncementKey = PUSHER_APP_KEY;
const pusherAnnouncementCluster = PUSHER_APP_CLUSTER;
const hasPusherConfiguration = Boolean(pusherAnnouncementKey && pusherAnnouncementCluster);

window.Echo = hasPusherConfiguration
    ? new Echo({
        broadcaster: 'pusher',
        key: pusherAnnouncementKey,
        cluster: pusherAnnouncementCluster,
        forceTLS: true,
    })
    : null;

window.AnnouncementEcho = window.Echo;

function refreshRealtimePage(topic) {
    if (typeof window.refreshCurrentRealtimePage !== 'function') return false;

    window.refreshCurrentRealtimePage(topic);
    return true;
}

if (hasPusherConfiguration) {
    window.dispatchEvent(new Event('announcement-echo-ready'));
} else {
    console.error('Pusher is not configured. Add VITE_PUSHER_APP_KEY and VITE_PUSHER_APP_CLUSTER, then rebuild the frontend.');
}

// Real-time updates via Echo (without Livewire)
document.addEventListener('DOMContentLoaded', () => {
    console.log('Setting up Echo listeners for real-time updates...');
    if (!window.Echo) return;
    
    // Listen for events
    window.Echo.channel('events')
        .listen('.event-created', (e) => {
            console.log('✅ Echo received: event-created', e);
            window.dispatchEvent(new CustomEvent('event-created', { detail: e }));
            refreshRealtimePage('events');
        })
        .listen('.event-updated', (e) => {
            console.log('✅ Echo received: event-updated', e);
            window.dispatchEvent(new CustomEvent('event-updated', { detail: e }));
            refreshRealtimePage('events');
        })
        .listen('.event-deleted', (e) => {
            console.log('✅ Echo received: event-deleted', e);
            window.dispatchEvent(new CustomEvent('event-deleted', { detail: e }));
            refreshRealtimePage('events');
        });

    // Listen for payments
    window.Echo.channel('payments')
        .listen('.payment-created', (e) => {
            console.log('✅ Echo received: payment-created', e);
            window.dispatchEvent(new CustomEvent('payment-created', { detail: e }));
            refreshRealtimePage('payments');
            if (isProtectedMembershipFeeUpdate(e)) refreshRealtimePage('events');
        })
        .listen('.payment-updated', (e) => {
            console.log('✅ Echo received: payment-updated', e);
            window.dispatchEvent(new CustomEvent('payment-updated', { detail: e }));
            refreshRealtimePage('payments');
            if (isProtectedMembershipFeeUpdate(e)) refreshRealtimePage('events');
        })
        .listen('.payment-deleted', (e) => {
            console.log('✅ Echo received: payment-deleted', e);
            window.dispatchEvent(new CustomEvent('payment-deleted', { detail: e }));
            refreshRealtimePage('payments');
        });




    // Listen for complaints
    window.Echo.channel('complaints')
        .listen('.complaint-created', (e) => {
            console.log('✅ Echo received: complaint-created', e);
            window.dispatchEvent(new CustomEvent('complaint-created', { detail: e }));
            refreshRealtimePage('complaints');
        })
        .listen('.complaint-updated', (e) => {
            console.log('✅ Echo received: complaint-updated', e);
            window.dispatchEvent(new CustomEvent('complaint-updated', { detail: e }));
            refreshRealtimePage('complaints');
        })
        .listen('.complaint-deleted', (e) => {
            console.log('✅ Echo received: complaint-deleted', e);
            window.dispatchEvent(new CustomEvent('complaint-deleted', { detail: e }));
            refreshRealtimePage('complaints');
        });

    // Listen for library resources
    window.Echo.channel('library')
        .listen('.library-resource-created', (e) => {
            console.log('✅ Echo received: library-resource-created', e);
            window.dispatchEvent(new CustomEvent('library-resource-created', { detail: e }));
            refreshRealtimePage('library');
        })
        .listen('.library-resource-updated', (e) => {
            console.log('✅ Echo received: library-resource-updated', e);
            window.dispatchEvent(new CustomEvent('library-resource-updated', { detail: e }));
            refreshRealtimePage('library');
        })
        .listen('.library-resource-deleted', (e) => {
            console.log('✅ Echo received: library-resource-deleted', e);
            window.dispatchEvent(new CustomEvent('library-resource-deleted', { detail: e }));
            refreshRealtimePage('library');
        });

    // Listen for edushare items
    window.Echo.channel('edushare')
        .listen('.edushare-item-created', (e) => {
            console.log('✅ Echo received: edushare-item-created', e);
            window.dispatchEvent(new CustomEvent('edushare-item-created', { detail: e }));
            refreshRealtimePage('edushare');
        })
        .listen('.edushare-item-updated', (e) => {
            console.log('✅ Echo received: edushare-item-updated', e);
            window.dispatchEvent(new CustomEvent('edushare-item-updated', { detail: e }));
            refreshRealtimePage('edushare');
        })
        .listen('.edushare-item-deleted', (e) => {
            console.log('✅ Echo received: edushare-item-deleted', e);
            window.dispatchEvent(new CustomEvent('edushare-item-deleted', { detail: e }));
            if (!window.adminEdushareDeleteInProgress) refreshRealtimePage('edushare');
        });

    // Listen for elections
    window.Echo.channel('elections')
        .listen('.election-created', (e) => {
            console.log('✅ Echo received: election-created', e);
            window.dispatchEvent(new CustomEvent('election-created', { detail: e }));
            refreshRealtimePage('elections');
        })
        .listen('.election-updated', (e) => {
            console.log('✅ Echo received: election-updated', e);
            window.dispatchEvent(new CustomEvent('election-updated', { detail: e }));
            refreshRealtimePage('elections');
        })
        .listen('.election-deleted', (e) => {
            console.log('✅ Echo received: election-deleted', e);
            window.dispatchEvent(new CustomEvent('election-deleted', { detail: e }));
            refreshRealtimePage('elections');
        });

    // Listen for documents
    window.Echo.channel('documents')
        .listen('.document-created', (e) => {
            console.log('✅ Echo received: document-created', e);
            window.dispatchEvent(new CustomEvent('document-created', { detail: e }));
            refreshRealtimePage('documents');
        })
        .listen('.document-updated', (e) => {
            console.log('✅ Echo received: document-updated', e);
            window.dispatchEvent(new CustomEvent('document-updated', { detail: e }));
            refreshRealtimePage('documents');
        })
        .listen('.document-deleted', (e) => {
            console.log('✅ Echo received: document-deleted', e);
            window.dispatchEvent(new CustomEvent('document-deleted', { detail: e }));
            refreshRealtimePage('documents');
        });

    // Listen for FAQs
    window.Echo.channel('faqs')
        .listen('.faq-created', (e) => {
            console.log('✅ Echo received: faq-created', e);
            window.dispatchEvent(new CustomEvent('faq-created', { detail: e }));
            refreshRealtimePage('chatbot');
        })
        .listen('.faq-updated', (e) => {
            console.log('✅ Echo received: faq-updated', e);
            window.dispatchEvent(new CustomEvent('faq-updated', { detail: e }));
            refreshRealtimePage('chatbot');
        })
        .listen('.faq-deleted', (e) => {
            console.log('✅ Echo received: faq-deleted', e);
            window.dispatchEvent(new CustomEvent('faq-deleted', { detail: e }));
            refreshRealtimePage('chatbot');
        });

    // Listen for student/admin chat messages
    window.Echo.channel('admin-chat')
        .listen('.message-sent', (e) => {
            console.log('Chat message received:', e);
            window.dispatchEvent(new CustomEvent('chat-message-sent', { detail: e }));
            refreshRealtimePage('chatbot');
        });


    // Listen for notifications (user-specific channel)
    if (window.Laravel && window.Laravel.userId) {
        window.Echo.channel('notifications.' + window.Laravel.userId)
            .listen('.notification-created', (e) => {
                console.log('New notification received:', e);
                window.dispatchEvent(new CustomEvent('notification-created', { detail: e }));
            });

        bindMembershipFeeEligibilityUpdates({
            echo: window.Echo,
            userId: window.Laravel.userId,
            refresh: refreshRealtimePage,
        });
    }
});

