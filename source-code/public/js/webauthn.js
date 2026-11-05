/**
 * UniOrg WebAuthn helper — fingerprint login for student mobile only
 * Handles: register (portfolio) and login (org_auth) via platform authenticator
 */

const WebAuthn = (() => {
    function b64urlToUint8Array(b64url) {
        const pad = '='.repeat((4 - (b64url.length % 4)) % 4);
        const b64 = (b64url + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        const arr = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    function uint8ArrayToB64url(arr) {
        let str = '';
        const bytes = new Uint8Array(arr);
        for (let i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function arrayBufferToB64url(buffer) {
        return uint8ArrayToB64url(new Uint8Array(buffer));
    }

    function isSecureContextOk() {
        return window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    }

    async function isPlatformAvailable() {
        if (!window.PublicKeyCredential) return false;
        try {
            if (PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) {
                return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            }
        } catch (e) {}
        return !!window.PublicKeyCredential;
    }

    function isMobile() {
        const ua = navigator.userAgent || '';
        const mobileUA = /android|iphone|ipad|ipod|mobile/i.test(ua);
        const hasTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
        const smallViewport = window.matchMedia('(max-width: 1024px)').matches;
        return mobileUA || (hasTouch && smallViewport);
    }

    function mapWebAuthnError(err) {
        const name = err && err.name ? err.name : '';
        const rawMsg = (err && err.message ? err.message : '').toString();
        const msg = rawMsg.toLowerCase();

        if (name === 'NotSupportedError') {
            return 'Fingerprint is not supported on this browser. Please use Chrome or Safari on your phone.';
        }
        if (name === 'SecurityError' && !isSecureContextOk()) {
            return 'Fingerprint requires a secure connection (HTTPS or localhost). Please open the site via HTTPS.';
        }
        if (msg.includes('no fingerprint') || msg.includes('no biometrics') || msg.includes('biometric')) {
            return 'No fingerprint / Face ID found on this device. Please set one up first: Phone Settings → Security → Biometrics / Face ID, then try again.';
        }
        if (msg.includes('user verification') || msg.includes('not configured') || msg.includes('not enrolled') || msg.includes('no credentials available')) {
            return 'No fingerprint / Face ID is set up on this phone. Please add a fingerprint or Face ID in your phone Settings, then try again.';
        }
        if (msg.includes('no platform authenticator') || msg.includes('platform authenticator is not available') || msg.includes('authenticator not found')) {
            return 'No fingerprint sensor found on this device. This phone may not have fingerprint / Face ID hardware.';
        }
        if (name === 'InvalidStateError') {
            return 'This fingerprint is already registered on this device.';
        }
        if (name === 'NotAllowedError') {
            // Most common for cancel/timeout OR no biometrics enrolled
            if (msg.includes('timed out') || msg.includes('timeout')) {
                return 'Fingerprint timed out. Please try again and verify with your fingerprint / Face ID when prompted. If you have no fingerprint enrolled, please set one up in Settings.';
            }
            if (msg.includes('not allowed') || msg.includes('abort') || msg.includes('permission denied')) {
                // Heuristic: if platform check previously failed, it's likely no biometrics
                return 'Fingerprint was not allowed. If you have no fingerprint / Face ID set up, please add one in Phone Settings → Biometrics, then try again. Otherwise, please approve the prompt.';
            }
            return 'Fingerprint was cancelled or not allowed. If you have no fingerprint enrolled, please set one up in Settings and try again.';
        }
        if (rawMsg && rawMsg.length < 200) return rawMsg;
        return 'Fingerprint failed. Please try again.';
    }

    // ---- REGISTER (Portfolio) ----
    async function registerFingerprint() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        // 1) get options
        const optRes = await fetch('/webauthn/register/options', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
            credentials: 'same-origin',
        });
        const optData = await optRes.json();
        if (!optData.success) {
            throw new Error(optData.message || 'Unable to start registration.');
        }
        const o = optData.options;

        const publicKey = {
            challenge: b64urlToUint8Array(o.challenge),
            rp: o.rp,
            user: {
                id: b64urlToUint8Array(o.user.id),
                name: o.user.name,
                displayName: o.user.displayName,
            },
            pubKeyCredParams: o.pubKeyCredParams,
            authenticatorSelection: o.authenticatorSelection,
            timeout: o.timeout,
            attestation: o.attestation,
            excludeCredentials: o.excludeCredentials?.map(c => ({
                type: c.type,
                id: b64urlToUint8Array(c.id),
                transports: c.transports,
            })) || [],
        };

        // 2) create credential (triggers phone fingerprint/Face ID)
        let cred;
        try {
            cred = await navigator.credentials.create({ publicKey });
        } catch (e) {
            const friendly = mapWebAuthnError(e);
            const err = new Error(friendly);
            err.name = e.name || 'NotAllowedError';
            err.cause = e;
            throw err;
        }
        if (!cred) throw new Error('Fingerprint creation was cancelled. No fingerprint / Face ID may be set up on this device.');

        // 3) send to server
        const payload = {
            id: cred.id ? cred.id : arrayBufferToB64url(cred.rawId),
            rawId: arrayBufferToB64url(cred.rawId),
            type: cred.type,
            response: {
                clientDataJSON: arrayBufferToB64url(cred.response.clientDataJSON),
                attestationObject: cred.response.attestationObject ? arrayBufferToB64url(cred.response.attestationObject) : null,
            },
            device_name: navigator.userAgent.slice(0, 80),
        };

        const verifyRes = await fetch('/webauthn/register/verify', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });
        const verifyData = await verifyRes.json();
        if (!verifyData.success) {
            throw new Error(verifyData.message || 'Verification failed.');
        }
        // Bank-like: remember ID after first registration for next passwordless tap
        try {
            const toStore = verifyData.student_id || o.user.name;
            if (toStore) localStorage.setItem('webauthn_last_student_id', toStore);
        } catch (e) {}
        return verifyData;
    }

    // ---- LOGIN (org_auth) ----
    // B-mode: works both with ID (filtered) and without ID (discoverable/passwordless)
    // Bank-like: if no ID typed, check localStorage for last used ID and do filtered login behind the scenes
    async function loginWithFingerprint(studentId) {
        const trimmedId = (studentId || '').trim();
        let hasId = !!trimmedId;
        let effectiveId = trimmedId;
        let usedStoredId = false;
        if (!hasId) {
            try {
                const stored = localStorage.getItem('webauthn_last_student_id');
                if (stored && stored.trim()) {
                    effectiveId = stored.trim();
                    hasId = true;
                    usedStoredId = true;
                }
            } catch (e) {}
        }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        // 1) get options — with ID = filtered, without = discoverable
        // If we have effectiveId (typed or stored), server returns filtered allowCredentials; else discoverable []
        const body = hasId ? { student_id: effectiveId } : {};
        body['cf-turnstile-response'] = window.UniOrgTurnstile?.token('loginForm') || '';
        const optRes = await fetch('/webauthn/login/options', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        }).finally(() => window.UniOrgTurnstile?.reset('loginForm'));
        const optData = await optRes.json();
        if (!optData.success) {
            throw new Error(optData.message || 'Unable to start fingerprint login.');
        }
        const o = optData.options;

        const publicKey = {
            challenge: b64urlToUint8Array(o.challenge),
            timeout: o.timeout,
            rpId: o.rpId,
            allowCredentials: (o.allowCredentials || []).map(c => ({
                type: c.type,
                id: b64urlToUint8Array(c.id),
                transports: c.transports,
            })),
            userVerification: o.userVerification,
        };
        // For discoverable (no ID), allowCredentials will be empty — browser shows account picker / uses resident key

        let assertion;
        try {
            assertion = await navigator.credentials.get({ publicKey });
        } catch (e) {
            const rawMsg = (e && e.message ? e.message : '').toString().toLowerCase();
            // Discoverable (true no ID, no stored) with no resident credential -> browser throws "no credentials available"
            if (!hasId && e.name === 'NotAllowedError' && (rawMsg.includes('no credentials') || rawMsg.includes('no available') || rawMsg.includes('credential') || rawMsg.includes('not allowed'))) {
                const err = new Error('No passwordless fingerprint found on this device. This happens if your fingerprint was registered before passwordless was enabled. Please login by typing your ID + tapping fingerprint, then go to My Profile → Change Fingerprint once to enable login without ID.');
                err.name = e.name;
                err.cause = e;
                throw err;
            }
            if (usedStoredId && e.name === 'NotAllowedError') {
                try { localStorage.removeItem('webauthn_last_student_id'); } catch (err2) {}
                const err = new Error('Stored fingerprint not found on this device. Please type your ID and use fingerprint, or login with password and re-register fingerprint in My Profile.');
                err.name = e.name;
                err.cause = e;
                throw err;
            }
            const friendly = mapWebAuthnError(e);
            const err = new Error(friendly);
            err.name = e.name || 'NotAllowedError';
            err.cause = e;
            throw err;
        }
        if (!assertion) throw new Error('Fingerprint verification was cancelled. No fingerprint may be set up on this device.');

        const payload = {
            id: assertion.id,
            rawId: arrayBufferToB64url(assertion.rawId),
            type: assertion.type,
            response: {
                clientDataJSON: arrayBufferToB64url(assertion.response.clientDataJSON),
                authenticatorData: arrayBufferToB64url(assertion.response.authenticatorData),
                signature: arrayBufferToB64url(assertion.response.signature),
                userHandle: assertion.response.userHandle ? arrayBufferToB64url(assertion.response.userHandle) : null,
            },
            student_id: hasId ? effectiveId : '',
            // For discoverable (no ID), server will resolve via credential_id or userHandle; for bank-like stored ID we send effectiveId
        };

        const verifyRes = await fetch('/webauthn/login/verify', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });
        const verifyData = await verifyRes.json();
        if (!verifyData.success) {
            if (usedStoredId) {
                const low = (verifyData.message || '').toLowerCase();
                if (low.includes('no fingerprint') || low.includes('does not match') || low.includes('could not identify') || low.includes('not found')) {
                    try { localStorage.removeItem('webauthn_last_student_id'); } catch (e2) {}
                }
            }
            throw new Error(verifyData.message || verifyData.errors?.student_id?.[0] || 'Fingerprint login failed.');
        }
        // Bank-like: remember this ID for next passwordless tap (like banks)
        try {
            const toStore = verifyData.student_id || effectiveId || trimmedId;
            if (toStore) localStorage.setItem('webauthn_last_student_id', toStore);
        } catch (e2) {}
        return verifyData;
    }

    return {
        isPlatformAvailable,
        isMobile,
        isSecureContextOk,
        registerFingerprint,
        loginWithFingerprint,
        mapWebAuthnError,
        b64urlToUint8Array,
        arrayBufferToB64url,
    };
})();
