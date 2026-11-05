(function () {
    const widgets = new Map();

    function container(formId) {
        return document.getElementById(formId)?.querySelector('[data-turnstile]');
    }

    function mount(formId) {
        const element = container(formId);
        if (!element || !window.turnstile || widgets.has(formId)) return;

        const state = { id: null, token: '' };
        const status = element.querySelector('[data-turnstile-status]');
        widgets.set(formId, state);
        state.id = window.turnstile.render(element.querySelector('[data-turnstile-widget]'), {
            sitekey: element.dataset.sitekey,
            action: element.dataset.action,
            theme: 'light',
            size: element.clientWidth > 0 && element.clientWidth < 300 ? 'compact' : 'flexible',
            callback: (token) => {
                state.token = token;
                status.textContent = '';
            },
            'expired-callback': () => reset(formId),
            'error-callback': () => {
                state.token = '';
                status.textContent = 'Security verification could not load. Check your connection and try again.';
            },
        });
    }

    function token(formId) {
        return widgets.get(formId)?.token || '';
    }

    function requireToken(formId) {
        if (!container(formId)) return true;
        mount(formId);
        if (token(formId)) return true;
        showToast('Please complete the security verification before submitting.', 'error');
        return false;
    }

    function reset(formId) {
        const state = widgets.get(formId);
        if (!state || !window.turnstile) {
            mount(formId);
            return;
        }
        state.token = '';
        container(formId).querySelector('[data-turnstile-status]').textContent = 'Refreshing security verification…';
        window.turnstile.reset(state.id);
    }

    window.UniOrgTurnstile = { mount, token, requireToken, reset };
    window.initUniOrgTurnstile = function () {
        document.querySelectorAll('[data-turnstile]').forEach((element) => {
            if (!element.closest('.hidden')) mount(element.closest('form').id);
        });
    };
})();
