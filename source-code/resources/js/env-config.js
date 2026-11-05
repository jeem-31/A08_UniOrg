/**
 * Public browser build-time configuration from environment variables.
 *
 * These values are PUBLIC by design:
 *   - Pusher app key + cluster are the client-side key (the app secret stays
 *     server-side and never reaches the browser).
 *   - Supabase URL + anon key are public; row-level security protects data.
 *
 * Deployment-specific values are intentionally omitted from this submission.
 * Set VITE_* variables before building
 * (local, Docker, Render) without relying on .env files or build args.
 * A VITE_* env value, when present and not a literal "${...}" placeholder,
 * overrides the default.
 */

const DEFAULTS = {
    VITE_PUSHER_APP_KEY: '',
    VITE_PUSHER_APP_CLUSTER: 'ap1',
    VITE_SUPABASE_URL: '',
    VITE_SUPABASE_ANON_KEY: '',
};

function envValue(name) {
    const fromEnv = import.meta.env[name];
    const isUsable = typeof fromEnv === 'string' && fromEnv !== '' && !fromEnv.includes('${');
    return isUsable ? fromEnv : (DEFAULTS[name] || '');
}

export const PUSHER_APP_KEY = envValue('VITE_PUSHER_APP_KEY');
export const PUSHER_APP_CLUSTER = envValue('VITE_PUSHER_APP_CLUSTER');
export const SUPABASE_URL = envValue('VITE_SUPABASE_URL');
export const SUPABASE_ANON_KEY = envValue('VITE_SUPABASE_ANON_KEY');
