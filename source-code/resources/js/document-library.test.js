import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    DocumentLibraryNavigation,
    DocumentLibraryPreview,
    buildGetFormUrl,
    documentLibraryUrlSignature,
} from './document-library.js';

const baseUrl = `${window.location.origin}/osas/smart-search`;
let navigation;

function rootMarkup(label, canonicalUrl = baseUrl, content = '') {
    return `<!doctype html><html><body>
        <div data-document-library-region data-library-canonical-url="${canonicalUrl}">
            <p data-view>${label}</p>
            <section id="document-library-results" tabindex="-1">${content}</section>
            <p id="document-library-live-status" role="status" aria-live="polite">25 documents found. Showing 1 through 25.</p>
        </div>
    </body></html>`;
}

function documentEntry(id, { open = false } = {}) {
    return `<article id="document-${id}" data-document-id="${id}">
        <p id="document-${id}-filename">Document ${id}</p>
        <a data-library-review-action data-full-case href="${window.location.origin}/osas/apply-org/${id}">Full Case</a>
        <details data-document-details ${open ? 'open' : ''}>
            <summary aria-controls="document-details-${id}" aria-expanded="${open}">Details</summary>
            <div id="document-details-${id}">Generated details</div>
        </details>
    </article>`;
}

function response(markup, { ok = true, url = baseUrl } = {}) {
    return {
        ok,
        url,
        text: vi.fn().mockResolvedValue(markup),
    };
}

function deferred() {
    let resolve;
    const promise = new Promise((done) => {
        resolve = done;
    });

    return { promise, resolve };
}

beforeEach(() => {
    window.history.replaceState(null, '', baseUrl);
    document.body.innerHTML = `
        <div data-outside>Persistent layout</div>
        <div data-document-library-region data-library-canonical-url="${baseUrl}">
            <a data-page href="${baseUrl}?page=2">Next</a>
            <p data-view>Original view</p>
            <section id="document-library-results"></section>
        </div>`;
});

afterEach(() => {
    navigation?.stop();
    navigation = null;
    vi.restoreAllMocks();
    document.body.innerHTML = '';
});

describe('DocumentLibraryNavigation', () => {
    it('replaces only the library region and pushes the canonical URL after success', async () => {
        const pending = deferred();
        const fetchRef = vi.fn(() => pending.promise);
        const pushState = vi.spyOn(window.history, 'pushState');
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();

        const link = document.querySelector('[data-page]');
        const click = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        link.dispatchEvent(click);

        expect(click.defaultPrevented).toBe(true);
        expect(pushState).not.toHaveBeenCalled();
        expect(document.querySelector('[data-view]').textContent).toBe('Original view');

        const canonicalUrl = `${baseUrl}?page=2`;
        pending.resolve(response(rootMarkup('Second page', canonicalUrl), { url: canonicalUrl }));

        await vi.waitFor(() => {
            expect(document.querySelector('[data-view]').textContent).toBe('Second page');
        });
        expect(document.querySelector('[data-outside]').textContent).toBe('Persistent layout');
        expect(pushState).toHaveBeenCalledOnce();
        expect(pushState).toHaveBeenCalledWith(
            expect.objectContaining({
                urlSignature: canonicalUrl,
                expandedDocumentIds: [],
                scrollY: 0,
            }),
            '',
            canonicalUrl,
        );
    });
    it('keeps the successful view and URL while exposing an exact retry after failure', async () => {
        const fetchRef = vi.fn().mockResolvedValue(response('', { ok: false }));
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();
        const failedUrl = `${baseUrl}?folder=unavailable&page=3`;

        const succeeded = await navigation.navigate(failedUrl);

        expect(succeeded).toBe(false);
        expect(window.location.href).toBe(baseUrl);
        expect(document.querySelector('[data-view]').textContent).toBe('Original view');
        expect(document.querySelector('[data-outside]').textContent).toBe('Persistent layout');
        expect(document.querySelector('[data-library-navigation-error]')).not.toBeNull();
        expect(document.querySelector('[data-library-retry]').href).toBe(failedUrl);
    });

    it('aborts stale requests and allows only the latest response to update the page', async () => {
        const first = deferred();
        const second = deferred();
        const signals = [];
        const fetchRef = vi.fn((url, options) => {
            signals.push(options.signal);
            return url.endsWith('page=1') ? first.promise : second.promise;
        });
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();

        const firstNavigation = navigation.navigate(`${baseUrl}?page=1`);
        const secondNavigation = navigation.navigate(`${baseUrl}?page=2`);
        expect(signals[0].aborted).toBe(true);

        const secondUrl = `${baseUrl}?page=2`;
        second.resolve(response(rootMarkup('Latest view', secondUrl), { url: secondUrl }));
        await expect(secondNavigation).resolves.toBe(true);

        const firstUrl = `${baseUrl}?page=1`;
        first.resolve(response(rootMarkup('Stale view', firstUrl), { url: firstUrl }));
        await expect(firstNavigation).resolves.toBe(false);

        expect(document.querySelector('[data-view]').textContent).toBe('Latest view');
        expect(window.location.href).toBe(secondUrl);
    });

    it('enhances GET forms canonically but leaves non-library links untouched', () => {
        const root = document.querySelector('[data-document-library-region]');
        root.insertAdjacentHTML('beforeend', `
            <form method="GET" action="${baseUrl}?discarded=yes">
                <input name="q" value="annual report">
                <select name="status[]" multiple>
                    <option value="Pending" selected>Pending</option>
                    <option value="Approved" selected>Approved</option>
                </select>
            </form>
            <a data-full-case href="${window.location.origin}/osas/apply-org/42">Full Case</a>`);

        const formUrl = buildGetFormUrl(root.querySelector('form'));
        expect(formUrl.href).toBe(`${baseUrl}?q=annual+report&status%5B%5D=Pending&status%5B%5D=Approved`);

        const fetchRef = vi.fn();
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();
        const fullCase = root.querySelector('[data-full-case]');
        const preventDefault = vi.fn();
        navigation.handleClick({
            defaultPrevented: false,
            button: 0,
            metaKey: false,
            ctrlKey: false,
            shiftKey: false,
            altKey: false,
            target: fullCase,
            preventDefault,
        });

        expect(preventDefault).not.toHaveBeenCalled();
        expect(fetchRef).not.toHaveBeenCalled();
    });

    it('stores expanded details, scroll, and query state before a review action', () => {
        document.body.innerHTML = rootMarkup(
            'Document view',
            baseUrl,
            documentEntry(41),
        ).match(/<body>([\s\S]*)<\/body>/)[1];
        const scrollY = vi.spyOn(window, 'scrollY', 'get').mockReturnValue(480);
        navigation = new DocumentLibraryNavigation({ fetchRef: vi.fn() }).start();
        const details = document.querySelector('[data-document-details]');
        details.open = true;
        details.dispatchEvent(new Event('toggle'));

        const link = document.querySelector('[data-full-case]');
        navigation.handleClick({
            defaultPrevented: false,
            button: 0,
            metaKey: false,
            ctrlKey: false,
            shiftKey: false,
            altKey: false,
            target: link,
        });

        expect(document.querySelector('summary').getAttribute('aria-expanded')).toBe('true');
        expect(window.history.state).toEqual({
            urlSignature: baseUrl,
            expandedDocumentIds: ['41'],
            scrollY: 480,
            highlightedDocumentId: null,
        });
        scrollY.mockRestore();
    });

    it('navigates to an Open Folder target, highlights it, and announces the results', async () => {
        const targetUrl = `${baseUrl}?folder=signed&year_folder=2025-2026&highlight=41`;
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
            callback(0);
            return 1;
        });
        const fetchRef = vi.fn().mockResolvedValue(response(
            rootMarkup('Year folder', targetUrl, documentEntry(41)),
            { url: targetUrl },
        ));
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();

        await expect(navigation.navigate(targetUrl)).resolves.toBe(true);

        const entry = document.querySelector('[data-document-id="41"]');
        expect(entry.dataset.libraryHighlighted).toBe('true');
        expect(entry.classList.contains('ring-amber-500')).toBe(true);
        expect(window.history.state.highlightedDocumentId).toBe('41');
        expect(scrollIntoView).toHaveBeenCalled();
        await vi.waitFor(() => {
            expect(document.querySelector('#document-library-live-status').textContent)
                .toContain('25 documents found');
        });
    });

    it('highlights the requested document from the browser URL on initial load', () => {
        const targetUrl = `${baseUrl}?folder=signed&year_folder=2025-2026&highlight=41`;
        window.history.replaceState(null, '', targetUrl);
        document.body.innerHTML = rootMarkup(
            'Year folder',
            `${baseUrl}?folder=signed&year_folder=2025-2026`,
            documentEntry(41),
        ).match(/<body>([\s\S]*)<\/body>/)[1];
        navigation = new DocumentLibraryNavigation({ fetchRef: vi.fn() }).start();

        const entry = document.querySelector('[data-document-id="41"]');
        expect(entry.dataset.libraryHighlighted).toBe('true');
        expect(entry.classList.contains('ring-amber-500')).toBe(true);
        expect(window.history.state.highlightedDocumentId).toBe('41');
    });

    it('leaves non-library back/forward destinations to the OSAS shell without reloading', () => {
        navigation = new DocumentLibraryNavigation({ fetchRef: vi.fn() }).start();
        window.history.replaceState(null, '', `${window.location.origin}/osas/dashboard`);
        expect(() => {
            window.dispatchEvent(new PopStateEvent('popstate'));
        }).not.toThrow();
        expect(navigation.fetch).not.toHaveBeenCalled();
    });

    it('restores only matching per-history-entry details, highlight, and scroll on pageshow', () => {
        document.body.innerHTML = rootMarkup(
            'Restored view',
            baseUrl,
            `${documentEntry(41)}${documentEntry(42)}`,
        ).match(/<body>([\s\S]*)<\/body>/)[1];
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
            callback(0);
            return 1;
        });
        navigation = new DocumentLibraryNavigation({ fetchRef: vi.fn() }).start();
        window.history.replaceState({
            urlSignature: baseUrl,
            expandedDocumentIds: ['42'],
            scrollY: 725,
            highlightedDocumentId: '42',
        }, '', baseUrl);

        window.dispatchEvent(new Event('pageshow'));

        expect(document.querySelector('[data-document-id="41"] details').open).toBe(false);
        expect(document.querySelector('[data-document-id="42"] details').open).toBe(true);
        expect(document.querySelector('[data-document-id="42"] summary').getAttribute('aria-expanded')).toBe('true');
        expect(document.querySelector('[data-document-id="42"]').dataset.libraryHighlighted).toBe('true');
        expect(document.activeElement).toBe(document.querySelector('[data-document-id="42"]'));
        expect(scrollTo).toHaveBeenCalledWith(0, 725);
    });

    it('focuses and associates an enhanced retrieval error with the retained results', async () => {
        const fetchRef = vi.fn().mockResolvedValue(response('', { ok: false }));
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();

        await navigation.navigate(`${baseUrl}?page=9`);

        const error = document.querySelector('[data-library-navigation-error]');
        expect(document.activeElement).toBe(error);
        expect(error.getAttribute('tabindex')).toBe('-1');
        expect(document.querySelector('#document-library-results').getAttribute('aria-describedby'))
            .toContain('document-library-navigation-error');
    });

    it('shows a matching skeleton while the next view loads and removes it after success', async () => {
        const pending = deferred();
        const targetUrl = `${baseUrl}?folder=signed`;
        const fetchRef = vi.fn(() => pending.promise);
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();

        const navigationPromise = navigation.navigate(targetUrl);

        const skeleton = document.querySelector('[data-library-skeleton]');
        expect(skeleton).not.toBeNull();
        expect(skeleton.querySelectorAll('.document-library-skeleton-block').length).toBeGreaterThan(0);
        expect(document.querySelector('#document-library-results').hidden).toBe(true);
        expect(document.querySelector('[data-document-library-region]').getAttribute('aria-busy')).toBe('true');
        expect(document.querySelector('[data-view]').textContent).toBe('Original view');

        pending.resolve(response(rootMarkup('Signed folder', targetUrl), { url: targetUrl }));
        await expect(navigationPromise).resolves.toBe(true);

        expect(document.querySelector('[data-library-skeleton]')).toBeNull();
        expect(document.querySelector('[data-view]').textContent).toBe('Signed folder');
    });

    it('removes the skeleton and restores the previous results after failure', async () => {
        const fetchRef = vi.fn().mockResolvedValue(response('', { ok: false }));
        navigation = new DocumentLibraryNavigation({ fetchRef }).start();
        const failedUrl = `${baseUrl}?folder=unavailable`;

        const succeeded = await navigation.navigate(failedUrl);

        expect(succeeded).toBe(false);
        expect(document.querySelector('[data-library-skeleton]')).toBeNull();
        expect(document.querySelector('#document-library-results').hidden).toBe(false);
        expect(document.querySelector('[data-document-library-region]').hasAttribute('aria-busy')).toBe(false);
        expect(document.querySelector('[data-view]').textContent).toBe('Original view');
        expect(document.querySelector('[data-library-navigation-error]')).not.toBeNull();
    });
});

describe('documentLibraryUrlSignature', () => {
    it('uses the complete canonical URL while excluding fragments', () => {
        expect(documentLibraryUrlSignature(`${baseUrl}?page=2#document-41`))
            .toBe(`${baseUrl}?page=2`);
        expect(documentLibraryUrlSignature('not a valid URL', 'not a base')).toBeNull();
    });
});

describe('DocumentLibraryPreview', () => {
    let preview;

    function trigger(filename, url = 'https://example.test/source') {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('data-library-preview-trigger', '');
        button.setAttribute('data-preview-url', url);
        button.setAttribute('data-preview-filename', filename);
        button.textContent = 'View Document';
        document.body.appendChild(button);
        return button;
    }

    beforeEach(() => {
        preview = new DocumentLibraryPreview().start();
    });

    afterEach(() => {
        preview?.stop();
        preview = null;
        document.body.innerHTML = '';
    });

    it('opens a student-style dialog on trigger click', () => {
        const btn = trigger('report.pdf', 'https://example.test/source');

        btn.click();

        const dialog = document.querySelector('[data-document-library-preview]');
        expect(dialog).not.toBeNull();
        expect(dialog.getAttribute('role')).toBe('dialog');
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(dialog.classList.contains('documents-modal')).toBe(true);
        expect(dialog.querySelector('h2').textContent).toBe('Document Preview');
        expect(dialog.querySelector('.bg-gray-100.rounded-lg iframe')).not.toBeNull();
    });

    it('renders an image tag for image file types', () => {
        const btn = trigger('photo.png', 'https://example.test/source');
        btn.click();

        const image = document.querySelector('[data-document-library-preview] .bg-gray-100.rounded-lg img');
        expect(image).not.toBeNull();
        expect(image.getAttribute('src')).toBe('https://example.test/source');
    });

    it('shows an unsupported fallback for other file types', () => {
        const btn = trigger('notes.docx', 'https://example.test/source');
        btn.click();

        const dialog = document.querySelector('[data-document-library-preview]');
        expect(dialog.textContent).toContain('Preview not available');
        expect(dialog.querySelector('.ph-file-x')).not.toBeNull();
    });

    it('closes on Escape and restores focus to the trigger', () => {
        const button = trigger('report.pdf', 'https://example.test/source');
        button.click();
        button.focus();
        expect(document.querySelector('[data-document-library-preview]')).not.toBeNull();

        const btn2 = document.querySelector('[data-library-preview-trigger]');
        expect(btn2).toBe(button);
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(document.querySelector('[data-document-library-preview]')).toBeNull();
        expect(document.activeElement).toBe(button);
    });

    it('locks body scroll while open and restores it on close', () => {
        const button = trigger('report.pdf', 'https://example.test/source');
        button.click();
        expect(document.body.style.overflow).toBe('hidden');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(document.body.style.overflow).toBe('');
    });

    it('keeps Tab focus inside the dialog', () => {
        const button = trigger('report.pdf', 'https://example.test/source');
        button.click();
        const dialog = document.querySelector('[data-document-library-preview]');
        const closeBtn = dialog.querySelector('button');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));

        expect(document.activeElement).toBe(closeBtn);
        expect(dialog.contains(document.activeElement)).toBe(true);
    });

    it('closes on backdrop click', () => {
        const btn = trigger('report.pdf', 'https://example.test/source');
        btn.click();

        document.querySelector('.documents-modal__backdrop').click();

        expect(document.querySelector('[data-document-library-preview]')).toBeNull();
    });
});

