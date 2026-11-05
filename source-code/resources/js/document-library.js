const ROOT_SELECTOR = '[data-document-library-region]';
const RESULTS_SELECTOR = '#document-library-results';
const ERROR_SELECTOR = '[data-library-navigation-error]';
const DETAILS_SELECTOR = '[data-document-details]';
const LIVE_STATUS_SELECTOR = '#document-library-live-status';
const FOCUS_ERROR_SELECTOR = '#document-library-validation-summary, #document-library-retrieval-error, [data-library-navigation-error]';
const HIGHLIGHT_CLASSES = ['ring-2', 'ring-amber-500', 'bg-amber-50'];

function toUrl(value, base) {
    try {
        return new URL(value, base);
    } catch {
        return null;
    }
}

export function documentLibraryUrlSignature(value, base = window.location.href) {
    const url = toUrl(value, base);
    if (!url) {
        return null;
    }

    url.hash = '';
    return url.href;
}

function normalizedDocumentIds(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return [...new Set(value.map(String).filter((id) => /^\d+$/.test(id)))];
}

function highlightedIdFromUrl(value, base) {
    const url = toUrl(value, base);
    const id = url?.searchParams.get('highlight');
    return id && /^\d+$/.test(id) ? id : null;
}

export function isEligibleLibraryUrl(value, { baseUrl, origin, pathname }) {
    const url = toUrl(value, baseUrl);
    const usesHttp = url?.protocol === 'https:' || url?.protocol === 'http:';

    return Boolean(usesHttp && url.origin === origin && url.pathname === pathname);
}

export function buildGetFormUrl(form, submitter = null, baseUrl = window.location.href) {
    const action = submitter?.getAttribute('formaction')
        || form.getAttribute('action')
        || baseUrl;
    const url = new URL(action, baseUrl);
    let data;

    try {
        data = submitter ? new FormData(form, submitter) : new FormData(form);
    } catch {
        data = new FormData(form);
        if (submitter?.name) {
            data.append(submitter.name, submitter.value);
        }
    }

    const query = new URLSearchParams();
    for (const [name, value] of data.entries()) {
        query.append(name, typeof value === 'string' ? value : value.name);
    }

    url.search = query.toString();
    url.hash = '';

    return url;
}
export class DocumentLibraryNavigation {
    constructor({
        documentRef = document,
        windowRef = window,
        fetchRef = window.fetch.bind(window),
        retainedHighlightId = null,
    } = {}) {
        this.document = documentRef;
        this.window = windowRef;
        this.fetch = fetchRef;
        this.retainedHighlightId = retainedHighlightId;
        this.root = this.document.querySelector(ROOT_SELECTOR);
        this.controller = null;
        this.started = false;

        if (!this.root) {
            throw new Error('Document Library region was not found.');
        }

        const initialUrl = toUrl(
            this.root.dataset.libraryCanonicalUrl,
            this.window.location.href,
        ) || new URL(this.window.location.href);

        this.origin = initialUrl.origin;
        this.pathname = initialUrl.pathname;
        this.lastSuccessfulUrl = initialUrl.href;
        this.lastSuccessfulState = null;
        this.handleClick = this.handleClick.bind(this);
        this.handleSubmit = this.handleSubmit.bind(this);
        this.handlePopState = this.handlePopState.bind(this);
        this.handlePageShow = this.handlePageShow.bind(this);
        this.handleDetailsToggle = this.handleDetailsToggle.bind(this);
    }

    start() {
        if (this.started) {
            return this;
        }

        this.document.addEventListener('click', this.handleClick);
        this.document.addEventListener('submit', this.handleSubmit);
        this.document.addEventListener('toggle', this.handleDetailsToggle, true);
        this.window.addEventListener('popstate', this.handlePopState);
        this.window.addEventListener('pageshow', this.handlePageShow);
        this.started = true;

        const state = this.window.history.state;
        if (this.stateMatchesCurrentView(state)) {
            this.restoreViewState(state, { focus: false, restoreScroll: false });
        } else {
            this.applyDetailsState(this.root, this.expandedDocumentIds(this.root));
            // Retained highlights survive in-place re-renders; otherwise the
            // browser URL carries the opened document.
            const highlightedDocumentId = this.retainedHighlightId
                || highlightedIdFromUrl(this.window.location.href, this.window.location.href);
            this.applyHighlight(this.root, highlightedDocumentId, { focus: false });
            this.persistCurrentState();
        }
        this.lastSuccessfulState = this.captureViewState();

        return this;
    }

    stop() {
        if (!this.started) {
            return;
        }

        this.controller?.abort();
        this.document.removeEventListener('click', this.handleClick);
        this.document.removeEventListener('submit', this.handleSubmit);
        this.document.removeEventListener('toggle', this.handleDetailsToggle, true);
        this.window.removeEventListener('popstate', this.handlePopState);
        this.window.removeEventListener('pageshow', this.handlePageShow);
        this.started = false;
    }

    isLibraryDestination(value) {
        return isEligibleLibraryUrl(value, {
            baseUrl: this.window.location.href,
            origin: this.origin,
            pathname: this.pathname,
        });
    }

    currentCanonicalUrl(root = this.document.querySelector(ROOT_SELECTOR)) {
        return documentLibraryUrlSignature(
            root?.dataset.libraryCanonicalUrl || this.window.location.href,
            this.window.location.href,
        );
    }

    expandedDocumentIds(root = this.document.querySelector(ROOT_SELECTOR)) {
        if (!root) {
            return [];
        }

        return [...root.querySelectorAll(`${DETAILS_SELECTOR}[open]`)]
            .map((details) => details.closest('[data-document-id]')?.dataset.documentId)
            .filter(Boolean);
    }

    captureViewState(root = this.document.querySelector(ROOT_SELECTOR)) {
        return {
            urlSignature: this.currentCanonicalUrl(root),
            expandedDocumentIds: this.expandedDocumentIds(root),
            scrollY: Math.max(0, Number(this.window.scrollY) || 0),
            highlightedDocumentId: this.currentHighlightedDocumentId(root),
        };
    }

    currentHighlightedDocumentId(root = this.document.querySelector(ROOT_SELECTOR)) {
        return root?.querySelector('[data-library-highlighted][data-document-id]')?.dataset.documentId
            || highlightedIdFromUrl(this.window.location.href, this.window.location.href);
    }

    stateMatchesCurrentView(state, root = this.document.querySelector(ROOT_SELECTOR)) {
        return Boolean(state
            && state.urlSignature
            && state.urlSignature === this.currentCanonicalUrl(root));
    }

    persistCurrentState() {
        const state = this.captureViewState();
        if (!state.urlSignature) {
            return null;
        }

        this.window.history.replaceState(state, '', state.urlSignature);
        this.lastSuccessfulState = state;
        return state;
    }

    applyDetailsState(root, expandedIds) {
        const expanded = new Set(normalizedDocumentIds(expandedIds));
        root?.querySelectorAll(DETAILS_SELECTOR).forEach((details) => {
            const id = details.closest('[data-document-id]')?.dataset.documentId;
            details.open = Boolean(id && expanded.has(id));
            const summary = details.querySelector('summary');
            summary?.setAttribute('aria-expanded', details.open ? 'true' : 'false');
        });
    }

    findDocument(root, documentId) {
        if (!root || documentId === null || documentId === undefined) {
            return null;
        }

        return [...root.querySelectorAll('[data-document-id]')]
            .find((entry) => entry.dataset.documentId === String(documentId)) || null;
    }

    applyHighlight(root, documentId, { focus = false } = {}) {
        root?.querySelectorAll('[data-library-highlighted]').forEach((entry) => {
            entry.removeAttribute('data-library-highlighted');
            entry.classList.remove(...HIGHLIGHT_CLASSES);
            if (entry.dataset.libraryTemporaryTabindex === 'true') {
                entry.removeAttribute('tabindex');
                delete entry.dataset.libraryTemporaryTabindex;
            }
        });

        const entry = this.findDocument(root, documentId);
        if (!entry) {
            return null;
        }

        entry.setAttribute('data-library-highlighted', 'true');
        entry.classList.add(...HIGHLIGHT_CLASSES);
        if (!entry.hasAttribute('tabindex')) {
            entry.setAttribute('tabindex', '-1');
            entry.dataset.libraryTemporaryTabindex = 'true';
        }
        if (focus) {
            entry.focus({ preventScroll: true });
        }

        return entry;
    }

    scrollHighlightedIntoView(root) {
        const entry = root?.querySelector('[data-library-highlighted]');
        entry?.scrollIntoView({ block: 'nearest' });
    }

    focusResultTarget(root, highlightedDocumentId = null) {
        const error = root?.querySelector(FOCUS_ERROR_SELECTOR);
        if (error) {
            if (!error.hasAttribute('tabindex')) {
                error.setAttribute('tabindex', '-1');
            }
            error.focus({ preventScroll: true });
            return error;
        }

        const highlighted = this.applyHighlight(root, highlightedDocumentId, { focus: true });
        if (highlighted) {
            return highlighted;
        }

        const results = root?.querySelector(RESULTS_SELECTOR);
        results?.focus({ preventScroll: true });
        return results || null;
    }

    restoreViewState(state, { focus = true, restoreScroll = true } = {}) {
        const root = this.document.querySelector(ROOT_SELECTOR);
        if (!this.stateMatchesCurrentView(state, root)) {
            return false;
        }

        this.applyDetailsState(root, state.expandedDocumentIds);
        const highlightedDocumentId = /^\d+$/.test(String(state.highlightedDocumentId || ''))
            ? String(state.highlightedDocumentId)
            : null;
        this.applyHighlight(root, highlightedDocumentId, { focus: false });
        if (focus && (highlightedDocumentId || root.querySelector(FOCUS_ERROR_SELECTOR))) {
            this.focusResultTarget(root, highlightedDocumentId);
        }
        if (restoreScroll) {
            const scrollY = Math.max(0, Number(state.scrollY) || 0);
            this.window.requestAnimationFrame(() => this.window.scrollTo(0, scrollY));
        }

        this.lastSuccessfulState = {
            urlSignature: state.urlSignature,
            expandedDocumentIds: normalizedDocumentIds(state.expandedDocumentIds),
            scrollY: Math.max(0, Number(state.scrollY) || 0),
            highlightedDocumentId,
        };
        return true;
    }

    announceResults(root) {
        const status = root?.querySelector(LIVE_STATUS_SELECTOR);
        if (!status) {
            return;
        }

        const message = status.textContent.replace(/\s+/g, ' ').trim();
        status.textContent = '';
        this.window.setTimeout(() => {
            if (status.isConnected) {
                status.textContent = message;
            }
        }, 0);
    }

    buildSkeleton() {
        const skeleton = this.document.createElement('div');
        skeleton.setAttribute('data-library-skeleton', '');
        skeleton.setAttribute('aria-hidden', 'true');
        skeleton.className = 'min-w-0 space-y-3';
        skeleton.innerHTML = `
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div class="min-w-0">
                    <div class="document-library-skeleton-block h-5 w-24"></div>
                    <div class="document-library-skeleton-block mt-1 h-3 w-64"></div>
                </div>
                <div class="document-library-skeleton-block h-4 w-20"></div>
            </div>
            <div class="grid min-w-0 grid-cols-1 gap-3">
                <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="document-library-skeleton-block h-11 w-11 shrink-0 rounded-lg"></div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="document-library-skeleton-block h-4 w-2/5"></div>
                        <div class="document-library-skeleton-block h-3 w-1/4"></div>
                    </div>
                    <div class="document-library-skeleton-block h-6 w-20 rounded-full"></div>
                </div>
                <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="document-library-skeleton-block h-11 w-11 shrink-0 rounded-lg"></div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="document-library-skeleton-block h-4 w-1/2"></div>
                        <div class="document-library-skeleton-block h-3 w-1/3"></div>
                    </div>
                    <div class="document-library-skeleton-block h-6 w-16 rounded-full"></div>
                </div>
                <div class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="document-library-skeleton-block h-11 w-11 shrink-0 rounded-lg"></div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="document-library-skeleton-block h-4 w-2/3"></div>
                        <div class="document-library-skeleton-block h-3 w-1/4"></div>
                    </div>
                    <div class="document-library-skeleton-block h-6 w-24 rounded-full"></div>
                </div>
            </div>`;
        return skeleton;
    }

    showSkeleton() {
        const root = this.document.querySelector(ROOT_SELECTOR);
        if (!root || root.querySelector('[data-library-skeleton]')) {
            return;
        }

        const skeleton = this.buildSkeleton();
        const results = root.querySelector(RESULTS_SELECTOR);
        root.insertBefore(skeleton, results);
        if (results) {
            results.hidden = true;
        }
        root.setAttribute('aria-busy', 'true');
    }

    hideSkeleton() {
        const root = this.document.querySelector(ROOT_SELECTOR);
        if (!root) {
            return;
        }

        root.querySelector('[data-library-skeleton]')?.remove();
        root.querySelector(RESULTS_SELECTOR)?.removeAttribute('hidden');
        root.removeAttribute('aria-busy');
    }

    handleDetailsToggle(event) {
        const details = event.target;
        if (!details.matches?.(DETAILS_SELECTOR)
            || !this.document.querySelector(ROOT_SELECTOR)?.contains(details)) {
            return;
        }

        details.querySelector('summary')?.setAttribute(
            'aria-expanded',
            details.open ? 'true' : 'false',
        );
        this.persistCurrentState();
    }

    handlePageShow() {
        if (this.stateMatchesCurrentView(this.window.history.state)) {
            this.restoreViewState(this.window.history.state);
        }
    }

    handleClick(event) {
        if (event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey) {
            return;
        }

        const link = event.target.closest?.('a[href]');
        const root = this.document.querySelector(ROOT_SELECTOR);

        if (!link || !root?.contains(link)
            || link.hasAttribute('download')
            || link.hasAttribute('data-no-library-enhancement')) {
            return;
        }

        if (link.hasAttribute('data-library-review-action')) {
            this.persistCurrentState();
        }

        if (link.target && link.target.toLowerCase() !== '_self') {
            return;
        }

        const rawHref = link.getAttribute('href');
        const destination = toUrl(rawHref, this.window.location.href);
        const isSameDocumentFragment = destination?.hash
            && destination.pathname === this.window.location.pathname
            && destination.search === this.window.location.search;

        if (!rawHref || isSameDocumentFragment || !this.isLibraryDestination(destination?.href)) {
            return;
        }

        event.preventDefault();
        void this.navigate(destination, { historyMode: 'push', trigger: link });
    }

    handleSubmit(event) {
        const form = event.target;
        const root = this.document.querySelector(ROOT_SELECTOR);

        if (!(form instanceof this.window.HTMLFormElement) || !root?.contains(form)) {
            return;
        }

        const submitter = event.submitter;
        const method = (submitter?.getAttribute('formmethod')
            || form.getAttribute('method')
            || 'get').toLowerCase();
        const target = submitter?.getAttribute('formtarget')
            || form.getAttribute('target')
            || '_self';

        if (event.defaultPrevented
            || method !== 'get'
            || target.toLowerCase() !== '_self'
            || form.hasAttribute('data-no-library-enhancement')) {
            return;
        }

        const destination = buildGetFormUrl(form, submitter, this.window.location.href);
        if (!this.isLibraryDestination(destination.href)) {
            return;
        }

        event.preventDefault();
        void this.navigate(destination, { historyMode: 'push', trigger: form });
    }

    handlePopState(event) {
        const destination = new URL(this.window.location.href);
        if (!this.isLibraryDestination(destination.href)) {
            // The OSAS shell restores every other page on back/forward.
            return;
        }

        const previousViewState = this.captureViewState();
        void this.navigate(destination, {
            historyMode: 'replace',
            trigger: null,
            restorationState: event.state,
            failureState: previousViewState,
        });
    }

    async navigate(value, {
        historyMode = 'push',
        trigger = null,
        restorationState = null,
        failureState = null,
    } = {}) {
        const destination = toUrl(value, this.window.location.href);
        if (!destination || !this.isLibraryDestination(destination.href)) {
            return false;
        }

        if (historyMode === 'push') {
            this.persistCurrentState();
        }
        const recoverableState = failureState || this.captureViewState();
        const recoverableUrl = recoverableState.urlSignature || this.lastSuccessfulUrl;

        this.controller?.abort();
        const controller = new this.window.AbortController();
        this.controller = controller;
        this.showSkeleton();

        try {
            // The fragment layout skips the OSAS shell, so swapping only the
            // library region keeps back/forward fast.
            const fetchUrl = new URL(destination.href);
            fetchUrl.searchParams.set('fragment', '1');
            const response = await this.fetch(fetchUrl.href, {
                method: 'GET',
                credentials: 'same-origin',
                redirect: 'follow',
                signal: controller.signal,
                headers: {
                    Accept: 'text/html',
                    'X-Document-Library-Enhancement': 'true',
                },
            });

            if (controller !== this.controller || controller.signal.aborted) {
                return false;
            }
            if (!response.ok) {
                throw new Error('The Document Library request was not successful.');
            }

            const markup = await response.text();
            if (controller !== this.controller || controller.signal.aborted) {
                return false;
            }

            const parsed = new this.window.DOMParser().parseFromString(markup, 'text/html');
            const receivedRoot = parsed.querySelector(ROOT_SELECTOR);
            const currentRoot = this.document.querySelector(ROOT_SELECTOR);
            if (!receivedRoot || !currentRoot) {
                throw new Error('The Document Library response was incomplete.');
            }

            const responseUrl = response.url || destination.href;
            const canonicalUrl = toUrl(
                receivedRoot.dataset.libraryCanonicalUrl || responseUrl,
                responseUrl,
            );
            if (!canonicalUrl || !this.isLibraryDestination(canonicalUrl.href)) {
                throw new Error('The Document Library response was invalid.');
            }

            canonicalUrl.hash = '';
            const importedRoot = this.document.importNode(receivedRoot, true);
            currentRoot.replaceWith(importedRoot);
            this.root = importedRoot;

            const signature = canonicalUrl.href;
            const canRestore = restorationState?.urlSignature === signature;
            // Folder tokens are re-encrypted on every server render, so the
            // canonical signature can differ even when restoring the same entry.
            // Fall back to the entry's own stored highlight in that case.
            const highlightedDocumentId = canRestore
                ? restorationState.highlightedDocumentId
                : (highlightedIdFromUrl(destination.href, destination.href)
                    || restorationState?.highlightedDocumentId
                    || null);
            const destinationState = {
                urlSignature: signature,
                expandedDocumentIds: canRestore
                    ? normalizedDocumentIds(restorationState.expandedDocumentIds)
                    : this.expandedDocumentIds(importedRoot),
                scrollY: canRestore ? Math.max(0, Number(restorationState.scrollY) || 0) : 0,
                highlightedDocumentId: /^\d+$/.test(String(highlightedDocumentId || ''))
                    ? String(highlightedDocumentId)
                    : null,
            };

            this.updateHistory(historyMode, signature, destinationState);
            this.lastSuccessfulUrl = signature;
            this.lastSuccessfulState = destinationState;
            this.controller = null;

            this.applyDetailsState(importedRoot, destinationState.expandedDocumentIds);
            this.applyHighlight(importedRoot, destinationState.highlightedDocumentId, { focus: false });
            this.focusResultTarget(importedRoot, destinationState.highlightedDocumentId);
            if (canRestore) {
                this.window.requestAnimationFrame(() => {
                    this.window.scrollTo(0, destinationState.scrollY);
                });
            } else if (destinationState.highlightedDocumentId !== null) {
                this.window.requestAnimationFrame(() => {
                    this.scrollHighlightedIntoView(importedRoot);
                });
            }
            this.announceResults(importedRoot);

            this.dispatch('document-library:navigated', {
                url: signature,
                root: importedRoot,
                trigger,
                restored: canRestore,
            });

            return true;
        } catch (error) {
            if (controller.signal.aborted || controller !== this.controller) {
                return false;
            }

            this.controller = null;
            this.hideSkeleton();
            if (historyMode === 'replace'
                && this.window.location.href !== recoverableUrl) {
                this.window.history.replaceState(
                    recoverableState,
                    '',
                    recoverableUrl,
                );
            }

            this.showFailure(destination.href);
            this.lastSuccessfulState = recoverableState;
            this.dispatch('document-library:navigation-failed', {
                retryUrl: destination.href,
                trigger,
            });

            return false;
        }
    }

    updateHistory(mode, url, state) {
        if (mode === 'push') {
            this.window.history.pushState(state, '', url);
        } else if (mode === 'replace') {
            this.window.history.replaceState(state, '', url);
        }
    }

    showFailure(retryUrl) {
        const root = this.document.querySelector(ROOT_SELECTOR);
        if (!root) {
            return;
        }

        root.querySelector(ERROR_SELECTOR)?.remove();
        const error = this.document.createElement('div');
        error.id = 'document-library-navigation-error';
        error.setAttribute('data-library-navigation-error', '');
        error.setAttribute('role', 'alert');
        error.setAttribute('tabindex', '-1');
        error.className = 'scroll-mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-red-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2';

        const heading = this.document.createElement('h2');
        heading.className = 'font-black';
        heading.textContent = 'Document Library navigation failed';

        const message = this.document.createElement('p');
        message.className = 'mt-1 text-sm';
        message.textContent = 'That view could not be loaded. Your last successful library view was kept.';

        const retry = this.document.createElement('a');
        retry.href = retryUrl;
        retry.setAttribute('data-library-retry', '');
        retry.className = 'mt-3 inline-flex min-h-10 items-center rounded-lg bg-[#26488C] px-4 py-2 text-sm font-bold text-white';
        retry.textContent = 'Retry';

        error.append(heading, message, retry);
        const results = root.querySelector(RESULTS_SELECTOR);
        if (results) {
            const describedBy = new Set((results.getAttribute('aria-describedby') || '')
                .split(/\s+/)
                .filter(Boolean));
            describedBy.add(error.id);
            results.setAttribute('aria-describedby', [...describedBy].join(' '));
            results.before(error);
        } else {
            root.prepend(error);
        }
        error.focus({ preventScroll: true });
    }

    dispatch(name, detail) {
        this.document.dispatchEvent(new this.window.CustomEvent(name, { detail }));
    }
}

const PREVIEW_TRIGGER_SELECTOR = '[data-library-preview-trigger]';

const PREVIEW_TYPES = {
    pdf: 'pdf',
    jpg: 'image',
    jpeg: 'image',
    png: 'image',
    gif: 'image',
    webp: 'image',
};

function previewTypeFor(filename) {
    const extension = (filename || '').split('.').pop()?.toLowerCase() || '';
    return PREVIEW_TYPES[extension] || 'unsupported';
}

export class DocumentLibraryPreview {
    constructor({ documentRef = document, windowRef = window } = {}) {
        this.document = documentRef;
        this.window = windowRef;
        this.active = null;
        this.lastTrigger = null;
        this.previousBodyOverflow = null;
        this.handleClick = this.handleClick.bind(this);
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleFocus = this.handleFocus.bind(this);
        this.open = this.open.bind(this);
        this.close = this.close.bind(this);
    }

    start() {
        this.document.addEventListener('click', this.handleClick);
        this.document.addEventListener('keydown', this.handleKeydown);
        this.document.addEventListener('focusin', this.handleFocus, true);
        return this;
    }

    stop() {
        this.document.removeEventListener('click', this.handleClick);
        this.document.removeEventListener('keydown', this.handleKeydown);
        this.document.removeEventListener('focusin', this.handleFocus, true);
        this.close();
    }

    handleClick(event) {
        const trigger = event.target.closest?.(PREVIEW_TRIGGER_SELECTOR);
        if (!trigger) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        this.open(trigger);
    }

    handleKeydown(event) {
        if (!this.active) {
            return;
        }
        if (event.key === 'Escape') {
            if (event.target.closest?.(PREVIEW_TRIGGER_SELECTOR)) {
                event.stopPropagation();
            }
            this.close();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = this.focusableElements();
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (event.target === first || !this.active.contains(event.target))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (event.target === last || !this.active.contains(event.target))) {
            event.preventDefault();
            first.focus();
        }
    }

    handleFocus(event) {
        if (!this.active || this.active.contains(event.target)) {
            return;
        }
        const focusable = this.focusableElements();
        if (focusable.length === 0) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        (event.target === last ? first : last).focus();
    }

    focusableElements() {
        if (!this.active) {
            return [];
        }
        return [...this.active.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), iframe',
        )].filter((element) => element.offsetParent !== null);
    }

    open(trigger) {
        const url = trigger.getAttribute('data-preview-url');
        const filename = trigger.getAttribute('data-preview-filename') || 'Document';
        this.render({ url, filename });
        this.lastTrigger = trigger;
    }

    close() {
        if (this.active) {
            this.active.remove();
            this.active = null;
            if (this.previousBodyOverflow !== null) {
                this.document.body.style.overflow = this.previousBodyOverflow;
                this.previousBodyOverflow = null;
            }
        }
        this.lastTrigger?.focus?.();
        this.lastTrigger = null;
    }

    buildPreview(url, filename) {
        const body = this.document.createElement('div');
        body.className = 'bg-gray-100 rounded-lg p-4';
        body.style.height = '600px';
        const type = previewTypeFor(filename);

        if (type === 'pdf') {
            const iframe = this.document.createElement('iframe');
            iframe.className = 'w-full h-full rounded';
            iframe.style.border = 'none';
            iframe.src = `${url}#view=FitH`;
            iframe.setAttribute('title', `Preview of ${filename}`);
            body.append(iframe);
            return body;
        }

        if (type === 'image') {
            const image = this.document.createElement('img');
            image.className = 'w-full h-full object-contain rounded';
            image.src = url;
            image.alt = `Preview of ${filename}`;
            body.append(image);
            return body;
        }

        const fallback = this.document.createElement('div');
        fallback.className = 'w-full h-full flex flex-col items-center justify-center text-center gap-3';
        const icon = this.document.createElement('i');
        icon.className = 'ph ph-file-x text-5xl text-gray-400';
        const heading = this.document.createElement('p');
        heading.className = 'text-lg font-bold text-gray-800';
        heading.textContent = 'Preview not available';
        const message = this.document.createElement('p');
        message.className = 'text-sm text-gray-500 max-w-md';
        message.textContent = 'This file type cannot be previewed in the browser. Click the icon to open the document in a new tab.';
        fallback.append(icon, heading, message);
        body.append(fallback);
        return body;
    }

    render({ url, filename }) {
        this.close();

        const root = this.document.createElement('div');
        root.setAttribute('data-document-library-preview', '');
        root.className = 'documents-modal fixed inset-0 z-50 overflow-y-auto';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'document-library-preview-heading');

        const center = this.document.createElement('div');
        center.className = 'flex items-center justify-center min-h-screen px-4';

        const backdrop = this.document.createElement('div');
        backdrop.className = 'documents-modal__backdrop fixed inset-0';
        backdrop.addEventListener('click', () => this.close());

        const panel = this.document.createElement('div');
        panel.className = 'relative bg-white rounded-2xl shadow-2xl max-w-4xl w-full p-6 transform transition-all';

        const closeBtn = this.document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'absolute top-4 right-4 text-gray-400 hover:text-gray-600';
        closeBtn.setAttribute('aria-label', 'Close preview');
        const closeIcon = this.document.createElement('i');
        closeIcon.className = 'ph ph-x text-2xl';
        closeBtn.append(closeIcon);
        closeBtn.addEventListener('click', () => this.close());

        const heading = this.document.createElement('h2');
        heading.id = 'document-library-preview-heading';
        heading.className = 'pr-10 text-xl font-bold text-gray-800 mb-4';
        heading.textContent = 'Document Preview';

        panel.append(closeBtn, heading, this.buildPreview(url, filename));
        center.append(backdrop, panel);
        root.append(center);
        this.document.body.appendChild(root);
        this.previousBodyOverflow = this.document.body.style.overflow || '';
        this.document.body.style.overflow = 'hidden';
        this.active = root;
        this.active.querySelector('button')?.focus?.();
    }
}

let navigationInstance = null;
let previewInstance = null;

export function initDocumentLibraryPreview(options = {}) {
    const documentRef = options.documentRef || document;
    if (!documentRef.querySelector(ROOT_SELECTOR)) {
        return null;
    }

    previewInstance?.stop();
    previewInstance = new DocumentLibraryPreview(options).start();
    return previewInstance;
}

export function initDocumentLibraryNavigation(options = {}) {
    const documentRef = options.documentRef || document;
    if (!documentRef.querySelector(ROOT_SELECTOR)) {
        return null;
    }

    navigationInstance?.stop();
    navigationInstance = new DocumentLibraryNavigation(options).start();
    return navigationInstance;
}

function reinitializeDocumentLibrary() {
    // Keep an existing highlight visible across in-place re-renders.
    const retainedHighlightId = document.querySelector(
        '[data-document-library-region] [data-library-highlighted]',
    )?.getAttribute('data-document-id') || null;
    initDocumentLibraryNavigation({ retainedHighlightId });
    initDocumentLibraryPreview();
}

if (typeof document !== 'undefined' && document.querySelector(ROOT_SELECTOR)) {
    reinitializeDocumentLibrary();
    // The OSAS shell swaps page content in place; rebind the library so its
    // focus and back/forward features keep working after a render.
    // navigation-ready fires before the shell pushes the new history entry,
    // so defer until the browser URL reflects the rendered page.
    window.addEventListener('osas:navigation-ready', () => {
        window.setTimeout(reinitializeDocumentLibrary, 0);
    });
}
