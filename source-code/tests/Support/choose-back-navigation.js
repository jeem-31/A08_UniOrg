import fs from 'node:fs';

// Exercises the "Back to Main Menu" navigation of the combined apply view:
// backToChoose() must reset the slide container, restore the card buttons,
// reset the wizard to step 1, clear member files and localStorage, and the
// page-load reset helper (restoreFormData) must run without throwing. This
// guards the rename regression where resetCreateSystemForm() was called but
// never defined, which silently broke back navigation and page-load reset.
const [viewPath] = process.argv.slice(2);
const view = fs.readFileSync(viewPath, 'utf8');
const blockStart = view.indexOf('// Select Request - Card slides RIGHT');
const blockEnd = view.indexOf('// Attach auto-save listeners', blockStart);
if (blockStart < 0 || blockEnd < 0 || blockEnd <= blockStart) {
    throw new Error('The back-to-choose navigation block was not found.');
}
if (view.includes('resetCreateSystemForm(')) {
    throw new Error('The undefined resetCreateSystemForm() call is still present.');
}

function trackingClassList() {
    const set = new Set();
    return {
        add(...names) { names.forEach(name => set.add(name)); },
        remove(...names) { names.forEach(name => set.delete(name)); },
        has(name) { return set.has(name); },
    };
}

const elements = new Map();
function element(id, value = '') {
    const listeners = {};
    const result = {
        value, disabled: false, dataset: {}, classList: trackingClassList(), options: [], parentNode: null,
        children: [], type: 'text', required: false,
        appendChild(child) { child.parentNode = result; this.children.push(child); return child; },
        insertBefore(child, before) {
            child.parentNode = result;
            const index = before ? this.children.indexOf(before) : -1;
            if (index >= 0) this.children.splice(index, 0, child); else this.children.push(child);
            return child;
        },
        querySelectorAll() { return []; },
        querySelector() { return this; },
        addEventListener(type, listener) { (listeners[type] ||= []).push(listener); },
        emit(type) { (listeners[type] || []).forEach(listener => listener({ target: result })); },
    };
    let html = '';
    Object.defineProperty(result, 'innerHTML', {
        get() { return html; },
        set(value) {
            html = value;
            const idPattern = /id="([^"]+)"/g;
            let match;
            while ((match = idPattern.exec(value)) !== null) {
                if (!elements.has(match[1])) element(match[1]);
            }
        },
    });
    let elementId = id;
    Object.defineProperty(result, 'id', {
        get() { return elementId; },
        set(next) {
            elements.delete(elementId);
            elementId = next;
            elements.set(next, result);
        },
    });
    elements.set(id, result);
    return result;
}

[
    'slideContainer', 'requestFormPanel', 'applyFormPanel',
    'requestButton', 'reqBtnDefault', 'reqBtnBack',
    'applyButton', 'applyBtnDefault', 'applyBtnBack',
    'currentStepApp', 'app-step1', 'app-step2', 'app-step3', 'app-step4', 'app-step5',
    'memberFilesPreview',
    'activate_department', 'activate_org_name', 'activate_org_acronym', 'activate_org_type',
    'activator_student_id', 'activator_first_name', 'activator_last_name', 'activator_middle_name',
    'activator_email',
].forEach(id => element(id));

const removedStorageKeys = [];
global.memberFiles = ['member-a', 'member-b'];
global.localStorage = { removeItem: key => removedStorageKeys.push(key) };
global.window = { selectedOrganizationId: 'org-1' };
global.updateAppProgress = () => {};
global.console = { log() {}, warn() {}, error() {} };
global.document = {
    getElementById: id => elements.get(id) || null,
    querySelector: selector => selector === 'meta[name="csrf-token"]' ? { content: 'synthetic-csrf' } : null,
};

(async () => {
    const app = new Function(`${view.slice(blockStart, blockEnd)}\nreturn { backToChoose, restoreFormData, selectRequest, selectApply };`)();
    const slide = elements.get('slideContainer');
    const requestFormPanel = elements.get('requestFormPanel');
    const applyFormPanel = elements.get('applyFormPanel');

    // Simulate the state after selectApply() ran: cards slid, form shown,
    // button transformed to "Back to Main Menu".
    slide.classList.add('slide-left');
    requestFormPanel.classList.add('show');
    applyFormPanel.classList.add('show');
    elements.get('reqBtnDefault').classList.add('hidden');
    elements.get('reqBtnBack').classList.remove('hidden');
    elements.get('applyBtnDefault').classList.add('hidden');
    elements.get('applyBtnBack').classList.remove('hidden');
    elements.get('currentStepApp').value = '6';
    elements.get('activator_first_name').value = 'Leftover';

    let threw = false;
    try {
        app.backToChoose();
    } catch (error) {
        threw = true;
        process.stderr.write(String(error && error.stack ? error.stack : error));
    }
    await new Promise(resolve => setTimeout(resolve, 500));

    let restoreThrew = false;
    try {
        app.restoreFormData();
    } catch (error) {
        restoreThrew = true;
        process.stderr.write(String(error && error.stack ? error.stack : error));
    }

    const result = {
        backToChooseDoesNotThrow: !threw,
        formPanelsHidden: !requestFormPanel.classList.has('show') && !applyFormPanel.classList.has('show'),
        requestButtonRestored: !elements.get('reqBtnDefault').classList.has('hidden')
            && elements.get('reqBtnBack').classList.has('hidden'),
        applyButtonRestored: !elements.get('applyBtnDefault').classList.has('hidden')
            && elements.get('applyBtnBack').classList.has('hidden'),
        slideContainerReset: !slide.classList.has('slide-right') && !slide.classList.has('slide-left'),
        wizardResetToStepOne: elements.get('currentStepApp').value == '1'
            && elements.get('app-step2').classList.has('hidden')
            && elements.get('app-step3').classList.has('hidden')
            && elements.get('app-step4').classList.has('hidden')
            && elements.get('app-step5').classList.has('hidden'),
        memberFilesCleared: global.memberFiles.length === 0,
        storageCleared: removedStorageKeys.includes('uniorg_system_request_data'),
        restoreFormDataDoesNotThrow: !restoreThrew,
        restoreFormDataResetsActivateForm: elements.get('activator_first_name').value === '',
    };

    process.stdout.write(JSON.stringify(result));
})().catch(error => { process.stderr.write(error.stack || String(error)); process.exitCode = 1; });
