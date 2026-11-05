import fs from 'node:fs';

const [viewPath] = process.argv.slice(2);
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
function element(id, value = '') {
    const listeners = {};
    const result = { id, value, disabled: false, dataset: {}, classList: new ClassList(),
        addEventListener(type, listener) { (listeners[type] ||= []).push(listener); },
        emit(type) { (listeners[type] || []).forEach(listener => listener({ target: result })); } };
    elements.set(id, result);
    return result;
}
const certifierFields = ['first_name', 'middle_name', 'last_name', 'suffix'];
const officerFields = [...certifierFields, 'year_level', 'section'];
const slots = ['president', 'vice_president', 'secretary', 'treasurer', 'auditor', 'rep_1st_year', 'rep_2nd_year', 'rep_3rd_year', 'rep_4th_year', 'business_manager', 'business_manager_2', 'pio', 'pio_2', 'event_coordinator', 'event_coordinator_2', 'comelec'];
global.officerPositions = [
    { key: 'senior_adviser', is_adviser: true }, { key: 'junior_adviser', is_adviser: true },
    ...slots.map(key => ({ key, is_adviser: false })),
];
global.customOfficerSlots = [];
certifierFields.forEach(field => element(`dean_${field}`));
['id_number', 'email'].forEach(field => element(`dean_${field}`));
global.officerPositions.forEach(position => {
    const fields = position.is_adviser ? ['id_number', 'email', ...certifierFields] : ['student_id', 'email', 'department_id', 'course', ...officerFields];
    fields.forEach(field => element(`officers_${position.key}_${field}`));
});
const file3 = element('file3');
file3.files = [];
element('currentStepApp', '5');
const toasts = [];
const requests = [];
class SyntheticFormData {
    constructor() { this.values = new Map(); }
    append(key, value) { this.values.set(key, value); }
    get(key) { return this.values.get(key); }
}
global.document = { getElementById: id => elements.get(id) || null, querySelector: selector => selector === 'meta[name="csrf-token"]' ? { content: 'synthetic-csrf' } : null };
global.FormData = SyntheticFormData;
global.showToast = (...args) => toasts.push(args);
global.updateCourseOptions = () => {};
global.fetch = (_url, options) => new Promise(resolve => requests.push({ options, resolve }));
const flush = () => new Promise(resolve => setImmediate(resolve));
const data = (prefix, includeForbidden = false) => ({
    dean: { first_name: `${prefix}Dean`, middle_name: 'M.', last_name: 'Faculty', suffix: 'EdD' },
    senior_adviser: { first_name: `${prefix}Senior`, middle_name: 'S.', last_name: 'Guide', suffix: 'MA' },
    junior_adviser: { first_name: `${prefix}Junior`, middle_name: 'J.', last_name: 'Mentor', suffix: 'PhD' },
    officers: Object.fromEntries(slots.map((slot, index) => [slot, {
        first_name: `${prefix}${slot}First`, middle_name: `M${index}.`, last_name: `${slot}Last`, suffix: `S${index}`,
        year_level: String((index % 4) + 1), section: String.fromCharCode(65 + (index % 4)),
        ...(includeForbidden ? { course: 'Must Not Apply', email: 'must-not-apply@example.test' } : {}),
    }])),
});
const requesterHelperStart = view.indexOf('function applyRequesterInfoToOfficer');
const requesterHelperEnd = view.indexOf('// Generate officer forms', requesterHelperStart);
if (requesterHelperStart < 0 || requesterHelperEnd < 0) throw new Error('Requester helper was not found.');
const requesterHelper = view.slice(requesterHelperStart, requesterHelperEnd);

// Mirrors the view's formatYearLevel contract: the browser SHALL display
// ordinal year labels ("1st Year", "2nd Year", ...) instead of raw digits.
const ordinalYearLabel = raw => {
    const text = String(raw).trim();
    if (!/^\d+$/.test(text)) return raw;
    const n = parseInt(text, 10);
    const last = n % 10;
    const lastTwo = n % 100;
    let suffix = 'th';
    if (lastTwo < 11 || lastTwo > 13) {
        if (last === 1) suffix = 'st';
        else if (last === 2) suffix = 'nd';
        else if (last === 3) suffix = 'rd';
    }
    return `${n}${suffix} Year`;
};
const expectedOfficerValue = (field, value) => field === 'year_level' ? ordinalYearLabel(value) : value;

(async () => {
    const generatedRequesterSenior = { first_name: 'Requester Senior', last_name: 'Preserved' };
    Object.entries(generatedRequesterSenior).forEach(([field, value]) => {
        elements.get(`officers_senior_adviser_${field}`).value = value;
    });
    ['req_first_name_apply', 'req_last_name_apply', 'req_email_apply'].forEach(id => element(id));

    const adviserDestinationIdsPresent = ['id_number', 'email', ...certifierFields].every(field =>
        view.includes(`id="\${namePrefix}_\${positionKey}_${field}"`));
    const generatedDestinationsStartBlank = !view.includes('let firstName = `Adviser`;')
        && !view.includes('let firstName = `Officer`;')
        && !view.includes('let lastName = positionLabel;')
        && !/id="\$\{namePrefix\}_\$\{positionKey\}_year_level"\s+value="3"/.test(view)
        && !/id="\$\{namePrefix\}_\$\{positionKey\}_section"\s+value="A"/.test(view);

    const app = new Function(`${view.slice(start, end)}\n${view.slice(customStart, customEnd)}\n${requesterHelper}\nreturn { officersPrefillState, applyVisiblePrefill, applyRequesterInfoToOfficer };`)();
    const fileA = { name: 'synthetic-A.pdf', type: 'application/pdf' };
    const fileB = { name: 'synthetic-B.pdf', type: 'application/pdf' };
    const fileC = { name: 'synthetic-C.pdf', type: 'application/pdf' };

    file3.files = [fileA];
    file3.emit('change');
    const requestAToken = requests[0].options.body.get('request_token');
    file3.files = [fileB];
    file3.emit('change');
    const requestBToken = requests[1].options.body.get('request_token');

    requests[0].resolve({ json: async () => ({ success: true, outcome: 'completed', request_token: requestAToken, data: data('A'), warnings: [] }) });
    await flush();
    const staleAWasIgnored = elements.get('dean_first_name').value === '';

    elements.get('currentStepApp').value = '6';
    requests[1].resolve({ json: async () => ({ success: true, outcome: 'partial', request_token: requestBToken, data: data('B', true), warnings: ['Synthetic partial result'] }) });
    await flush();
    const responseB = data('B', true);
    const deanHydrated = certifierFields.every(field => elements.get(`dean_${field}`).value === responseB.dean[field]);
    const deanHydratedWhenResponseLandsOnStepSix = deanHydrated;
    const deanDidNotFillIdOrEmail = elements.get('dean_id_number').value === '' && elements.get('dean_email').value === '';

    elements.get('currentStepApp').value = '6';
    app.applyVisiblePrefill();
    const adviserHydrated = certifierFields.every(field => {
        const seniorExpected = generatedRequesterSenior[field] ?? responseB.senior_adviser[field];
        return elements.get(`officers_senior_adviser_${field}`).value === seniorExpected
            && elements.get(`officers_junior_adviser_${field}`).value === responseB.junior_adviser[field];
    });
    const allSupportedOfficersHydrated = slots.every(slot => officerFields.every(field =>
        elements.get(`officers_${slot}_${field}`).value === expectedOfficerValue(field, responseB.officers[slot][field])));
    const president = responseB.officers.president;
    const officerHydrated = officerFields.every(field =>
        elements.get(`officers_president_${field}`).value === expectedOfficerValue(field, president[field]));
    const didNotFillUnrelated = slots.every(slot =>
        elements.get(`officers_${slot}_course`).value === '' && elements.get(`officers_${slot}_email`).value === '');

    const helperRequester = { first_name: 'Chosen', last_name: 'Requester', email: 'chosen@example.test' };
    elements.get('req_first_name_apply').value = helperRequester.first_name;
    elements.get('req_last_name_apply').value = helperRequester.last_name;
    elements.get('req_email_apply').value = helperRequester.email;
    app.applyRequesterInfoToOfficer('officers', 'junior_adviser', true);
    app.applyRequesterInfoToOfficer('officers', 'president', false);
    app.applyVisiblePrefill();
    const requesterHelperValuesRetained = ['junior_adviser', 'president'].every(slot =>
        Object.entries(helperRequester).every(([field, value]) => elements.get(`officers_${slot}_${field}`).value === value));

    const firstName = elements.get('officers_vice_president_first_name');
    const middleName = elements.get('officers_vice_president_middle_name');
    firstName.value = 'Manual First';
    firstName.emit('input');
    middleName.value = '';
    middleName.emit('change');
    app.applyVisiblePrefill();
    const stepSixRevisitPreservedManualIntent = firstName.value === 'Manual First' && middleName.value === '';
    elements.get('currentStepApp').value = '5';
    app.applyVisiblePrefill();
    const stepFiveRevisitRetainedDean = certifierFields.every(field =>
        elements.get(`dean_${field}`).value === responseB.dean[field]);

    file3.files = [];
    file3.emit('change');
    const protectedOfficerFields = new Set([
        'president:first_name', 'president:last_name',
        'vice_president:first_name', 'vice_president:middle_name',
    ]);
    const allUnchangedAutomaticValuesCleared = slots.every(slot => officerFields.every(field =>
        protectedOfficerFields.has(`${slot}:${field}`) || elements.get(`officers_${slot}_${field}`).value === ''));
    const generatedRequesterValuesRetained = Object.entries(generatedRequesterSenior).every(([field, value]) =>
        elements.get(`officers_senior_adviser_${field}`).value === value);
    const requesterValuesRetained = generatedRequesterValuesRetained && requesterHelperValuesRetained;
    const removalClearedOnlyUnchangedAutomatic = allUnchangedAutomaticValuesCleared
        && elements.get('officers_senior_adviser_middle_name').value === ''
        && elements.get('officers_senior_adviser_suffix').value === ''
        && firstName.value === 'Manual First' && middleName.value === '' && requesterValuesRetained;
    const allInputsRemainEditable = [
        ...certifierFields.map(field => elements.get(`dean_${field}`)),
        ...officerFields.map(field => elements.get(`officers_president_${field}`)),
    ].every(input => !input.disabled);
    const exactGeneratedKeys = JSON.stringify(global.officerPositions.map(position => position.key)) === JSON.stringify([
        'senior_adviser', 'junior_adviser', ...slots,
    ]);

    // Binary toast contract: an empty-officers response must surface
    // 'Autofill Failed' (wrong file / nothing detected).
    const statusAfterRemoval = app.officersPrefillState.status;
    file3.files = [fileC];
    file3.emit('change');
    const requestCToken = requests[2].options.body.get('request_token');
    requests[2].resolve({ json: async () => ({ success: true, outcome: 'manual_required', request_token: requestCToken, data: { dean: null, senior_adviser: null, junior_adviser: null, officers: {} }, warnings: ['Synthetic empty result'] }) });
    await flush();

    process.stdout.write(JSON.stringify({
        requestAToken, requestBToken, staleAWasIgnored, abortedA: requests[0].options.signal.aborted,
        deanHydrated, deanHydratedWhenResponseLandsOnStepSix, deanDidNotFillIdOrEmail,
        adviserHydrated, officerHydrated, allSupportedOfficersHydrated,
        adviserDestinationIdsPresent, generatedDestinationsStartBlank, requesterValuesRetained,
        requesterHelperValuesRetained, didNotFillUnrelated, exactGeneratedKeys, stepFiveRevisitRetainedDean,
        stepSixRevisitPreservedManualIntent, removalClearedOnlyUnchangedAutomatic, allInputsRemainEditable,
        failedToastShown: toasts.some(([, type, title]) => type === 'warning' && title === 'Autofill Failed'),
        statusAfterRemoval,
    }));
})().catch(error => { process.stderr.write(error.stack || String(error)); process.exitCode = 1; });