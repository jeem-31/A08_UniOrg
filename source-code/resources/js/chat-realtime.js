/**
 * Supabase Realtime chat bridge.
 *
 * The chat pages subscribe to INSERT changes on the `admin_chats` table via
 * Supabase Realtime instead of relying on a Pusher broadcast round-trip. The
 * message row is streamed straight from the database the moment it is saved,
 * so delivery is much faster and needs no server-side broadcast at all.
 *
 * Pages register callbacks through window.ChatRealtime.onMessage(). A generic
 * `chat-message-sent` CustomEvent is also dispatched for legacy badge scripts.
 */
import { createClient } from '@supabase/supabase-js';
import { SUPABASE_URL, SUPABASE_ANON_KEY } from './env-config';

const supabaseUrl = SUPABASE_URL;
const supabaseAnonKey = SUPABASE_ANON_KEY;
const hasConfig = Boolean(supabaseUrl && supabaseAnonKey);

const supabase = hasConfig ? createClient(supabaseUrl, supabaseAnonKey) : null;

let started = false;
const listeners = new Set();

function start() {
    if (!supabase || started) return;
    started = true;

    supabase
        .channel('admin_chats_changes')
        .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'admin_chats' }, (payload) => {
            const row = payload && payload.new;
            if (!row) return;

            listeners.forEach((callback) => {
                try {
                    callback(row);
                } catch (error) {
                    console.error('ChatRealtime listener error:', error);
                }
            });

            window.dispatchEvent(new CustomEvent('chat-message-sent', { detail: row }));
        })
        .subscribe();
}

window.ChatRealtime = {
    onMessage(callback) {
        if (typeof callback !== 'function') return null;
        listeners.add(callback);
        start();
        return () => listeners.delete(callback);
    },
    isEnabled() {
        return hasConfig;
    },
};

if (!hasConfig) {
    console.error('Supabase Realtime is not configured. Add VITE_SUPABASE_URL and VITE_SUPABASE_ANON_KEY, then rebuild the frontend.');
}
