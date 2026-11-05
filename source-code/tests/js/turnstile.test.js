import { readFileSync } from 'node:fs';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';

const managerSource = readFileSync('public/js/turnstile.js', 'utf8');
const authSource = readFileSync('public/js/auth.js', 'utf8');
const viewSource = readFileSync('resources/views/auth/org_auth.blade.php', 'utf8');
const recoverySource = viewSource.split('// --- FORGOT PASSWORD LOGIC ---')[1].split('</script>')[0]
    .replace("{{ route('password.email') }}", '/forgot-password')
    .replace("{{ route('password.reset') }}", '/reset-password');
const widget = (action) => `<div data-turnstile data-sitekey="public-key" data-action="${action}">
    <div data-turnstile-widget></div><p data-turnstile-status></p></div>`;
let options;
let login;
let recovery;

const settle = async () => { for (let step = 0; step < 12; step++) await Promise.resolve(); };
const event = () => new Event('submit', { cancelable: true });

function solve(formId, token) {
    const element = document.querySelector(`#${formId} [data-turnstile-widget]`);
    const entry = options.find((entry) => entry.element === element);
    entry.element.closest('form').querySelector('[name="cf-turnstile-response"]').value = token;
    entry.config.callback(token);
    return entry;
}

beforeEach(() => {
    options = [];
    document.body.innerHTML = `<meta name="csrf-token" content="test-csrf">
        <form id="loginForm" action="/org/login"><input name="student_id" value="TEST-ID">
            <input name="password" value="password">${widget('login')}<button type="submit">Login</button></form>
        <div id="forgotPasswordModal" class="hidden"><h3 id="forgotPasswordTitle"></h3>
            <div id="fpStep1"><form id="requestResetForm"><input id="fp_email" value="owner@example.com">
                ${widget('password_email')}<button id="fp_btn_1">Send code</button></form></div>
            <div id="fpStep2" class="hidden"><strong id="fp_display_email"></strong>
                <form id="resetPasswordForm"><input id="fp_code" value="123456">
                    <input id="fp_new_password" value="NewPassword123!">
                    <input id="fp_confirm_password" value="NewPassword123!">
                    ${widget('password_reset')}<button id="fp_btn_2">Reset</button></form></div></div>`;
    window.turnstile = {
        render: vi.fn((element, config) => {
            const id = `widget-${options.length}`;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cf-turnstile-response';
            element.appendChild(input);
            options.push({ id, element, config });
            return id;
        }),
        reset: vi.fn(),
    };
    vi.stubGlobal('showToast', vi.fn());
    vi.stubGlobal('fetch', vi.fn());
    new Function(managerSource)();
    window.testToast = globalThis.showToast;
    login = new Function(`${authSource}\nshowToast = window.testToast; return validateLoginForm;`)();
    recovery = new Function(`${recoverySource}\nreturn { openForgotPasswordModal, requestResetCode, submitNewPassword };`)();
    window.initUniOrgTurnstile();
});

afterEach(() => {
    vi.unstubAllGlobals();
    delete window.UniOrgTurnstile;
    delete window.initUniOrgTurnstile;
    delete window.turnstile;
    delete window.testToast;
});

it('blocks unverified submissions and only renders visible forms', () => {
    login(event());
    expect(fetch).not.toHaveBeenCalled();
    expect(options).toHaveLength(1);
    expect(options[0].config.action).toBe('login');
    expect(showToast).toHaveBeenCalledWith(expect.stringContaining('security verification'), 'error');
});

it('submits the login token, shows server verification errors and refreshes for a retry', async () => {
    solve('loginForm', 'login-token');
    fetch.mockResolvedValue({ json: async () => ({ success: false, errors: { 'cf-turnstile-response': ['Verification expired.'] } }) });
    login(event());
    expect(fetch.mock.calls[0][1].body.get('cf-turnstile-response')).toBe('login-token');
    await settle();
    expect(showToast).toHaveBeenCalledWith('Verification expired.', 'error');
    expect(document.querySelector('#loginForm button').disabled).toBe(false);
    expect(window.turnstile.reset).toHaveBeenCalledWith('widget-0');
    expect(window.UniOrgTurnstile.token('loginForm')).toBe('');
});

it('expires a completed challenge and requires a fresh token', () => {
    const entry = solve('loginForm', 'expired-token');
    entry.config['expired-callback']();
    expect(window.UniOrgTurnstile.requireToken('loginForm')).toBe(false);
    expect(window.turnstile.reset).toHaveBeenCalledWith(entry.id);
});

it('uses separate fresh tokens for sending the code and resetting the password', async () => {
    recovery.openForgotPasswordModal(event());
    solve('requestResetForm', 'email-token');
    fetch.mockResolvedValue({ json: async () => ({ message: 'Code sent' }), status: 200 });
    recovery.requestResetCode(event());
    expect(JSON.parse(fetch.mock.calls[0][1].body)['cf-turnstile-response']).toBe('email-token');
    await settle();
    expect(document.getElementById('fpStep2').classList.contains('hidden')).toBe(false);
    expect(options).toHaveLength(3);
    document.getElementById('fp_code').value = '123456';
    document.getElementById('fp_new_password').value = 'NewPassword123!';
    document.getElementById('fp_confirm_password').value = 'NewPassword123!';
    solve('resetPasswordForm', 'reset-token');
    recovery.submitNewPassword(event());
    expect(fetch.mock.calls[1][0]).toBe('/reset-password');
    expect(JSON.parse(fetch.mock.calls[1][1].body)['cf-turnstile-response']).toBe('reset-token');
    await settle();
    expect(document.getElementById('forgotPasswordModal').classList.contains('hidden')).toBe(true);
    expect(window.UniOrgTurnstile.token('resetPasswordForm')).toBe('');
});

it('restores login and recovery controls after network failures', async () => {
    fetch.mockRejectedValue(new Error('Offline'));
    vi.spyOn(console, 'error').mockImplementation(() => {});
    solve('loginForm', 'login-token');
    login(event());
    await settle();
    expect(document.querySelector('#loginForm button').disabled).toBe(false);
    expect(window.UniOrgTurnstile.token('loginForm')).toBe('');

    recovery.openForgotPasswordModal(event());
    solve('requestResetForm', 'email-token');
    recovery.requestResetCode(event());
    await settle();
    expect(document.getElementById('fp_btn_1').disabled).toBe(false);
    expect(window.UniOrgTurnstile.token('requestResetForm')).toBe('');
    expect(document.getElementById('fpStep2').classList.contains('hidden')).toBe(true);
});

it('mounts an opened modal when the Cloudflare script finishes loading late', () => {
    const api = window.turnstile;
    delete window.turnstile;
    new Function(managerSource)();
    recovery.openForgotPasswordModal(event());
    expect(window.UniOrgTurnstile.requireToken('requestResetForm')).toBe(false);
    window.turnstile = api;
    window.initUniOrgTurnstile();
    expect(window.turnstile.render).toHaveBeenCalledWith(
        document.querySelector('#requestResetForm [data-turnstile-widget]'),
        expect.objectContaining({ action: 'password_email' }));
});
