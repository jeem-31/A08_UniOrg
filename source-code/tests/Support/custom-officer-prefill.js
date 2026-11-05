import fs from 'node:fs';

// Exercises the browser prefill path for "Other Position" cards: unknown
// officer keys in the prefill payload must create a custom card at the end
// of the officers container, register it for dirty tracking, and prefill it.
// Also verifies year-level ordinal formatting and that custom cards
// auto-link their department identically to default cards.
const [viewPath] = process.argv.slice(2);
const view = fs.readFileSync(viewPath, 'utf8');
const prefillStart = view.indexOf('// Certification of Officers PDF auto-fill belongs only');
const prefillEnd = view.indexOf('// Apply Form File Uploads', prefillStart);
const customStart = view.indexOf('function autoLinkOfficerDepartment');
const customEnd = view.indexOf('// Auto-fetch student data when 8 or 10 digits are entered', customStart);
if (prefillStart < 0 || prefillEnd < 0 || customStart < 0 || customEnd < 0) {
    throw new Error('The prefill or custom officer helper blocks were not found.');
}

class ClassList { add() {} remove() {} }
const elements = new Map();
let createdElementCounter = 0;
function element(id, value = '') {
    const listeners = {};
    const result = {
        value, disabled: false, dataset: {}, classList: new ClassList(), options: [], parentNode: { appendChild() {} },
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
                elements.get(match[1]).parentNode = result;
            }
            const inputPattern = /<input\b([^>]*)>/g;
            let inputMatch;
            while ((inputMatch = inputPattern.exec(value)) !== null) {
                const attrs = inputMatch[1];
                const idAttr = /id="([^"]+)"/.exec(attrs);
                const valueAttr = /value="([^"]*)"/.exec(attrs);
                if (idAttr && valueAttr && elements.has(idAttr[1])) {
                    elements.get(idAttr[1]).value = valueAttr[1];
                }
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

const certifierFields = ['first_name', 'middle_name', 'last_name', 'suffix'];
const officerFields = [...certifierFields, 'year_level', 'section'];
const slots = ['president', 'vice_president', 'secretary', 'treasurer', 'auditor', 'rep_1st_year', 'rep_2nd_year', 'rep_3rd_year', 'rep_4th_year', 'business_manager', 'business_manager_2', 'pio', 'pio_2', 'event_coordinator', 'event_coordinator_2', 'comelec'];
const labelMap = {
    senior_adviser: 'Senior Adviser', junior_adviser: 'Junior Adviser',
    president: 'President', vice_president: 'Vice President', secretary: 'Secretary',
    treasurer: 'Treasurer', auditor: 'Auditor', rep_1st_year: '1st Year Representative',
    rep_2nd_year: '2nd Year Representative', rep_3rd_year: '3rd Year Representative',
    rep_4th_year: '4th Year Representative', business_manager: 'Business Manager',
    business_manager_2: 'Business Manager 2', pio: 'Public Information Officer',
    pio_2: 'Public Information Officer 2', event_coordinator: 'Event Coordinator',
    event_coordinator_2: 'Event Coordinator 2', comelec: 'COMELEC',
};
global.officerPositions = [
    { key: 'senior_adviser', label: labelMap.senior_adviser, is_adviser: true },
    { key: 'junior_adviser', label: labelMap.junior_adviser, is_adviser: true },
    ...slots.map(key => ({ key, label: labelMap[key], is_adviser: false })),
];
global.customOfficerSlots = [];
certifierFields.forEach(field => element(`dean_${field}`));
global.officerPositions.forEach(position => {
    const fields = position.is_adviser ? certifierFields : ['student_id', 'email', 'department_id', 'course', ...officerFields];
    fields.forEach(field => element(`officers_${position.key}_${field}`));
});
element('appOfficersContainer');
element('org_type_apply', '');
element('department_apply', '');
element('currentStepApp', '6');
['file3-debug-info', 'file3-debug-name', 'file3-debug-size', 'file3-debug-modified', 'file3-debug-status', 'file3-debug-extracted'].forEach(id => element(id));
global.document = {
    getElementById: id => elements.get(id) || null,
    querySelector: selector => selector === 'meta[name="csrf-token"]' ? { content: 'synthetic-csrf' } : null,
    createElement: () => element(`__created_${createdElementCounter++}__`),
};
global.updateCourseOptions = () => {};
global.showToast = () => {};
global.console = { log() {}, warn() {}, error() {} };

(async () => {
    const app = new Function(`${view.slice(prefillStart, prefillEnd)}\n${view.slice(customStart, customEnd)}\nreturn { officersPrefillState, applyVisiblePrefill, customOfficerSlots, updateOfficersDebugUI };`)();
    app.officersPrefillState.generation = 7;
    app.officersPrefillState.data = {
        dean: { first_name: 'Dana', middle_name: 'L.', last_name: 'Faculty', suffix: 'EdD' },
        officers: {
            president: { first_name: 'Pio', middle_name: 'M.', last_name: 'Pres', suffix: '', year_level: '3', section: 'B' },
            custom_1: { first_name: 'Maya', middle_name: 'K.', last_name: 'Lima', suffix: '', year_level: '2', section: 'A', position_name: 'Assistant Secretary' },
            custom_2: { first_name: 'Nico', middle_name: '', last_name: 'Reyes', suffix: 'Jr.', year_level: '1', section: 'C' },
        },
    };
    app.applyVisiblePrefill();

    app.updateOfficersDebugUI({ name: 'certification.pdf', size: 204800, lastModified: 1754246400000 }, 'COMPLETED', app.officersPrefillState.data, null);
    const debugHtml = elements.get('file3-debug-extracted')?.innerHTML || '';

    const customCardIds = ['student_id', 'first_name', 'last_name', 'middle_name', 'email', 'department_id', 'course', 'year_level', 'section', 'suffix'];
    const customCardTemplate = view.slice(customStart, customEnd);
    const firstRun = {
        customCardCreated: customCardIds.every(field => elements.has(`officers_custom_1_${field}`)),
        secondCustomCardCreated: elements.has('officers_custom_2_first_name'),
        dividerCreated: elements.has('customOfficersDivider'),
        addButtonCreated: elements.has('customAddPositionBtn'),
        registeredInState: app.customOfficerSlots.length === 2
            && app.customOfficerSlots[0].key === 'custom_1'
            && app.customOfficerSlots[1].key === 'custom_2',
        positionNameInputRemoved: customCardTemplate.includes('Position Name *'),
        positionNameCarriedHidden: customCardTemplate.includes('type="hidden" name="${namePrefix}[${positionKey}][position]"')
            && elements.get('officers_custom_1_position_name')?.value === 'Assistant Secretary',
        customNamesApplied: elements.get('officers_custom_1_first_name')?.value === 'Maya'
            && elements.get('officers_custom_1_last_name')?.value === 'Lima'
            && elements.get('officers_custom_1_year_level')?.value === '2nd Year'
            && elements.get('officers_custom_1_section')?.value === 'A',
        secondCardNamesApplied: elements.get('officers_custom_2_first_name')?.value === 'Nico'
            && elements.get('officers_custom_2_suffix')?.value === 'Jr.',
        yearLevelFormatted: elements.get('officers_custom_1_year_level')?.value === '2nd Year'
            && elements.get('officers_custom_2_year_level')?.value === '1st Year',
        defaultYearLevelFormatted: elements.get('officers_president_year_level')?.value === '3rd Year',
        aiKeyLabelDerived: global.customOfficerSlots[1].label === 'Other Position',
        defaultsStillHydrated: elements.get('officers_president_first_name')?.value === 'Pio',
        deanStillHydrated: elements.get('dean_first_name')?.value === 'Dana',
        customTracked: elements.get('officers_custom_1_year_level')?.dataset?.autoFillToken === '7',
        customValuesPrefillable: elements.get('officers_custom_1_first_name')?.dataset?.autoFillToken === '7',
        departmentEnabledWhenGlobalEmpty: elements.get('officers_custom_1_department_id')?.value === ''
            && elements.get('officers_custom_1_department_id')?.disabled === false,
        debugShowsCapturedPosition: debugHtml.includes('Assistant Secretary:') && debugHtml.includes('Maya K. Lima'),
        debugShowsDefaultLabel: debugHtml.includes('President:') && debugHtml.includes('Pio M. Pres'),
        debugFallsBackForKeyOnly: debugHtml.includes('Other Position:') && debugHtml.includes('Nico'),
        debugHidesRawSlotKeys: !debugHtml.includes('custom 1') && !debugHtml.includes('custom_1') && !debugHtml.includes('president:'),
    };

    // Second scenario, part A: the applicant chose the org type and global
    // department only AFTER the cards were created — the re-link pass in
    // applyVisibleOfficersPrefill must link the existing cards without a rebuild.
    elements.get('org_type_apply').value = 'Academic Org';
    elements.get('department_apply').value = '3';
    app.applyVisiblePrefill();
    const relinkedWithoutRebuild = elements.get('officers_custom_1_department_id')?.value === '3'
        && elements.get('officers_custom_1_department_id')?.disabled === true
        && Array.from(elements.values()).some(el => el.name === 'officers[custom_1][department_id]'
            && el.type === 'hidden' && el.value === '3');

    // Second scenario, part B: a fresh rebuild (step-6 revisit) links newly
    // built custom cards identically to the default cards.
    app.customOfficerSlots.length = 0;
    for (const id of [...elements.keys()]) {
        if (id.startsWith('officers_custom_') || id === 'customOfficersDivider') elements.delete(id);
    }
    app.applyVisiblePrefill();

    const secondRun = {
        departmentLinkedOnCustomCard: elements.get('officers_custom_1_department_id')?.value === '3'
            && elements.get('officers_custom_1_department_id')?.disabled === true
            && Array.from(elements.values()).some(el => el.name === 'officers[custom_1][department_id]'
                && el.type === 'hidden' && el.value === '3'),
        yearLevelFormatIdempotent: elements.get('officers_president_year_level')?.value === '3rd Year'
            && elements.get('officers_custom_1_year_level')?.value === '2nd Year',
    };

    // Third scenario: Non-Academic org type has no global department — the
    // college detected from the Certification of Officers PDF fills the
    // department select; an unmatched (or absent) college stays blank.
    app.customOfficerSlots.length = 0;
    for (const id of [...elements.keys()]) {
        if (id.startsWith('officers_custom_') || id === 'customOfficersDivider') elements.delete(id);
    }
    elements.get('org_type_apply').value = 'Non-Academic';
    elements.get('department_apply').value = '';
    const deptOptions = [
        { value: '1', textContent: 'College of Engineering (CEIT)' },
        { value: '4', textContent: 'College of Imaginary Arts (COIA)' },
    ];
    elements.get('officers_president_department_id').options = deptOptions;
    app.officersPrefillState.data.college = 'College of Imaginary Arts';
    app.applyVisiblePrefill();
    elements.get('officers_custom_1_department_id').options = deptOptions;
    app.applyVisiblePrefill();
    const collegeMatchedDepartment = elements.get('officers_custom_1_department_id')?.value === '4';

    app.officersPrefillState.data.college = 'College of Unknown Studies';
    app.applyVisiblePrefill();
    const unmatchedCollegeStaysBlank = elements.get('officers_custom_1_department_id')?.value === ''
        && elements.get('officers_custom_1_department_id')?.disabled === false;

    process.stdout.write(JSON.stringify({ ...firstRun, relinkedWithoutRebuild, ...secondRun, collegeMatchedDepartment, unmatchedCollegeStaysBlank }));
})().catch(error => { process.stderr.write(error.stack || String(error)); process.exitCode = 1; });
