import fs from 'node:fs';

const [viewPath, payload] = process.argv.slice(2);
const expected = JSON.parse(Buffer.from(payload, 'base64').toString('utf8'));
const view = fs.readFileSync(viewPath, 'utf8');
const start = view.indexOf('// Certification of Officers PDF auto-fill belongs only');
const end = view.indexOf('// Apply Form File Uploads', start);
const customStart = view.indexOf('function autoLinkOfficerDepartment');
const customEnd = view.indexOf('// Auto-fetch student data when 8 or 10 digits are entered', customStart);
if (start < 0 || end < 0 || customStart < 0 || customEnd < 0) {
    throw new Error('Current Certification prefill or department-link helper block was not found.');
}

class ClassList { add() {} remove() {} }
const elements = new Map();
const field = (id, value = '') => {
    const listeners = {};
    const result = { id, value, disabled: false, dataset: {}, classList: new ClassList(), files: [],
        addEventListener(type, listener) { (listeners[type] ||= []).push(listener); },
        emit(type) { (listeners[type] || []).forEach(listener => listener({ target: result })); } };
    elements.set(id, result);
    return result;
};
const certifierFields = ['first_name', 'middle_name', 'last_name', 'suffix'];
const officerFields = [...certifierFields, 'year_level', 'section'];
const slots = ['president', 'vice_president', 'secretary', 'treasurer', 'auditor', 'rep_1st_year', 'rep_2nd_year', 'rep_3rd_year', 'rep_4th_year', 'business_manager', 'business_manager_2', 'pio', 'pio_2', 'event_coordinator', 'event_coordinator_2', 'comelec'];
global.officerPositions = [{ key: 'senior_adviser', is_adviser: true }, { key: 'junior_adviser', is_adviser: true }, ...slots.map(key => ({ key, is_adviser: false }))];
global.customOfficerSlots = [];
certifierFields.forEach(name => field(`dean_${name}`));
global.officerPositions.forEach(position => (position.is_adviser ? certifierFields : officerFields).forEach(name => field(`officers_${position.key}_${name}`)));
const file3 = field('file3');
field('currentStepApp', '6');
class SyntheticFormData { constructor() { this.values = new Map(); } append(key, value) { this.values.set(key, value); } get(key) { return this.values.get(key); } }
global.document = { getElementById: id => elements.get(id) || null, querySelector: selector => selector === 'meta[name="csrf-token"]' ? { content: 'synthetic-csrf-token' } : null };
global.FormData = SyntheticFormData;
global.showToast = () => {};
global.updateCourseOptions = () => {};
let fetchCalls = 0;
const beta = structuredClone(expected);
beta.officers.president.first_name = 'BETA';
global.fetch = async (_url, options) => { fetchCalls += 1; return { json: async () => ({ success: true, outcome: 'completed', request_token: options.body.get('request_token'), data: beta, warnings: [] }) }; };

(async () => {
    const app = new Function(`${view.slice(start, end)}\n${view.slice(customStart, customEnd)}\nreturn { officersPrefillState, applyVisiblePrefill };`)();
    const result = {};
    const fileA = { name: 'synthetic-A.pdf', type: 'application/pdf' };
    const fileB = { name: 'synthetic-B.pdf', type: 'application/pdf' };
    try {
        app.officersPrefillState.file = fileA;
        app.officersPrefillState.data = expected;
        app.applyVisiblePrefill();
        result.keyedError = null;
    } catch (error) { result.keyedError = `${error.name}: ${error.message}`; }
    result.nameOnly = Object.fromEntries(officerFields.map(name => [name, elements.get(`officers_president_${name}`).value]));
    file3.files = [fileB];
    file3.emit('change');
    await new Promise(resolve => setImmediate(resolve));
    const firstName = elements.get('officers_president_first_name');
    const cachedFirstName = firstName.value;
    firstName.value = 'MANUAL FIRST';
    firstName.emit('input');
    file3.files = [];
    file3.emit('change');
    result.timeline = { fetchCalls, cachedFirstName, afterRemovalCache: app.officersPrefillState.data ? app.officersPrefillState.data.officers.president.first_name : null, manualFirstName: firstName.value };
    process.stdout.write(JSON.stringify(result));
})().catch(error => { process.stderr.write(error.stack || String(error)); process.exitCode = 1; });
