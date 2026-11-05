import fs from 'node:fs';

const [viewPath, encodedCase] = process.argv.slice(2);
const scenario = JSON.parse(Buffer.from(encodedCase, 'base64').toString('utf8'));
const view = fs.readFileSync(viewPath, 'utf8');
const start = view.indexOf('// Certification of Officers PDF auto-fill belongs only');
const end = view.indexOf('// Apply Form File Uploads', start);
const navigationStart = view.indexOf('function nextAppStep(');
const navigationEnd = view.indexOf('function prevAppStep(', navigationStart);
if (start < 0 || end < 0 || navigationStart < 0 || navigationEnd < 0) {
    throw new Error('The application prefill or navigation helper blocks were not found.');
}

class ClassList {
    constructor() { this.values = new Set(); }
    add(...values) { values.forEach((value) => this.values.add(value)); }
    remove(...values) { values.forEach((value) => this.values.delete(value)); }
    contains(value) { return this.values.has(value); }
}

const elements = new Map();
const input = (id, value = '', required = false) => {
    let currentValue = String(value ?? '');
    const element = { id, required, disabled: false, dataset: {}, classList: new ClassList(), addEventListener() {} };
    Object.defineProperty(element, 'value', {
        get() { return currentValue; },
        set(nextValue) { currentValue = String(nextValue ?? ''); },
    });
    elements.set(id, element);
    return element;
};
const step = (id, requiredInputs = []) => {
    const element = input(id);
    element.querySelectorAll = () => requiredInputs;
    return element;
};
const manualGroup = (prefix, values) => Object.fromEntries(Object.entries(values).map(([field, value]) => {
    input(`${prefix}_${field}`, value);
    return [field, value];
}));
const readGroup = (prefix, fields) => Object.fromEntries(fields.map((field) => [field, elements.get(`${prefix}_${field}`).value]));
const isEditable = (prefix, fields) => fields.every((field) => !elements.get(`${prefix}_${field}`).disabled);
const fields = ['first_name', 'middle_name', 'last_name', 'suffix'];
global.customOfficerSlots = [];
const dean = manualGroup('dean', scenario.manual.dean);
const officer = manualGroup('officers_president', scenario.manual.officer);
const file3 = input('file3');
file3.files = scenario.upload ? [{ name: scenario.fileName, type: scenario.mimeType }] : [];
const requiredInput = input('manual_required_value', scenario.validationValue, true);
step('app-step4', [requiredInput]);
step('app-step5');
input('currentStepApp', '4');
input('appSubmitBtn');

let fetchCalls = 0;
global.document = {
    getElementById(id) { return elements.get(id) || null; },
    querySelector(selector) { return selector === 'meta[name="csrf-token"]' ? { content: 'synthetic-csrf-token' } : null; },
};
global.FormData = class { append() {} };
global.fetch = async () => {
    fetchCalls += 1;
    return { json: async () => ({ success: false, message: 'Synthetic extraction recovery; continue with manual entry.' }) };
};
global.showToast = () => {};
global.console = { log() {}, warn() {}, error() {} };
global.updateAppProgress = () => {};

(async () => {
    const app = new Function(`${view.slice(start, end)}\n${view.slice(navigationStart, navigationEnd)}\nreturn { triggerOfficersPrefill, nextAppStep };`)();
    await app.triggerOfficersPrefill();
    const afterRecovery = {
        manual: { dean: readGroup('dean', fields), officer: readGroup('officers_president', fields) },
        editable: isEditable('dean', fields) && isEditable('officers_president', fields),
        unrelatedCreated: elements.has('officers_director_first_name') || elements.has('officers_course_first_name'),
    };

    requiredInput.value = '';
    app.nextAppStep(5);
    const validationBlockedNavigation = document.getElementById('currentStepApp').value === '4';
    const validationLeftManualFieldsEditable = isEditable('dean', fields) && isEditable('officers_president', fields);
    requiredInput.value = scenario.validationValue;
    app.nextAppStep(5);
    const navigationAdvanced = document.getElementById('currentStepApp').value === '5';
    const submissionAvailable = !document.getElementById('appSubmitBtn').disabled
        && view.includes('id="appSubmitBtn"') && view.includes('route("system-request.submit")');
    const ordinaryUploadAccepted = view.includes('accept=".pdf,.jpg,.jpeg,.png"');

    process.stdout.write(JSON.stringify({
        fetchCalls, outcome: scenario.upload && scenario.mimeType === 'application/pdf' ? 'recovery' : 'manual', afterRecovery,
        validationBlockedNavigation, validationLeftManualFieldsEditable, navigationAdvanced,
        submissionAvailable, ordinaryUploadAccepted,
    }));
})().catch((error) => {
    process.stderr.write(error.stack || String(error));
    process.exitCode = 1;
});
