@extends('layouts.dean')

@section('title', 'Financial Reports')

@php
    $paymentsCount = (int) ($totalPayments ?? 0);
    $plannedBudget = (float) ($totalEventBudgets ?? 0);
    $budgetRecords = collect($eventBudgets ?? []);
    $approvedBudgetCount = $budgetRecords->where('approval_status', 'approved')->count();
    $activeReportTab = in_array(request('tab'), ['payments', 'budgets'], true) ? request('tab') : 'payments';
@endphp

@section('content')
<div class="dean-finance-page" x-data="{
    activeTab: '{{ $activeReportTab }}',
    activeCategory: '{{ request('category', 'all') }}',
    budgetStatus: 'all',
    budgetSearch: '',
    startDate: '{{ request('start_date', '') }}',
    endDate: '{{ request('end_date', '') }}',
    currentPaymentPage: 1,
    currentBudgetPage: 1,
    perPage: 10,
    payments: {{ Js::from($payments ?? []) }},
    budgets: {{ Js::from($eventBudgets ?? []) }},
    get filteredPayments() {
        return this.payments.filter(payment => {
            if (this.activeCategory !== 'all' && (payment.category || 'other') !== this.activeCategory) return false;
            const paidDate = payment.paid_at ? new Date(payment.paid_at) : null;
            if (paidDate && this.startDate && paidDate < new Date(this.startDate + 'T00:00:00')) return false;
            if (paidDate && this.endDate && paidDate > new Date(this.endDate + 'T23:59:59')) return false;
            return true;
        });
    },
    get filteredBudgets() {
        const query = this.budgetSearch.trim().toLowerCase();
        return this.budgets.filter(budget => {
            const title = (budget.title || '').toLowerCase();
            const venue = (budget.venue || '').toLowerCase();
            const status = budget.approval_status || '';
            if (this.budgetStatus !== 'all' && status !== this.budgetStatus) return false;
            return !query || title.includes(query) || venue.includes(query);
        });
    },
    get paymentPages() { return Math.ceil(this.filteredPayments.length / this.perPage) || 1; },
    get budgetPages() { return Math.ceil(this.filteredBudgets.length / this.perPage) || 1; },
    get paginatedPayments() {
        const start = (this.currentPaymentPage - 1) * this.perPage;
        return this.filteredPayments.slice(start, start + this.perPage);
    },
    get paginatedBudgets() {
        const start = (this.currentBudgetPage - 1) * this.perPage;
        return this.filteredBudgets.slice(start, start + this.perPage);
    },
    get visiblePaymentIds() { return new Set(this.paginatedPayments.map(payment => String(payment.transaction_id))); },
    get visibleBudgetIds() { return new Set(this.paginatedBudgets.map(budget => String(budget.id))); },
    init() {
        this.$watch('activeCategory', () => this.currentPaymentPage = 1);
        this.$watch('startDate', () => this.currentPaymentPage = 1);
        this.$watch('endDate', () => this.currentPaymentPage = 1);
        this.$watch('budgetSearch', () => this.currentBudgetPage = 1);
        this.$watch('budgetStatus', () => this.currentBudgetPage = 1);
    },
    resetPaymentFilters() {
        this.activeCategory = 'all';
        this.startDate = '';
        this.endDate = '';
    },
    resetBudgetFilters() {
        this.budgetStatus = 'all';
        this.budgetSearch = '';
    },
    goToPage(type, page) {
        const total = type === 'payments' ? this.paymentPages : this.budgetPages;
        if (page < 1 || page > total) return;
        if (type === 'payments') this.currentPaymentPage = page;
        else this.currentBudgetPage = page;
    },
    handleExport() {
        const isPayments = this.activeTab === 'payments';
        const collection = isPayments ? this.filteredPayments : this.filteredBudgets;
        if (collection.length === 0) {
            this.showToast('Nothing to export', 'No records match the current filters.', 'error');
            return;
        }
        let url = '{{ route('dean.financial-reports.export') }}?tab=' + this.activeTab;
        if (isPayments) {
            url += '&category=' + encodeURIComponent(this.activeCategory);
            if (this.startDate) url += '&start_date=' + encodeURIComponent(this.startDate);
            if (this.endDate) url += '&end_date=' + encodeURIComponent(this.endDate);
        }
        window.location.href = url;
    },
    showToast(title, message, type = 'success') {
        const toast = document.getElementById('finance-toast');
        const icon = document.getElementById('finance-toast-icon');
        document.getElementById('finance-toast-title').textContent = title;
        document.getElementById('finance-toast-message').textContent = message;
        toast.classList.remove('hidden', 'finance-toast--success', 'finance-toast--error');
        toast.classList.add(type === 'success' ? 'finance-toast--success' : 'finance-toast--error');
        icon.innerHTML = '';
        const iconElement = document.createElement('i');
        iconElement.className = type === 'success' ? 'ph ph-check' : 'ph ph-warning';
        icon.appendChild(iconElement);
        window.setTimeout(() => toast.classList.add('hidden'), 4500);
    }
}">
    <div class="dean-finance-page__shell">
        <header class="dean-finance-header">
            <div>
                <div class="dean-finance-eyebrow">Finance operations</div>
                <h1>Financial reports</h1>
                <p>Track student payments and planned event budgets across the department.</p>
            </div>
            <div class="dean-finance-header__actions">
                <a href="{{ route('dean.dashboard') }}" class="dean-finance-btn dean-finance-btn--secondary"><i class="ph ph-arrow-left"></i><span>Dashboard</span></a>
                <button type="button" @click="handleExport()" class="dean-finance-btn dean-finance-btn--primary"><i class="ph ph-file-pdf"></i><span>Export current view</span></button>
            </div>
        </header>

        <section class="dean-finance-metrics" aria-label="Financial summary">
            <div class="dean-finance-metric dean-finance-metric--blue"><span class="dean-finance-metric__icon"><i class="ph ph-receipt"></i></span><span><small>Transactions</small><strong>{{ number_format($paymentsCount) }}</strong><em>Completed payments</em></span></div>
            <div class="dean-finance-metric dean-finance-metric--gold"><span class="dean-finance-metric__icon"><i class="ph ph-calendar-check"></i></span><span><small>Planned event budgets</small><strong>&#8369;{{ number_format($plannedBudget, 2) }}</strong><em>{{ number_format($approvedBudgetCount) }} approved records</em></span></div>
        </section>

        <div class="dean-finance-tabs" role="tablist" aria-label="Financial report type">
            <button type="button" role="tab" :aria-selected="activeTab === 'payments'" :class="activeTab === 'payments' ? 'is-active' : ''" @click="activeTab = 'payments'"><i class="ph ph-credit-card"></i><span>Student payments</span><b>{{ number_format($paymentsCount) }}</b></button>
            <button type="button" role="tab" :aria-selected="activeTab === 'budgets'" :class="activeTab === 'budgets' ? 'is-active' : ''" @click="activeTab = 'budgets'"><i class="ph ph-wallet"></i><span>Event budgets</span><b>{{ number_format($budgetRecords->count()) }}</b></button>
        </div>

        <div class="dean-finance-workspace">
            <main class="dean-finance-panel">
                <div class="dean-finance-panel__header">
                    <div><span class="dean-finance-panel__eyebrow">REPORT DATA</span><h2 x-text="activeTab === 'payments' ? 'Student payments' : 'Event budgets'"></h2><p x-show="activeTab === 'payments'">Completed transactions received from students.</p><p x-show="activeTab === 'budgets'" style="display:none">Planned budgets attached to department events.</p></div>
                    <button type="button" class="dean-finance-export-link" @click="handleExport()"><i class="ph ph-download-simple"></i> Export PDF</button>
                </div>

                <div class="dean-finance-filterbar" x-show="activeTab === 'payments'">
                    <label><span>Category</span><select x-model="activeCategory"><option value="all">All categories</option><option value="tour_fee">Tour fee</option><option value="membership_fee">Membership fee</option><option value="event_fee">Event fee</option><option value="document_fee">Document fee</option><option value="medical_fee">Medical fee</option><option value="other">Other</option></select></label>
                    <label><span>From</span><input type="date" x-model="startDate"></label>
                    <label><span>To</span><input type="date" x-model="endDate"></label>
                    <button type="button" class="dean-finance-reset" x-show="activeCategory !== 'all' || startDate || endDate" @click="resetPaymentFilters()"><i class="ph ph-arrow-counter-clockwise"></i> Reset</button>
                </div>

                <div class="dean-finance-filterbar" x-show="activeTab === 'budgets'" style="display:none">
                    <label class="dean-finance-search"><span>Search events</span><span class="dean-finance-input-wrap"><i class="ph ph-magnifying-glass"></i><input type="search" x-model="budgetSearch" placeholder="Event title or venue"></span></label>
                    <label><span>Status</span><select x-model="budgetStatus"><option value="all">All statuses</option><option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select></label>
                    <button type="button" class="dean-finance-reset" x-show="budgetSearch || budgetStatus !== 'all'" @click="resetBudgetFilters()"><i class="ph ph-arrow-counter-clockwise"></i> Reset</button>
                </div>

                <section x-show="activeTab === 'payments'">
                    <div class="dean-finance-table-meta"><span>Completed payment ledger</span><span x-text="filteredPayments.length + ' matching records'"></span></div>
                    <div class="dean-finance-table-wrap">
                        <table class="dean-finance-table">
                            <thead><tr><th>Student</th><th>Transaction</th><th>Category</th><th>Amount</th><th>Method</th><th>Paid</th></tr></thead>
                            <tbody>
                                @forelse($payments ?? [] as $payment)
                                    <tr x-show="visiblePaymentIds.has('{{ $payment->transaction_id }}')">
                                        <td><div class="dean-finance-person"><span>{{ strtoupper(substr($payment->user->first_name ?? 'U', 0, 1)) }}</span><div><strong>{{ name_with_suffix($payment->user->first_name ?? '', null, $payment->user->last_name ?? '', $payment->user->suffix ?? '') ?: 'Unknown student' }}</strong><small>{{ $payment->user->student_id ?? 'Student ID unavailable' }}</small></div></div></td>
                                        <td><span class="dean-finance-transaction">{{ $payment->transaction_id }}</span></td>
                                        <td><span class="dean-finance-tag dean-finance-tag--purple">{{ ucfirst(str_replace('_', ' ', $payment->category ?? 'other')) }}</span></td>
                                        <td><strong class="dean-finance-amount">&#8369;{{ number_format($payment->amount, 2) }}</strong></td>
                                        <td><span class="dean-finance-method"><i class="ph {{ $payment->payment_method === 'gcash' ? 'ph-device-mobile' : 'ph-hand-coins' }}"></i>{{ ucfirst($payment->payment_method ?? 'Manual') }}</span></td>
                                        <td><strong class="dean-finance-date">{{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : 'N/A' }}</strong><small class="dean-finance-time">{{ $payment->paid_at ? $payment->paid_at->format('h:i A') : '' }}</small></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><div class="dean-finance-empty"><span><i class="ph ph-receipt"></i></span><strong>No completed payments</strong><p>Student payments will appear here once recorded.</p></div></td></tr>
                                @endforelse
                                <tr x-show="filteredPayments.length === 0 && payments.length > 0"><td colspan="6"><div class="dean-finance-empty"><span><i class="ph ph-funnel"></i></span><strong>No matching payments</strong><p>Try broadening the category or date filters.</p></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="dean-finance-pagination" x-show="filteredPayments.length > 0"><span>Showing <b x-text="((currentPaymentPage - 1) * perPage) + 1"></b>–<b x-text="Math.min(currentPaymentPage * perPage, filteredPayments.length)"></b> of <b x-text="filteredPayments.length"></b></span><div><button type="button" @click="goToPage('payments', currentPaymentPage - 1)" :disabled="currentPaymentPage === 1"><i class="ph ph-caret-left"></i></button><template x-for="page in paymentPages" :key="page"><button type="button" @click="goToPage('payments', page)" x-text="page" :class="page === currentPaymentPage ? 'is-current' : ''"></button></template><button type="button" @click="goToPage('payments', currentPaymentPage + 1)" :disabled="currentPaymentPage === paymentPages"><i class="ph ph-caret-right"></i></button></div></div>
                </section>

                <section x-show="activeTab === 'budgets'" style="display:none">
                    <div class="dean-finance-table-meta"><span>Event budget register</span><span x-text="filteredBudgets.length + ' matching records'"></span></div>
                    <div class="dean-finance-table-wrap">
                        <table class="dean-finance-table dean-finance-table--budgets">
                            <thead><tr><th>Event</th><th>Schedule</th><th>Venue</th><th>Budget</th><th>Status</th><th>Owner</th></tr></thead>
                            <tbody>
                                @forelse($eventBudgets ?? [] as $eventBudget)
                                    <tr x-show="visibleBudgetIds.has('{{ $eventBudget->id }}')">
                                        <td><div class="dean-finance-event"><span><i class="ph ph-calendar-dot"></i></span><div><strong>{{ $eventBudget->title }}</strong><small>Event #{{ $eventBudget->id }}</small></div></div></td>
                                        <td><strong class="dean-finance-date">{{ $eventBudget->start_date ? \Carbon\Carbon::parse($eventBudget->start_date)->format('M d, Y') : 'To be confirmed' }}</strong></td>
                                        <td><span class="dean-finance-muted">{{ $eventBudget->venue ?: 'Venue to be confirmed' }}</span></td>
                                        <td><strong class="dean-finance-amount">&#8369;{{ number_format($eventBudget->planned_budget ?? 0, 2) }}</strong></td>
                                        <td><span class="dean-finance-status dean-finance-status--{{ $eventBudget->approval_status === 'approved' ? 'approved' : ($eventBudget->approval_status === 'rejected' ? 'rejected' : 'pending') }}">{{ ucfirst($eventBudget->approval_status ?? 'pending') }}</span></td>
                                        <td><span class="dean-finance-muted">{{ $eventBudget->user ? $eventBudget->user->short_name : 'Organization admin' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><div class="dean-finance-empty"><span><i class="ph ph-wallet"></i></span><strong>No event budgets</strong><p>Event budgets will appear here once organizations submit them.</p></div></td></tr>
                                @endforelse
                                <tr x-show="filteredBudgets.length === 0 && budgets.length > 0"><td colspan="6"><div class="dean-finance-empty"><span><i class="ph ph-funnel"></i></span><strong>No matching budgets</strong><p>Try a different event name or status.</p></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="dean-finance-pagination" x-show="filteredBudgets.length > 0"><span>Showing <b x-text="((currentBudgetPage - 1) * perPage) + 1"></b>–<b x-text="Math.min(currentBudgetPage * perPage, filteredBudgets.length)"></b> of <b x-text="filteredBudgets.length"></b></span><div><button type="button" @click="goToPage('budgets', currentBudgetPage - 1)" :disabled="currentBudgetPage === 1"><i class="ph ph-caret-left"></i></button><template x-for="page in budgetPages" :key="page"><button type="button" @click="goToPage('budgets', page)" x-text="page" :class="page === currentBudgetPage ? 'is-current' : ''"></button></template><button type="button" @click="goToPage('budgets', currentBudgetPage + 1)" :disabled="currentBudgetPage === budgetPages"><i class="ph ph-caret-right"></i></button></div></div>
                </section>
            </main>

            <aside class="dean-finance-sidebar">
                <div class="dean-finance-sidebar__header"><div><span class="dean-finance-panel__eyebrow">FINANCE SNAPSHOT</span><h2>Report scope</h2></div><span class="dean-finance-sidebar__icon"><i class="ph ph-chart-pie"></i></span></div>
                <div class="dean-finance-sidebar__section"><div class="dean-finance-sidebar__label">REPORT CONTENT</div><div class="dean-finance-breakdown"><div><span><i class="ph ph-credit-card"></i>Completed payments</span><strong>{{ number_format($paymentsCount) }}</strong></div><div><span><i class="ph ph-calendar-check"></i>Budget records</span><strong>{{ number_format($budgetRecords->count()) }}</strong></div><div><span><i class="ph ph-check-circle"></i>Approved budgets</span><strong>{{ number_format($approvedBudgetCount) }}</strong></div></div></div>
                <div class="dean-finance-sidebar__section"><div class="dean-finance-sidebar__label">EXPORT NOTES</div><p class="dean-finance-sidebar__copy">The export button follows the active report tab and payment filters. Budget exports include the full event budget register.</p></div>
                <div class="dean-finance-sidebar__section dean-finance-sidebar__links"><a href="{{ route('dean.dashboard') }}"><span><i class="ph ph-gauge"></i></span><strong>Back to dashboard</strong><i class="ph ph-caret-right"></i></a><a href="{{ route('dean.content-overview') }}"><span><i class="ph ph-squares-four"></i></span><strong>Content overview</strong><i class="ph ph-caret-right"></i></a></div>
            </aside>
        </div>
    </div>

    <div id="finance-toast" class="dean-finance-toast hidden"><span id="finance-toast-icon"></span><div><strong id="finance-toast-title"></strong><p id="finance-toast-message"></p></div><button type="button" @click="document.getElementById('finance-toast').classList.add('hidden')"><i class="ph ph-x"></i></button></div>
</div>
<style>
    .dean-finance-page { --df-ink:#20212b; --df-muted:#727582; --df-line:#e7e8ed; --df-bg:#f7f7fa; --df-surface:#fff; --df-purple:var(--primary-color,#8238b3); --df-gold:var(--complementary-color,#d7ac28); color:var(--df-ink); background:var(--df-bg); min-height:calc(100vh - 64px); font-family:'Plus Jakarta Sans','Inter',ui-sans-serif,system-ui,sans-serif; }
    .dean-finance-page__shell { width:min(100%,1680px); margin:0 auto; padding:28px clamp(20px,2.4vw,42px) 42px; }
    .dean-finance-header { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:22px; }
    .dean-finance-eyebrow,.dean-finance-panel__eyebrow,.dean-finance-sidebar__label { color:var(--df-muted); font-size:10px; font-weight:800; letter-spacing:.14em; line-height:1.2; text-transform:uppercase; }
    .dean-finance-eyebrow { margin-bottom:8px; }
    .dean-finance-header h1 { margin:0; font-size:clamp(25px,2.1vw,34px); font-weight:800; letter-spacing:-.04em; line-height:1.1; }
    .dean-finance-header p { margin:7px 0 0; color:var(--df-muted); font-size:13px; line-height:1.5; }
    .dean-finance-header__actions { display:flex; align-items:center; gap:9px; flex-shrink:0; }
    .dean-finance-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:38px; padding:0 13px; border:1px solid transparent; border-radius:7px; font-size:11px; font-weight:750; text-decoration:none; cursor:pointer; transition:transform .18s ease,background .18s ease,border-color .18s ease; }
    .dean-finance-btn:hover { transform:translateY(-1px); }
    .dean-finance-btn--secondary { border-color:var(--df-line); background:var(--df-surface); color:#555967; }
    .dean-finance-btn--primary { background:var(--df-purple); color:#fff; box-shadow:0 4px 10px rgba(88,40,123,.16); }
    .dean-finance-btn--primary:hover { filter:brightness(.95); }
    .dean-finance-metrics { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
    .dean-finance-metric { display:flex; align-items:center; gap:14px; min-width:0; min-height:78px; padding:16px 20px; border:1px solid var(--df-line); border-radius:8px; background:var(--df-surface); box-shadow:0 1px 2px rgba(32,33,43,.025); }
    .dean-finance-metric__icon { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border-radius:7px; font-size:13px; }
    .dean-finance-metric > span:last-child { display:grid; min-width:0; }
    .dean-finance-metric small { color:#5c5f6a; font-size:11px; font-weight:750; }
    .dean-finance-metric strong { margin-top:2px; font-size:23px; letter-spacing:-.04em; line-height:1.05; }
    .dean-finance-metric em { margin-top:5px; overflow:hidden; color:var(--df-muted); font-size:10px; font-style:normal; text-overflow:ellipsis; white-space:nowrap; }
    .dean-finance-metric--green .dean-finance-metric__icon { background:#eaf8f1; color:#258355; }.dean-finance-metric--blue .dean-finance-metric__icon { background:#eaf2ff; color:#2563eb; }.dean-finance-metric--gold .dean-finance-metric__icon { background:#fff7df; color:#ae7a08; }.dean-finance-metric--purple .dean-finance-metric__icon { background:rgba(var(--primary-color-rgb,130,56,179),.1); color:var(--df-purple); }
    .dean-finance-tabs { display:flex; align-items:center; gap:3px; padding:4px; margin-bottom:10px; border:1px solid var(--df-line); border-radius:8px; background:#f0f0f4; }
    .dean-finance-tabs button { display:inline-flex; align-items:center; gap:8px; min-height:37px; padding:0 14px; border:0; border-radius:6px; background:transparent; color:#70727c; font-size:11px; font-weight:750; cursor:pointer; }
    .dean-finance-tabs button i { color:#9496a1; font-size:12px; }.dean-finance-tabs button b { display:inline-flex; align-items:center; justify-content:center; min-width:21px; height:19px; padding:0 5px; border-radius:10px; background:#e2e2e8; color:#666875; font-size:10px; }.dean-finance-tabs button.is-active { background:#fff; color:var(--df-purple); box-shadow:0 1px 3px rgba(32,33,43,.1); }.dean-finance-tabs button.is-active i { color:currentColor; }.dean-finance-tabs button.is-active b { background:#f0e7f7; color:var(--df-purple); }
    .dean-finance-workspace { display:grid; grid-template-columns:minmax(0,1fr) 292px; align-items:start; gap:10px; }.dean-finance-panel,.dean-finance-sidebar { border:1px solid var(--df-line); border-radius:9px; background:var(--df-surface); box-shadow:0 1px 2px rgba(32,33,43,.025); }.dean-finance-panel { min-width:0; overflow:hidden; }.dean-finance-panel__header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:18px 20px 14px; border-bottom:1px solid var(--df-line); }.dean-finance-panel__header h2,.dean-finance-sidebar h2 { margin:4px 0 0; font-size:17px; font-weight:800; letter-spacing:-.025em; }.dean-finance-panel__header p { margin:5px 0 0; color:var(--df-muted); font-size:11px; }.dean-finance-export-link { display:inline-flex; align-items:center; gap:7px; margin-top:4px; border:0; background:none; color:var(--df-purple); font-size:10px; font-weight:800; cursor:pointer; }.dean-finance-filterbar { display:flex; align-items:flex-end; gap:10px; padding:12px 20px; border-bottom:1px solid var(--df-line); background:#fbfbfc; }.dean-finance-filterbar label { display:grid; gap:5px; min-width:130px; }.dean-finance-filterbar label > span:first-child { color:#858791; font-size:9px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }.dean-finance-filterbar input,.dean-finance-filterbar select { min-height:32px; padding:0 9px; border:1px solid #dfe0e6; border-radius:6px; background:#fff; color:#4d4f59; font-size:10px; outline:none; }.dean-finance-filterbar input:focus,.dean-finance-filterbar select:focus { border-color:var(--df-purple); box-shadow:0 0 0 2px rgba(var(--primary-color-rgb,130,56,179),.1); }.dean-finance-filterbar .dean-finance-search { flex:1; }.dean-finance-input-wrap { position:relative; display:block; }.dean-finance-input-wrap i { position:absolute; left:10px; top:10px; color:#a2a4ad; font-size:10px; }.dean-finance-input-wrap input { width:100%; padding-left:27px; }.dean-finance-reset { min-height:32px; padding:0 9px; border:1px solid #dfe0e6; border-radius:6px; background:#fff; color:var(--df-purple); font-size:10px; font-weight:750; cursor:pointer; }.dean-finance-table-meta { display:flex; align-items:center; justify-content:space-between; padding:12px 20px 8px; color:#8a8c95; font-size:10px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }.dean-finance-table-meta span:last-child { color:#a0a2aa; font-size:9px; letter-spacing:0; font-weight:700; text-transform:none; }.dean-finance-table-wrap { overflow-x:auto; }.dean-finance-table { width:100%; border-collapse:collapse; }.dean-finance-table th { padding:9px 12px; border-bottom:1px solid var(--df-line); background:#fbfbfc; color:#83858f; font-size:9px; font-weight:800; letter-spacing:.08em; text-align:left; text-transform:uppercase; white-space:nowrap; }.dean-finance-table th:first-child,.dean-finance-table td:first-child { padding-left:20px; }.dean-finance-table th:last-child,.dean-finance-table td:last-child { padding-right:20px; }.dean-finance-table td { padding:10px 12px; border-bottom:1px solid #f0f0f3; color:#4d4f59; font-size:10px; vertical-align:middle; white-space:nowrap; }.dean-finance-table tbody tr:hover { background:#fafafd; }.dean-finance-person,.dean-finance-event { display:flex; align-items:center; gap:9px; }.dean-finance-person > span { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; flex:0 0 28px; border-radius:7px; background:rgba(var(--primary-color-rgb,130,56,179),.1); color:var(--df-purple); font-size:10px; font-weight:800; }.dean-finance-person div,.dean-finance-event div { display:grid; gap:2px; }.dean-finance-person strong,.dean-finance-event strong { color:#30313c; font-size:10px; }.dean-finance-person small,.dean-finance-event small,.dean-finance-time { color:#999ba4; font-size:9px; }.dean-finance-event > span { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:7px; background:#eaf2ff; color:#2563eb; font-size:11px; }.dean-finance-transaction { color:#696b76; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; font-size:9px; }.dean-finance-tag,.dean-finance-status { display:inline-flex; align-items:center; width:max-content; padding:4px 7px; border-radius:4px; font-size:9px; font-weight:800; line-height:1; }.dean-finance-tag--purple { background:rgba(var(--primary-color-rgb,130,56,179),.1); color:var(--df-purple); }.dean-finance-amount { color:#28855a; font-size:10px; }.dean-finance-method { display:inline-flex; align-items:center; gap:5px; color:#666874; font-size:9px; }.dean-finance-method i { color:#8b8d97; }.dean-finance-date { display:block; color:#4d4f59; font-size:10px; }.dean-finance-muted { color:#777985; font-size:9px; }.dean-finance-status--approved { background:#eaf8f1; color:#218252; }.dean-finance-status--pending { background:#fff5df; color:#a66b00; }.dean-finance-status--rejected { background:#fff0f0; color:#c93636; }.dean-finance-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:260px; padding:30px 20px; text-align:center; }.dean-finance-empty span { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; margin-bottom:9px; border-radius:8px; background:#f1f1f5; color:#a6a8b1; }.dean-finance-empty strong { color:#4b4d58; font-size:12px; }.dean-finance-empty p { margin:4px 0 0; color:#888a93; font-size:10px; }.dean-finance-pagination { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 20px 14px; border-top:1px solid #f0f0f3; color:#92949d; font-size:10px; }.dean-finance-pagination b { color:#555762; }.dean-finance-pagination > div { display:flex; align-items:center; gap:4px; }.dean-finance-pagination button { display:inline-flex; align-items:center; justify-content:center; min-width:26px; height:25px; padding:0 7px; border:1px solid #dfe0e6; border-radius:5px; background:#fff; color:#686a75; font-size:10px; cursor:pointer; }.dean-finance-pagination button.is-current { border-color:var(--df-purple); background:var(--df-purple); color:#fff; }.dean-finance-pagination button:disabled { cursor:not-allowed; opacity:.45; }
    .dean-finance-sidebar { padding:18px; }.dean-finance-sidebar__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:17px; }.dean-finance-sidebar__icon { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:7px; background:rgba(var(--primary-color-rgb,130,56,179),.1); color:var(--df-purple); font-size:12px; }.dean-finance-sidebar__section { padding:17px 0 0; }.dean-finance-breakdown { display:grid; gap:11px; margin-top:12px; }.dean-finance-breakdown > div { display:flex; align-items:center; justify-content:space-between; color:#666874; font-size:10px; }.dean-finance-breakdown span { display:inline-flex; align-items:center; gap:8px; }.dean-finance-breakdown i { width:15px; color:#92949d; text-align:center; }.dean-finance-breakdown strong { color:#343540; font-size:11px; }.dean-finance-sidebar__copy { margin:10px 0 0; color:#82848e; font-size:10px; line-height:1.6; }.dean-finance-sidebar__links { display:grid; gap:2px; border-top:1px solid var(--df-line); }.dean-finance-sidebar__links a { display:flex; align-items:center; gap:9px; padding:8px 0; color:inherit; text-decoration:none; }.dean-finance-sidebar__links a > span { display:inline-flex; align-items:center; justify-content:center; width:27px; height:27px; border-radius:6px; background:rgba(var(--primary-color-rgb,130,56,179),.1); color:var(--df-purple); font-size:11px; }.dean-finance-sidebar__links strong { flex:1; color:#4d4f59; font-size:10px; }.dean-finance-sidebar__links a > i { color:#b3b5bd; font-size:9px; }.dean-finance-sidebar__links a:hover strong { color:var(--df-purple); }
    .dean-finance-toast { position:fixed; right:20px; top:20px; z-index:9999; display:flex; align-items:center; gap:10px; min-width:280px; max-width:370px; padding:11px 12px; border:1px solid var(--df-line); border-left:3px solid; border-radius:8px; background:#fff; box-shadow:0 10px 26px rgba(32,33,43,.14); }.dean-finance-toast.hidden { display:none !important; }.dean-finance-toast--success { border-left-color:#2a9360; }.dean-finance-toast--error { border-left-color:#d04343; }.dean-finance-toast > span { display:inline-flex; align-items:center; justify-content:center; width:27px; height:27px; flex:0 0 27px; border-radius:6px; background:#f1f1f5; color:#777985; font-size:11px; }.dean-finance-toast--success > span { background:#eaf8f1; color:#2a9360; }.dean-finance-toast--error > span { background:#fff0f0; color:#d04343; }.dean-finance-toast div { min-width:0; flex:1; }.dean-finance-toast strong { display:block; color:#383944; font-size:11px; }.dean-finance-toast p { margin:3px 0 0; color:#81838d; font-size:10px; line-height:1.35; }.dean-finance-toast button { border:0; background:none; color:#a1a3ab; cursor:pointer; }
    @media (max-width:1180px) { .dean-finance-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }.dean-finance-workspace { grid-template-columns:minmax(0,1fr); }.dean-finance-sidebar { display:grid; grid-template-columns:minmax(0,1fr) minmax(220px,.8fr); column-gap:22px; }.dean-finance-sidebar__header,.dean-finance-sidebar__links { grid-column:1/-1; }.dean-finance-sidebar__section { padding-top:15px; } }
    @media (max-width:760px) { .dean-finance-page__shell { padding:20px 14px 30px; }.dean-finance-header { align-items:flex-start; flex-direction:column; gap:16px; }.dean-finance-header__actions { width:100%; }.dean-finance-btn { flex:1; }.dean-finance-metrics { grid-template-columns:1fr; }.dean-finance-tabs { overflow-x:auto; }.dean-finance-tabs button { flex:1 0 auto; }.dean-finance-panel__header { padding-left:15px; padding-right:15px; }.dean-finance-filterbar { align-items:stretch; flex-wrap:wrap; padding-left:15px; padding-right:15px; }.dean-finance-filterbar label { flex:1; min-width:120px; }.dean-finance-filterbar .dean-finance-search { flex-basis:100%; }.dean-finance-table-meta { padding-left:15px; padding-right:15px; }.dean-finance-table th:first-child,.dean-finance-table td:first-child { padding-left:15px; }.dean-finance-table th:last-child,.dean-finance-table td:last-child { padding-right:15px; }.dean-finance-pagination { align-items:flex-start; flex-direction:column; padding-left:15px; padding-right:15px; }.dean-finance-sidebar { display:block; }.dean-finance-sidebar__section { padding-top:17px; } }
</style>
@endsection
