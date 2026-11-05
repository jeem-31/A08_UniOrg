@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Manage Payments - Admin')

@section('content')

<!-- Receipt Modal -->
<div id="receiptModal" class="fixed inset-0 bg-gray-600/50 modal-backdrop overflow-y-auto h-full w-full z-[9999] flex items-center justify-center hidden">
    <div class="relative w-full max-w-4xl mx-auto bg-white rounded-lg shadow-lg m-4 max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center px-5 py-3 border-b flex-shrink-0">
            <h3 class="text-base font-semibold text-gray-900">Payment Receipt</h3>
            <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div id="receiptModalContent" class="overflow-y-auto flex-1 p-5">
        </div>
    </div>
</div>

<!-- Approve Payment Modal -->
<div id="approveModal" class="fixed inset-0 bg-black/50 modal-backdrop flex items-center justify-center z-[9999] hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-900">Approve Payment</h3>
            <button onclick="closeApproveModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <i class="ph ph-check-circle text-lg text-green-600"></i>
                </div>
                <p class="text-sm text-gray-700">
                    Approve payment from <strong id="approveStudentName"></strong>?<br>
                    <span class="text-gray-500 text-xs">The student will be notified.</span>
                </p>
            </div>
            <div class="flex gap-2">
                <button onclick="closeApproveModal()" class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button onclick="confirmApprove()" id="approveButton" class="flex-1 px-3 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ph ph-check mr-1" id="approveIcon"></i><span id="approveText">Approve</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Payment Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 modal-backdrop flex items-center justify-center z-[9999] hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="px-5 py-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="ph ph-warning text-lg text-red-600"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Delete Payment</h3>
                    <p class="text-sm text-gray-600 mt-0.5">Delete "<span id="deleteAnnouncementTitle" class="font-semibold"></span>"? This cannot be undone.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button onclick="closeDeleteModal()" class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition">
                    Cancel
                </button>
                <form id="deleteAnnouncementForm" method="POST" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" id="deleteConfirmBtn" class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition disabled:opacity-50">
                        <i id="deleteConfirmIcon" class="ph ph-trash mr-1"></i><span id="deleteConfirmText">Delete</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Payment Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black/50 modal-backdrop flex items-center justify-center z-[9999] hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-900">Reject Payment</h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-gray-700 mb-3">
                Reject payment from <strong id="rejectStudentName"></strong>.
            </p>
            <div class="mb-3">
                <label class="block text-gray-700 text-xs font-semibold mb-1.5">
                    Reason for Rejection <span class="text-red-500">*</span>
                </label>
                <textarea id="rejectReason" rows="3" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="E.g., Receipt is unclear, wrong amount"></textarea>
            </div>
            <div class="flex gap-2">
                <button onclick="closeRejectModal()" class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button onclick="submitRejection()" id="rejectButton" class="flex-1 px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 transition disabled:opacity-50">
                    <i class="ph ph-x mr-1" id="rejectIcon"></i><span id="rejectText">Reject</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Image Modal -->
<div id="receiptImageModal" class="fixed inset-0 bg-black/75 modal-backdrop flex items-center justify-center z-[9999] hidden">
    <div class="relative w-full max-w-3xl mx-auto bg-white rounded-lg shadow-lg p-4 m-4">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-semibold text-gray-900">GCash Payment Receipt</h3>
            <button onclick="closeReceiptImageModal()" class="text-gray-400 hover:text-gray-600">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>
        <div class="overflow-y-auto max-h-[70vh]">
            <img id="receiptImage" src="" alt="Receipt" class="w-full rounded">
        </div>
    </div>
</div>

<div class="w-full" data-admin-payments-page x-data="{
    showManualDetailsModal: false,
    showCreateModal: false,
    showManualModal: false,
    activeTab: '{{ request('tab', 'payments') }}',
    activeStatus: '{{ request('status', 'all') }}',
    activeMethod: '{{ request('method', '') }}',
    currentPage: 1,
    perPage: 10,
    filterFromDate: '{{ request('from_date', '') }}',
    filterToDate: '{{ request('to_date', '') }}',

    allPayments: {{ Js::from($allPayments) }},
    announcements: {{ Js::from($announcements) }},

    get pendingVerifications() {
        return this.allPayments.filter(function(p) {
            return p.verification_status === 'pending' && p.status !== 'completed';
        });
    },

    get filteredPayments() {
        var list = this.allPayments.slice();
        if (this.activeMethod) {
            list = list.filter(function(p) { return p.payment_method === this.activeMethod; }.bind(this));
        } else if (this.activeStatus !== 'all') {
            list = list.filter(function(p) { return p.status === this.activeStatus; }.bind(this));
        }
        if (this.filterFromDate) {
            list = list.filter(function(p) { return p.paid_at && p.paid_at.substring(0, 10) >= this.filterFromDate; }.bind(this));
        }
        if (this.filterToDate) {
            list = list.filter(function(p) { return p.paid_at && p.paid_at.substring(0, 10) <= this.filterToDate; }.bind(this));
        }
        return list;
    },

    get paymentsPageCount() { return Math.ceil(this.filteredPayments.length / this.perPage) || 1; },
    get paginatedPayments() {
        var start = (this.currentPage - 1) * this.perPage;
        return this.filteredPayments.slice(start, start + this.perPage);
    },
    get visiblePaymentIds() { return new Set(this.paginatedPayments.map(function(p) { return String(p.transaction_id); })); },

    get pendingPageCount() { return Math.ceil(this.pendingVerifications.length / this.perPage) || 1; },
    get paginatedPending() {
        var start = (this.currentPage - 1) * this.perPage;
        return this.pendingVerifications.slice(start, start + this.perPage);
    },
    get visiblePendingIds() { return new Set(this.paginatedPending.map(function(p) { return String(p.id); })); },

    get announcementsPageCount() { return Math.ceil(this.announcements.length / this.perPage) || 1; },
    get paginatedAnnouncements() {
        var start = (this.currentPage - 1) * this.perPage;
        return this.announcements.slice(start, start + this.perPage);
    },
    get visibleAnnouncementIds() { return new Set(this.paginatedAnnouncements.map(function(a) { return String(a.id); })); },

    get statsPosted() { return this.announcements.length; },
    get statsCompleted() { return {{ $stats['completed'] }}; },
    get statsRevenue() { return {{ $stats['total_revenue'] }}; },

    paymentCategoryClass(category) {
        var classes = {
            tour_fee: 'bg-purple-100 text-purple-800',
            membership_fee: 'bg-blue-100 text-blue-800',
            event_fee: 'bg-yellow-100 text-yellow-800',
            document_fee: 'bg-indigo-100 text-indigo-800',
            medical_fee: 'bg-pink-100 text-pink-800',
            project_contribution: 'bg-green-100 text-green-800',
            other: 'bg-gray-100 text-gray-800'
        };

        return classes[category] || classes.other;
    },

    paymentCategoryName(category) {
        return String(category || 'other').replace(/_/g, ' ').replace(/\b\w/g, function(letter) {
            return letter.toUpperCase();
        });
    },

    formatPaymentAmount(amount) {
        return new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(Number(amount) || 0);
    },

    formatPaymentDate(date) {
        var dateValue = String(date || '').slice(0, 10);
        if (!dateValue) return '';

        return new Date(dateValue + 'T00:00:00').toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        });
    },

    handleLocalPaymentCreated(event) {
        var payment = event.detail;
        if (!payment) return;

        var existingIndex = this.announcements.findIndex(function(announcement) {
            return String(announcement.id) === String(payment.id);
        });

        if (existingIndex === -1) {
            this.announcements.unshift(payment);
        } else {
            this.announcements.splice(existingIndex, 1, payment);
        }

        this.switchTab('announcements');
        this.showCreateModal = false;
    },

    handleLocalPaymentUpdated(event) {
        var payment = event.detail;
        if (!payment) return;

        var existingIndex = this.announcements.findIndex(function(announcement) {
            return String(announcement.id) === String(payment.id);
        });

        if (existingIndex === -1) {
            this.announcements.unshift(payment);
        } else {
            this.announcements.splice(existingIndex, 1, payment);
        }
    },

    handleLocalPaymentDeleted(event) {
        var paymentId = event.detail;
        var existingIndex = this.announcements.findIndex(function(announcement) {
            return String(announcement.id) === String(paymentId);
        });

        if (existingIndex !== -1) {
            this.announcements.splice(existingIndex, 1);
        }

        if (this.currentPage > this.announcementsPageCount) {
            this.currentPage = this.announcementsPageCount;
        }
    },

    init() {
        var self = this;
        this.$watch('activeTab', function() { self.currentPage = 1; });
        this.$watch('activeStatus', function() { self.currentPage = 1; });
        this.$watch('activeMethod', function() { self.currentPage = 1; });
        this.$watch('filterFromDate', function() { self.currentPage = 1; });
        this.$watch('filterToDate', function() { self.currentPage = 1; });
        this.onLocalPaymentCreated = this.handleLocalPaymentCreated.bind(this);
        this.onLocalPaymentUpdated = this.handleLocalPaymentUpdated.bind(this);
        this.onLocalPaymentDeleted = this.handleLocalPaymentDeleted.bind(this);
        window.addEventListener('admin-payment-created-locally', this.onLocalPaymentCreated);
        window.addEventListener('admin-payment-updated-locally', this.onLocalPaymentUpdated);
        window.addEventListener('admin-payment-deleted-locally', this.onLocalPaymentDeleted);
    },

    destroy() {
        window.removeEventListener('admin-payment-created-locally', this.onLocalPaymentCreated);
        window.removeEventListener('admin-payment-updated-locally', this.onLocalPaymentUpdated);
        window.removeEventListener('admin-payment-deleted-locally', this.onLocalPaymentDeleted);
    },

    get emptyMessage() {
        if (this.activeTab === 'payments') {
            if (this.activeMethod === 'manual') return 'No manual payments yet';
            if (this.activeMethod === 'gcash') return 'No GCash payments yet';
            if (this.activeStatus === 'completed') return 'No completed payments yet';
            return 'No payments yet';
        }
        if (this.activeTab === 'pending_verifications') return 'No pending verifications';
        if (this.activeTab === 'announcements') return 'No posted payments yet';
        return '';
    },

    isActive(tab, status, method) {
        if (tab !== this.activeTab) return false;
        if (tab === 'payments') {
            if (this.activeMethod) return method === this.activeMethod;
            return !method && this.activeStatus === (status || 'all');
        }
        return true;
    },

    switchTab(tab, status, method) {
        this.activeTab = tab;
        this.activeStatus = status || (tab === 'payments' ? 'all' : '');
        this.activeMethod = method || '';
        this.currentPage = 1;
        var params = new URLSearchParams();
        params.set('tab', this.activeTab);
        if (this.activeTab === 'payments' || this.activeTab === 'pending_verifications') {
            if (this.activeStatus && this.activeStatus !== 'all') params.set('status', this.activeStatus);
            if (this.activeMethod) params.set('method', this.activeMethod);
        }
        window.history.replaceState({ tabNavigation: true }, '', window.location.pathname + '?' + params.toString());
    },

    goToPage(type, page) {
        var total = type === 'announcements' ? this.announcementsPageCount : (type === 'pending' ? this.pendingPageCount : this.paymentsPageCount);
        if (page < 1 || page > total) return;
        this.currentPage = page;
    },

    resetFilters() {
        this.filterFromDate = '';
        this.filterToDate = '';
        this.activeStatus = 'all';
        this.activeMethod = '';
    }
}">
    <!-- Read-Only Notice Banner -->
    @hasReadOnly('payments')
    <div class="bg-yellow-50 border-l-4 border-yellow-400 px-4 py-2.5 mb-4">
        <div class="flex items-center gap-2">
            <i class="ph ph-eye text-yellow-600 text-sm"></i>
            <p class="text-sm text-yellow-700">You have view-only access to this module.</p>
        </div>
    </div>
    @endHasReadOnly

    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Payments</h1>
            <p class="text-sm text-gray-500">Review and verify student payments</p>
        </div>
        <div class="flex items-center gap-2">
            @hasFullAccess('payments')
            <button @click="showCreateModal = true" style="background-color: var(--primary-color);" class="text-white px-3.5 py-2 rounded-md text-sm font-medium hover:opacity-90 transition flex items-center gap-1.5">
                <i class="ph ph-plus text-xs"></i> Create
            </button>
            <button @click="showManualModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 rounded-md text-sm font-medium transition flex items-center gap-1.5">
                <i class="ph ph-money-wavy text-xs"></i> Manual
            </button>
            @endHasFullAccess
            <button onclick="exportPayments(this)" class="bg-gray-600 hover:bg-gray-700 text-white px-3.5 py-2 rounded-md text-sm font-medium transition flex items-center gap-1.5">
                <i class="ph ph-file-xls text-xs"></i> <span>Export</span>
            </button>
        </div>
    </div>

    <!-- Success/Error Toast -->
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="fixed top-6 right-6 z-50 bg-white border-l-4 border-green-500 rounded-lg shadow-lg px-4 py-3 flex items-center gap-3 max-w-sm" style="display: none;">
        <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
            <i class="ph ph-check-circle text-sm text-green-600"></i>
        </div>
        <p class="text-sm text-gray-700">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-gray-400 hover:text-gray-600 flex-shrink-0"><i class="ph ph-x"></i></button>
    </div>
    @endif
    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="fixed top-6 right-6 z-50 bg-white border-l-4 border-red-500 rounded-lg shadow-lg px-4 py-3 flex items-center gap-3 max-w-sm" style="display: none;">
        <div class="h-8 w-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
            <i class="ph ph-warning-circle text-sm text-red-600"></i>
        </div>
        <p class="text-sm text-gray-700">{{ session('error') }}</p>
        <button @click="show = false" class="ml-auto text-gray-400 hover:text-gray-600 flex-shrink-0"><i class="ph ph-x"></i></button>
    </div>
    @endif

    <!-- Stats Bar -->
    <div class="flex items-stretch gap-px bg-gray-200 rounded-md overflow-hidden mb-4 border border-gray-200">
        <div class="flex-1 bg-white px-4 py-2.5 flex items-center gap-3">
            <div style="background-color: color-mix(in srgb, var(--primary-color) 12%, transparent);" class="h-8 w-8 rounded flex items-center justify-center flex-shrink-0">
                <i style="color: var(--complementary-color);" class="ph ph-megaphone text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 leading-tight">Posted</p>
                <p class="text-base font-bold text-gray-900 leading-tight" x-text="statsPosted"></p>
            </div>
        </div>
        <div class="flex-1 bg-white px-4 py-2.5 flex items-center gap-3">
            <div style="background-color: color-mix(in srgb, var(--primary-color) 12%, transparent);" class="h-8 w-8 rounded flex items-center justify-center flex-shrink-0">
                <i style="color: var(--complementary-color);" class="ph ph-check-circle text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 leading-tight">Completed</p>
                <p class="text-base font-bold text-gray-900 leading-tight" x-text="statsCompleted"></p>
            </div>
        </div>
        <div class="flex-1 bg-white px-4 py-2.5 flex items-center gap-3">
            <div style="background-color: color-mix(in srgb, var(--primary-color) 12%, transparent);" class="h-8 w-8 rounded flex items-center justify-center flex-shrink-0">
                <i style="color: var(--complementary-color);" class="ph ph-currency-circle-dollar text-sm"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500 leading-tight">Revenue</p>
                <p class="text-base font-bold text-gray-900 leading-tight" x-text="'₱' + Number(statsRevenue).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
            </div>
        </div>
    </div>

    <!-- Tabs + Filters -->
    <div class="flex items-center justify-between mb-4 border-b border-gray-200">
        <nav class="-mb-px flex gap-0.5">
            <a href="#" @click.prevent="switchTab('payments', 'all', '')"
               :class="isActive('payments', 'all', '') ? 'border-[var(--primary-color)] text-[var(--primary-color)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
               class="whitespace-nowrap py-2.5 px-3 border-b-2 text-xs font-medium transition flex items-center gap-1">
                <i class="ph ph-list text-xs"></i>All
            </a>
            <a href="#" @click.prevent="switchTab('payments', 'completed', '')"
               :class="isActive('payments', 'completed', '') ? 'border-[var(--primary-color)] text-[var(--primary-color)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
               class="whitespace-nowrap py-2.5 px-3 border-b-2 text-xs font-medium transition flex items-center gap-1">
                <i class="ph ph-check-circle text-xs"></i>Completed
            </a>
            <a href="#" @click.prevent="switchTab('payments', '', 'manual')"
               :class="isActive('payments', '', 'manual') ? 'border-[var(--primary-color)] text-[var(--primary-color)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
               class="whitespace-nowrap py-2.5 px-3 border-b-2 text-xs font-medium transition flex items-center gap-1">
                <i class="ph ph-hand-coins text-xs"></i>Manual
            </a>
            <a href="#" @click.prevent="switchTab('payments', '', 'gcash')"
               :class="isActive('payments', '', 'gcash') ? 'border-[var(--primary-color)] text-[var(--primary-color)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
               class="whitespace-nowrap py-2.5 px-3 border-b-2 text-xs font-medium transition flex items-center gap-1">
                <i class="ph ph-device-mobile text-xs"></i>GCash
            </a>
            <a href="#" @click.prevent="switchTab('pending_verifications', '', '')"
               :class="isActive('pending_verifications', '', '') ? 'border-[var(--primary-color)] text-[var(--primary-color)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
               class="whitespace-nowrap py-2.5 px-3 border-b-2 text-xs font-medium transition flex items-center gap-1">
                <i class="ph ph-clock text-xs"></i>Pending
            </a>
            <div class="border-l border-gray-300 mx-1.5 self-center" style="height: 18px;"></div>
            <a href="#" @click.prevent="switchTab('announcements', '', '')"
               :class="isActive('announcements', '', '') ? 'border-[var(--primary-color)] text-[var(--primary-color)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
               class="whitespace-nowrap py-2.5 px-3 border-b-2 text-xs font-medium transition flex items-center gap-1">
                <i class="ph ph-megaphone text-xs"></i>Posted
            </a>
        </nav>
        <div class="flex items-center gap-1.5 pb-2.5">
            <input type="date" id="filterFromDate" x-model="filterFromDate" class="px-2 py-1.5 border border-gray-300 rounded text-xs w-32 focus:outline-none focus:border-[var(--primary-color)]" placeholder="From">
            <span class="text-gray-400 text-xs">-</span>
            <input type="date" id="filterToDate" x-model="filterToDate" class="px-2 py-1.5 border border-gray-300 rounded text-xs w-32 focus:outline-none focus:border-[var(--primary-color)]" placeholder="To">
            <button @click="filterFromDate = ''; filterToDate = ''" x-show="filterFromDate || filterToDate" class="px-2.5 py-1.5 bg-gray-500 text-white rounded text-xs hover:bg-gray-600 transition" title="Clear filter">
                <i class="ph ph-x"></i>
            </button>
        </div>
    </div>

    <!-- ==================== PAYMENTS TAB ==================== -->
    <div x-show="activeTab === 'payments' || activeTab === 'pending_verifications'">
        <div x-show="activeTab === 'payments'">
            <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Student</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Transaction ID</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Category</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Amount</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Method</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Date</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($allPayments as $payment)
                            <tr x-show="visiblePaymentIds.has('{{ $payment->transaction_id }}')" class="hover:bg-gray-50 payment-row" data-paid-date="{{ $payment->paid_at ? $payment->paid_at->format('Y-m-d') : '' }}">
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ name_with_suffix($payment->user->first_name ?? '', null, $payment->user->last_name ?? '', $payment->user->suffix ?? '') }}</div>
                                    <div class="text-xs text-gray-500">{{ $payment->user->student_id ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 font-mono">{{ $payment->transaction_id }}</div>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                        {{ $payment->category === 'tour_fee' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $payment->category === 'membership_fee' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $payment->category === 'event_fee' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $payment->category === 'document_fee' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $payment->category === 'medical_fee' ? 'bg-pink-100 text-pink-800' : '' }}
                                        {{ $payment->category === 'project_contribution' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $payment->category === 'other' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $payment->category)) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm font-semibold text-gray-900">₱{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                        {{ $payment->payment_method === 'gcash' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $payment->payment_method === 'manual' ? 'bg-purple-100 text-purple-800' : '' }}">
                                        {{ ucfirst($payment->payment_method) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm text-gray-600">
                                    {{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : 'N/A' }}
                                    @if($payment->paid_at)
                                    <div class="text-xs text-gray-400">{{ $payment->paid_at->format('h:i A') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm">
                                    <div class="flex items-center gap-2.5">
                                        @if($payment->status === 'completed')
                                        <a href="javascript:void(0);" onclick="showReceiptModal({{ $payment->id }}); return false;" style="color: var(--primary-color);" class="hover:opacity-75 text-xs font-medium flex items-center gap-1">
                                            <i class="ph ph-eye text-xs"></i> View
                                        </a>
                                        <a href="{{ route('payments.download-receipt', $payment->id) }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs font-medium flex items-center gap-1">
                                            <i class="ph ph-download-simple text-xs"></i> Receipt
                                        </a>
                                        @else
                                        <span class="text-xs text-gray-400">Receipt only when paid</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                        <tr x-show="filteredPayments.length === 0">
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500" x-text="emptyMessage"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Payments Pagination -->
            <div class="mt-3 flex items-center justify-between" x-show="filteredPayments.length > 0">
                <div class="text-xs text-gray-500">
                    <span x-text="((currentPage - 1) * perPage) + 1"></span>-<span x-text="Math.min(currentPage * perPage, filteredPayments.length)"></span> of <span x-text="filteredPayments.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" @click="goToPage('payments', currentPage - 1)" :disabled="currentPage === 1" class="px-2.5 py-1.5 rounded border text-xs font-medium" :class="currentPage === 1 ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50'">
                        <i class="ph ph-caret-left text-2xs"></i>
                    </button>
                    <template x-for="page in paymentsPageCount" :key="page">
                        <button type="button" @click="goToPage('payments', page)" x-text="page" :class="'px-2.5 py-1.5 rounded border text-xs font-medium ' + (page === currentPage ? 'is-current' : '')"></button>
                    </template>
                    <button type="button" @click="goToPage('payments', currentPage + 1)" :disabled="currentPage === paymentsPageCount" class="px-2.5 py-1.5 rounded border text-xs font-medium" :class="currentPage === paymentsPageCount ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50'">
                        <i class="ph ph-caret-right text-2xs"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================== PENDING VERIFICATIONS TAB ==================== -->
        <div x-show="activeTab === 'pending_verifications'">
            <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
                <div class="px-3 py-2 bg-orange-50 border-b border-orange-200 flex items-center gap-2">
                    <i class="ph ph-clock text-orange-600 text-xs"></i>
                    <span class="text-sm font-semibold text-orange-800">GCash Payments Pending Verification</span>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Student</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Reference #</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Category</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Amount</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Receipt</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Submitted</th>
                            <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($allPayments->where('verification_status', 'pending')->where('status', '!=', 'completed') as $payment)
                            <tr x-show="visiblePendingIds.has('{{ $payment->id }}')" class="hover:bg-gray-50">
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ name_with_suffix($payment->user->first_name ?? '', null, $payment->user->last_name ?? '', $payment->user->suffix ?? '') }}</div>
                                    <div class="text-xs text-gray-500">{{ $payment->user->student_id ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    @php
                                        $refNum = $payment->reference_number ?? 'N/A';
                                        if ($refNum !== 'N/A' && strlen($refNum) === 13) {
                                            $refNum = substr($refNum, 0, 4) . ' ' . substr($refNum, 4, 3) . ' ' . substr($refNum, 7);
                                        }
                                    @endphp
                                    <div style="color: var(--primary-color);" class="text-sm font-mono font-medium">{{ $refNum }}</div>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="text-xs text-gray-500 mb-0.5">{{ $payment->payment->title ?? 'N/A' }}</div>
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                        {{ $payment->category === 'tour_fee' ? 'bg-purple-100 text-purple-800' : '' }}
                                        {{ $payment->category === 'membership_fee' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $payment->category === 'event_fee' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $payment->category === 'document_fee' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $payment->category === 'medical_fee' ? 'bg-pink-100 text-pink-800' : '' }}
                                        {{ $payment->category === 'project_contribution' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $payment->category === 'other' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $payment->category)) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm font-semibold text-gray-900">₱{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    @if($payment->gcash_receipt_path)
                                        <button onclick="showReceiptImageModal('{{ storage_url($payment->gcash_receipt_path) }}')" class="text-blue-600 hover:text-blue-800 text-xs font-medium flex items-center gap-1">
                                            <i class="ph ph-image text-xs"></i> View
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">No receipt uploaded</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-xs text-gray-500">{{ $payment->created_at->format('M d, Y h:i A') }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    @hasFullAccess('payments')
                                    <div class="flex items-center gap-2.5">
                                        <button onclick="showApproveModal({{ $payment->id }}, '{{ name_with_suffix($payment->user->first_name ?? '', null, $payment->user->last_name ?? '', $payment->user->suffix ?? '') }}')" class="text-green-600 hover:text-green-800 text-xs font-medium flex items-center gap-1">
                                            <i class="ph ph-check-circle text-xs"></i>Approve
                                        </button>
                                        <button onclick="showRejectModal({{ $payment->id }}, '{{ name_with_suffix($payment->user->first_name ?? '', null, $payment->user->last_name ?? '', $payment->user->suffix ?? '') }}')" class="text-red-600 hover:text-red-800 text-xs font-medium flex items-center gap-1">
                                            <i class="ph ph-x-circle text-xs"></i>Reject
                                        </button>
                                    </div>
                                    @endHasFullAccess
                                    @hasReadOnly('payments')
                                    <span class="text-xs text-gray-400"><i class="ph ph-eye mr-1"></i>View Only</span>
                                    @endHasReadOnly
                                </td>
                            </tr>
                        @empty
                        @endforelse
                        <tr x-show="pendingVerifications.length === 0">
                            <td colspan="7" class="px-3 py-10 text-center">
                                <i class="ph ph-check-circle text-gray-300 text-3xl mb-2"></i>
                                <p class="text-sm text-gray-500">No pending verifications</p>
                                <p class="text-xs text-gray-400">All GCash payments have been verified</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pending Pagination -->
            <div class="mt-3 flex items-center justify-between" x-show="pendingVerifications.length > 0">
                <div class="text-xs text-gray-500">
                    <span x-text="((currentPage - 1) * perPage) + 1"></span>-<span x-text="Math.min(currentPage * perPage, pendingVerifications.length)"></span> of <span x-text="pendingVerifications.length"></span>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" @click="goToPage('pending', currentPage - 1)" :disabled="currentPage === 1" class="px-2.5 py-1.5 rounded border text-xs font-medium" :class="currentPage === 1 ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50'">
                        <i class="ph ph-caret-left text-2xs"></i>
                    </button>
                    <template x-for="page in pendingPageCount" :key="page">
                        <button type="button" @click="goToPage('pending', page)" x-text="page" :class="'px-2.5 py-1.5 rounded border text-xs font-medium ' + (page === currentPage ? 'is-current' : '')"></button>
                    </template>
                    <button type="button" @click="goToPage('pending', currentPage + 1)" :disabled="currentPage === pendingPageCount" class="px-2.5 py-1.5 rounded border text-xs font-medium" :class="currentPage === pendingPageCount ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50'">
                        <i class="ph ph-caret-right text-2xs"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== ANNOUNCEMENTS TAB ==================== -->
    <div x-show="activeTab === 'announcements'">
        <div class="bg-white rounded-md border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Title</th>
                        <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Category</th>
                        <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Amount</th>
                        <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Due Date</th>
                        <th class="px-3 py-2.5 text-left text-2xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="announcement in paginatedAnnouncements" :key="'payment-announcement-' + announcement.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2.5">
                                <div class="text-sm font-medium text-gray-900" x-text="announcement.title"></div>
                                <div class="text-xs text-gray-500" x-text="announcement.description ? String(announcement.description).slice(0, 50) : ''"></div>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full" :class="paymentCategoryClass(announcement.category)">
                                    <span x-text="paymentCategoryName(announcement.category)"></span>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-sm font-semibold text-gray-900" x-text="'₱' + formatPaymentAmount(announcement.amount)"></td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-sm text-gray-600" x-text="formatPaymentDate(announcement.due_date)"></td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <button @click="showEditAnnouncementModal(announcement)" style="color: var(--primary-color);" class="hover:opacity-75 text-xs font-medium flex items-center gap-1">
                                        <i class="ph ph-pencil-simple text-xs"></i> Edit
                                    </button>
                                    <template x-if="announcement.is_required_membership_fee">
                                        <span class="text-xs font-medium text-gray-500 flex items-center gap-1" title="Required annual fee; it cannot be deleted">
                                            <i class="ph ph-lock-key text-xs"></i> Required
                                        </span>
                                    </template>
                                    <button x-show="!announcement.is_required_membership_fee" @click="showDeleteModal(announcement.id, announcement.title)" class="text-red-600 hover:text-red-800 text-xs font-medium flex items-center gap-1">
                                        <i class="ph ph-trash text-xs"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="announcements.length === 0">
                        <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">No posted payments yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Announcements Pagination -->
        <div class="mt-3 flex items-center justify-between" x-show="announcements.length > 0">
            <div class="text-xs text-gray-500">
                <span x-text="((currentPage - 1) * perPage) + 1"></span>-<span x-text="Math.min(currentPage * perPage, announcements.length)"></span> of <span x-text="announcements.length"></span>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" @click="goToPage('announcements', currentPage - 1)" :disabled="currentPage === 1" class="px-2.5 py-1.5 rounded border text-xs font-medium" :class="currentPage === 1 ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50'">
                    <i class="ph ph-caret-left text-2xs"></i>
                </button>
                <template x-for="page in announcementsPageCount" :key="page">
                    <button type="button" @click="goToPage('announcements', page)" x-text="page" :class="'px-2.5 py-1.5 rounded border text-xs font-medium ' + (page === currentPage ? 'is-current' : '')"></button>
                </template>
                <button type="button" @click="goToPage('announcements', currentPage + 1)" :disabled="currentPage === announcementsPageCount" class="px-2.5 py-1.5 rounded border text-xs font-medium" :class="currentPage === announcementsPageCount ? 'text-gray-300 border-gray-200 cursor-not-allowed' : 'text-gray-700 border-gray-300 hover:bg-gray-50'">
                    <i class="ph ph-caret-right text-2xs"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Create Payment Announcement Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showCreateModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-5 z-10">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Create Payment</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                </div>
                <form method="POST" action="{{ route('admin.payments.announcements.store') }}" enctype="multipart/form-data" id="createPaymentForm" x-data="{
                    isSubmitting: false,
                    async submitForm(event) {
                        event.preventDefault();
                        if (this.isSubmitting) return;
                        this.isSubmitting = true;
                        const btn = document.getElementById('createPaymentBtn');
                        const icon = document.getElementById('createPaymentIcon');
                        const text = document.getElementById('createPaymentText');
                        btn.disabled = true;
                        icon.className = 'ph ph-spinner ph-spin mr-1';
                        text.textContent = 'Creating...';
                        const form = event.target;
                        const formData = new FormData(form);
                        try {
                            const headers = {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            };
                            const socketId = window.Echo && typeof window.Echo.socketId === 'function'
                                ? window.Echo.socketId()
                                : null;
                            if (socketId) headers['X-Socket-ID'] = socketId;

                            const response = await fetch(form.action, {
                                method: 'POST',
                                body: formData,
                                headers: headers
                            });
                            const data = await response.json();
                            if (response.ok && data.success) {
                                window.dispatchEvent(new CustomEvent('admin-payment-created-locally', { detail: data.payment }));
                                window.clearAdminPageCache && window.clearAdminPageCache();
                                form.reset();
                                createQrImageData = null;
                                closeQRPreview();
                                document.getElementById('qrFileInfo').classList.add('hidden');
                                document.getElementById('viewCreateQrBtn').classList.add('hidden');
                                btn.disabled = false;
                                icon.className = 'ph ph-plus mr-1';
                                text.textContent = 'Create Payment';
                                showToast('success', data.message || 'Payment created successfully!');
                            } else {
                                btn.disabled = false;
                                icon.className = 'ph ph-plus mr-1';
                                text.textContent = 'Create Payment';
                                alert(data.message || 'Failed to create payment.');
                            }
                        } catch (error) {
                            btn.disabled = false;
                            icon.className = 'ph ph-plus mr-1';
                            text.textContent = 'Create Payment';
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        } finally {
                            this.isSubmitting = false;
                        }
                    }
                }" @submit="submitForm($event)">
                    @csrf
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Title</label>
                            <input type="text" name="title" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]" placeholder="e.g., 2nd Sem Tour Fee">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Category</label>
                            <select name="category" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]">
                                <option value="">Select</option>
                                <option value="tour_fee">Tour Fee</option>
                                <option value="event_fee">Event Fee</option>
                                <option value="document_fee">Document Fee</option>
                                <option value="medical_fee">Medical Fee</option>
                                <option value="other">Others</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Amount</label>
                            <input type="number" name="amount" step="0.01" min="0" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Due Date</label>
                            <input type="date" name="due_date" id="createDueDate" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">GCash Number</label>
                            <input type="text" name="gcash_number" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]" placeholder="09XXXXXXXXX" pattern="[0-9]{11}" inputmode="numeric" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">QR Code</label>
                            <div class="flex gap-2">
                                <div class="flex-1 min-w-0">
                                    <input type="file" name="qr_code" id="createQrCode" accept="image/*" class="hidden" onchange="previewCreateQR(event)">
                                    <label for="createQrCode" class="flex items-center justify-center w-full h-[34px] px-2 py-1.5 border border-dashed border-gray-300 rounded cursor-pointer hover:bg-gray-50">
                                        <span class="flex items-center gap-1.5">
                                            <i style="color: var(--primary-color);" class="ph ph-cloud-arrow-up text-xs"></i>
                                            <span class="text-xs text-gray-500">Upload</span>
                                        </span>
                                    </label>
                                    <div id="qrFileInfo" class="hidden mt-0.5 text-xs text-gray-600 flex items-center">
                                        <i class="ph ph-check-circle text-green-500 mr-1"></i>
                                        <span id="qrFileName" class="truncate"></span>
                                    </div>
                                </div>
                                <button type="button" id="viewCreateQrBtn" onclick="toggleQRPreview(event)" style="background-color: var(--primary-color);" class="hidden h-[34px] px-2.5 text-xs text-white font-medium rounded whitespace-nowrap"><i class="ph ph-eye mr-1"></i>View</button>
                                <div id="qrPreviewPopover" class="hidden absolute left-full ml-2 z-[100] bg-white rounded-lg shadow-xl border p-2" style="width: 250px; bottom: 0; border-color: var(--primary-color);">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs font-semibold text-gray-700">QR Preview</span>
                                        <button type="button" onclick="closeQRPreview()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-xs"></i></button>
                                    </div>
                                    <img id="qrPreviewPopoverImage" src="" alt="QR Preview" class="w-full h-auto rounded">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="showCreateModal = false" class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit" id="createPaymentBtn" style="background-color: var(--primary-color);" class="flex-1 text-white text-sm py-2 rounded font-medium hover:opacity-90 transition disabled:opacity-50">
                            <i id="createPaymentIcon" class="ph ph-plus mr-1"></i><span id="createPaymentText">Create Payment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Payment Announcement Modal -->
    <div id="editAnnouncementModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div onclick="closeEditAnnouncementModal()" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-5 z-10">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Edit Payment</h3>
                    <button onclick="closeEditAnnouncementModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                </div>
                <form id="editAnnouncementForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editAnnouncementId" name="announcement_id">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Title</label>
                            <input type="text" id="editTitle" name="title" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Category</label>
                            <select id="editCategory" name="category" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]">
                                <option value="">Select</option>
                                <option value="tour_fee">Tour Fee</option>
                                <option value="membership_fee">Membership Fee</option>
                                <option value="event_fee">Event Fee</option>
                                <option value="document_fee">Document Fee</option>
                                <option value="medical_fee">Medical Fee</option>
                                <option value="other">Others</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Amount</label>
                            <input type="number" id="editAmount" name="amount" step="0.01" min="0" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Due Date</label>
                            <input type="date" id="editDueDate" name="due_date" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]" min="">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">GCash Number</label>
                            <input type="text" id="editGcashNumber" name="gcash_number" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]" placeholder="09XXXXXXXXX" pattern="[0-9]{11}" inputmode="numeric" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">QR Code</label>
                            <div class="flex gap-2">
                                <div class="flex-1 min-w-0">
                                    <input type="file" name="qr_code" id="editQrCode" accept="image/*" class="hidden" onchange="previewEditQR(event)">
                                    <label for="editQrCode" class="flex items-center justify-center w-full h-[34px] px-2 py-1.5 border border-dashed border-gray-300 rounded cursor-pointer hover:bg-gray-50">
                                        <span class="flex items-center gap-1.5">
                                            <i style="color: var(--primary-color);" class="ph ph-cloud-arrow-up text-xs"></i>
                                            <span class="text-xs text-gray-500">Upload</span>
                                        </span>
                                    </label>
                                    <div id="editQrFileInfo" class="hidden mt-0.5 text-xs text-gray-600 flex items-center">
                                        <i class="ph ph-check-circle text-green-500 mr-1"></i>
                                        <span id="editQrFileName" class="truncate"></span>
                                    </div>
                                </div>
                                <button type="button" id="viewEditQrBtn" onclick="toggleEditQRPreview(event)" style="background-color: var(--primary-color);" class="hidden h-[34px] px-2.5 text-xs text-white font-medium rounded whitespace-nowrap"><i class="ph ph-eye mr-1"></i>View</button>
                                <div id="editQrPreviewPopover" class="hidden absolute left-full ml-2 z-[100] bg-white rounded-lg shadow-xl border p-2" style="width: 250px; bottom: 0; border-color: var(--primary-color);">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs font-semibold text-gray-700">QR Preview</span>
                                        <button type="button" onclick="closeEditQRPreview()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-xs"></i></button>
                                    </div>
                                    <img id="editQrPreviewPopoverImage" src="" alt="QR Preview" class="w-full h-auto rounded">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditAnnouncementModal()" class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit" id="editPaymentBtn" style="background-color: var(--primary-color);" class="flex-1 text-white text-sm py-2 rounded font-medium hover:opacity-90 transition disabled:opacity-50">
                            <i id="editPaymentIcon" class="ph ph-floppy-disk mr-1"></i><span id="editPaymentText">Update Payment</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manual Payment Modal - Select Payment -->
    <div x-show="showManualModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showManualModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-5 z-10">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-semibold text-gray-900">Select Payment</h3>
                    <button @click="showManualModal = false" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                </div>
                <div class="mb-3">
                    <label class="block text-gray-700 text-xs font-semibold mb-1.5">Posted Payments</label>
                    <div class="space-y-1.5 max-h-[280px] overflow-y-auto">
                        <template x-for="announcement in announcements" :key="'manual-payment-' + announcement.id">
                            <button type="button" @click.stop="
                                const announcementId = document.getElementById('selectedAnnouncementId');
                                const paymentTitle = document.getElementById('selectedPaymentTitle');
                                const amount = document.getElementById('paymentAmount');
                                const errorDiv = document.getElementById('manualPaymentError');
                                if (announcementId) announcementId.value = announcement.id;
                                if (paymentTitle) paymentTitle.textContent = announcement.title + ' - ₱' + formatPaymentAmount(announcement.amount);
                                if (amount) amount.value = announcement.amount;
                                if (errorDiv) errorDiv.classList.add('hidden');
                                showManualModal = false;
                                $nextTick(() => { showManualDetailsModal = true; });
                            " class="w-full px-3 py-2 border border-gray-200 rounded hover:bg-gray-50 text-left transition cursor-pointer">
                                <div class="font-medium text-gray-900 text-sm" x-text="announcement.title"></div>
                                <div class="text-xs text-gray-500" x-text="paymentCategoryName(announcement.category) + ' - ₱' + formatPaymentAmount(announcement.amount)"></div>
                                <div class="text-xs text-gray-400" x-text="'Due: ' + formatPaymentDate(announcement.due_date)"></div>
                            </button>
                        </template>
                        <p x-show="announcements.length === 0" class="text-gray-500 text-sm text-center py-3">No posted payments yet</p>
                    </div>
                </div>
                <button type="button" @click="showManualModal = false" class="w-full px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded hover:bg-gray-50 transition">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Manual Payment Modal - Enter Details -->
    <div x-show="showManualDetailsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div @click="showManualDetailsModal = false" class="fixed inset-0 bg-black/50 modal-backdrop"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-5 z-10">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Process Manual Payment</h3>
                    <button type="button" @click="showManualDetailsModal = false" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                </div>
                <form id="manualPaymentForm" x-data="{ submitting: false }" @submit.prevent="
                    submitting = true;
                    fetch('{{ route('admin.payments.manual.store') }}', {
                        method: 'POST',
                        headers: paymentRealtimeHeaders({ 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }),
                        body: new FormData($event.target)
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            showManualDetailsModal = false;
                            setTimeout(() => showToast('success', d.message), 300);
                            window.clearAdminPageCache && window.clearAdminPageCache();
                            window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
                        } else { submitting = false; showToast('error', d.message); }
                    })
                    .catch(e => { submitting = false; showToast('error', 'An error occurred.'); });
                ">
                    <input type="hidden" name="announcement_id" id="selectedAnnouncementId">
                    <div class="mb-3 px-3 py-2 bg-gray-50 rounded text-sm">
                        <span class="text-gray-500 text-xs">Selected:</span>
                        <span class="font-semibold text-gray-900" id="selectedPaymentTitle"></span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Student ID</label>
                            <input type="text" name="student_id" id="manualStudentId" required class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-[var(--primary-color)]" placeholder="0000-0000" maxlength="9" oninput="let v=this.value.replace(/[^0-9]/g,''); if(v.length>4) v=v.slice(0,4)+'-'+v.slice(4,8); this.value=v;">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-xs font-semibold mb-1">Amount</label>
                            <input type="number" name="amount" step="0.01" min="0" required id="paymentAmount" readonly class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded bg-gray-100 cursor-not-allowed">
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded px-3 py-2 mb-4">
                        <p class="text-xs text-blue-800"><i class="ph ph-info mr-1"></i>This will create a completed payment record and generate receipt</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="showManualDetailsModal = false" class="flex-1 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit" :disabled="submitting" style="background-color: var(--primary-color);" class="flex-1 text-white text-sm py-2 rounded font-medium hover:opacity-90 transition disabled:opacity-50">
                            <i class="ph mr-1" :class="submitting ? 'ph-spinner ph-spin' : 'ph-check-circle'"></i>
                            <span x-text="submitting ? 'Processing...' : 'Process Payment'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .pagination-container .is-current,
    .is-current {
        background-color: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
        color: #ffffff !important;
        font-weight: 600;
    }
    .pagination-container button:not(.is-current):not(:disabled),
    button.is-current ~ button:not(.is-current) {
        border-color: #d1d5db;
        color: #374151;
    }
    .pagination-container button:not(.is-current):not(:disabled):hover {
        background-color: #f9fafb;
    }
    .text-2xs { font-size: 0.65rem; line-height: 0.9rem; }
</style>

<script>
function clearPaymentsTabCache() {}
function paymentRealtimeHeaders(headers) {
    const socketId = window.Echo && typeof window.Echo.socketId === 'function'
        ? window.Echo.socketId()
        : null;

    if (socketId) headers['X-Socket-ID'] = socketId;
    return headers;
}

function showReceiptModal(paymentId) {
    const modal = document.getElementById('receiptModal');
    const content = document.getElementById('receiptModalContent');
    if (!modal || !content) return;
    content.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="ph ph-spinner ph-spin text-2xl"></i><p class="mt-2 text-sm">Loading receipt...</p></div>';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    fetch(`/admin/payments/${paymentId}/receipt-html`)
        .then(res => { if (!res.ok) throw new Error(); return res.text(); })
        .then(html => { content.innerHTML = html; })
        .catch(() => { content.innerHTML = '<div class="text-center text-red-600 py-8"><i class="ph ph-warning-circle text-3xl mb-2"></i><p>Failed to load receipt.</p></div>'; });
}
function closeReceiptModal() { document.getElementById('receiptModal').classList.add('hidden'); document.body.style.overflow = ''; }
window.addEventListener('click', function(event) { const modal = document.getElementById('receiptModal'); if (event.target === modal) closeReceiptModal(); });

function showReceiptImageModal(imageUrl) { document.getElementById('receiptImage').src = imageUrl; document.getElementById('receiptImageModal').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
function closeReceiptImageModal() { document.getElementById('receiptImageModal').classList.add('hidden'); document.body.style.overflow = ''; }

let approvePaymentId = null;
function showApproveModal(paymentId, studentName) {
    approvePaymentId = paymentId;
    document.getElementById('approveModal').classList.remove('hidden');
    document.getElementById('approveStudentName').textContent = studentName;
}
function closeApproveModal() { document.getElementById('approveModal').classList.add('hidden'); approvePaymentId = null; }
function confirmApprove() {
    const button = document.getElementById('approveButton');
    const icon = document.getElementById('approveIcon');
    const text = document.getElementById('approveText');
    button.disabled = true; icon.className = 'ph ph-spinner ph-spin mr-1'; text.textContent = 'Approving...';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch(`/admin/payments/gcash/${approvePaymentId}/approve`, { method: 'POST', headers: paymentRealtimeHeaders({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }) })
    .then(r => r.json()).then(data => {
        if (!data.success) throw new Error(data.message || 'Approval failed');
        closeApproveModal(); approvePaymentId = null;
        showToast('success', data.message || 'Payment approved!');
        window.clearAdminPageCache && window.clearAdminPageCache();
        window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
    }).catch(error => {
        closeApproveModal();
        button.disabled = false; icon.className = 'ph ph-check mr-1'; text.textContent = 'Approve';
        showToast('error', error.message || 'An error occurred');
    });
}

let rejectPaymentId = null;
function showRejectModal(paymentId, studentName) {
    rejectPaymentId = paymentId;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectStudentName').textContent = studentName;
    document.getElementById('rejectReason').value = '';
}
function closeRejectModal() { document.getElementById('rejectModal').classList.add('hidden'); rejectPaymentId = null; }
function submitRejection() {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { showToast('error', 'Please provide a reason for rejection'); return; }
    const button = document.getElementById('rejectButton');
    const icon = document.getElementById('rejectIcon');
    const text = document.getElementById('rejectText');
    button.disabled = true; icon.className = 'ph ph-spinner ph-spin mr-1'; text.textContent = 'Rejecting...';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch(`/admin/payments/gcash/${rejectPaymentId}/reject`, { method: 'POST', headers: paymentRealtimeHeaders({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }), body: JSON.stringify({ reason: reason }) })
    .then(r => r.json()).then(data => {
        if (!data.success) throw new Error(data.message || 'Rejection failed');
        closeRejectModal(); document.getElementById('rejectReason').value = ''; rejectPaymentId = null;
        showToast('success', data.message || 'Payment rejected');
        window.clearAdminPageCache && window.clearAdminPageCache();
        window.refreshCurrentAdminPage && window.refreshCurrentAdminPage();
    }).catch(error => {
        closeRejectModal();
        button.disabled = false; icon.className = 'ph ph-x mr-1'; text.textContent = 'Reject';
        showToast('error', error.message || 'An error occurred');
    });
}

let deleteAnnouncementId = null;
document.getElementById('deleteAnnouncementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('deleteConfirmBtn');
    const icon = document.getElementById('deleteConfirmIcon');
    const text = document.getElementById('deleteConfirmText');
    btn.disabled = true; icon.className = 'ph ph-spinner ph-spin mr-1'; text.textContent = 'Deleting...';

    try {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        };
        const socketId = window.Echo && typeof window.Echo.socketId === 'function'
            ? window.Echo.socketId()
            : null;
        if (socketId) headers['X-Socket-ID'] = socketId;

        const response = await fetch(this.action, {
            method: 'POST',
            headers: headers,
            body: new FormData(this)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete payment.');
        }

        window.dispatchEvent(new CustomEvent('admin-payment-deleted-locally', { detail: data.payment_id }));
        window.clearAdminPageCache && window.clearAdminPageCache();
        closeDeleteModal();
        showToast('success', data.message || 'Payment deleted successfully.');
    } catch (error) {
        console.error('Error deleting payment:', error);
        showToast('error', error.message || 'Failed to delete payment.');
    } finally {
        btn.disabled = false;
        icon.className = 'ph ph-trash mr-1';
        text.textContent = 'Delete';
    }
});
function showDeleteModal(announcementId, announcementTitle) {
    deleteAnnouncementId = announcementId;
    document.getElementById('deleteAnnouncementTitle').textContent = announcementTitle;
    document.getElementById('deleteAnnouncementForm').action = `{{ url('admin/payments/announcements') }}/${announcementId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); deleteAnnouncementId = null; }
window.addEventListener('click', function(event) { const dm = document.getElementById('deleteModal'); if (event.target === dm) closeDeleteModal(); });

let editAnnouncementId = null;
let editQrImageData = null;
function showEditAnnouncementModal(announcement) {
    if (!announcement) return;
    const isProtectedMembershipFee = Boolean(announcement.is_required_membership_fee);
    editAnnouncementId = announcement.id;
    document.getElementById('editTitle').value = announcement.title || '';
    document.getElementById('editCategory').value = announcement.category || '';
    document.getElementById('editAmount').value = announcement.amount || '';
    document.getElementById('editDueDate').value = String(announcement.due_date || '').slice(0, 10);
    document.getElementById('editGcashNumber').value = announcement.gcash_number || '';
    document.getElementById('editTitle').readOnly = isProtectedMembershipFee;
    document.getElementById('editCategory').disabled = isProtectedMembershipFee;
    document.getElementById('editDueDate').readOnly = isProtectedMembershipFee;
    document.getElementById('editDueDate').min = isProtectedMembershipFee ? '' : new Date().toISOString().split('T')[0];
    document.getElementById('editGcashNumber').required = !isProtectedMembershipFee;
    document.getElementById('editQrCode').value = '';
    document.getElementById('editQrFileInfo').classList.add('hidden');
    document.getElementById('editQrFileName').textContent = '';
    editQrImageData = announcement.qr_code_url || announcement.qr_code || null;
    const viewBtn = document.getElementById('viewEditQrBtn');
    const fileInfo = document.getElementById('editQrFileInfo');
    if (editQrImageData) { viewBtn.classList.remove('hidden'); fileInfo.classList.remove('hidden'); document.getElementById('editQrFileName').textContent = 'Existing QR uploaded'; }
    else { viewBtn.classList.add('hidden'); fileInfo.classList.add('hidden'); }
    document.getElementById('editAnnouncementForm').action = `{{ url('admin/payments/announcements') }}/${announcement.id}`;
    document.getElementById('editAnnouncementModal').classList.remove('hidden');
}
function closeEditAnnouncementModal() { document.getElementById('editAnnouncementModal').classList.add('hidden'); closeEditQRPreview(); editAnnouncementId = null; editQrImageData = null; }
document.getElementById('editAnnouncementForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('editPaymentBtn');
    const icon = document.getElementById('editPaymentIcon');
    const text = document.getElementById('editPaymentText');
    btn.disabled = true; icon.className = 'ph ph-spinner ph-spin mr-1'; text.textContent = 'Updating...';

    try {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        };
        const socketId = window.Echo && typeof window.Echo.socketId === 'function'
            ? window.Echo.socketId()
            : null;
        if (socketId) headers['X-Socket-ID'] = socketId;

        const response = await fetch(this.action, {
            method: 'POST',
            headers: headers,
            body: new FormData(this)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to update payment.');
        }

        window.dispatchEvent(new CustomEvent('admin-payment-updated-locally', { detail: data.payment }));
        window.clearAdminPageCache && window.clearAdminPageCache();
        closeEditAnnouncementModal();
        showToast('success', data.message || 'Payment updated successfully.');
    } catch (error) {
        console.error('Error updating payment:', error);
        showToast('error', error.message || 'Failed to update payment.');
    } finally {
        btn.disabled = false;
        icon.className = 'ph ph-floppy-disk mr-1';
        text.textContent = 'Update Payment';
    }
});
window.addEventListener('click', function(event) { const em = document.getElementById('editAnnouncementModal'); if (event.target === em) closeEditAnnouncementModal(); });

document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const createDueDateInput = document.getElementById('createDueDate');
    if (createDueDateInput) createDueDateInput.min = today;
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    if (successMessage) {
        showToast('success', decodeURIComponent(successMessage));
        window.history.replaceState({}, document.title, window.location.pathname + '?tab=' + (urlParams.get('tab') || 'payments'));
    }
    const toastData = sessionStorage.getItem('paymentToast');
    if (toastData) {
        sessionStorage.removeItem('paymentToast');
        const data = JSON.parse(toastData);
        const borderColor = data.type === 'success' ? 'border-green-500' : 'border-red-500';
        const bgColor = data.type === 'success' ? 'bg-green-100' : 'bg-red-100';
        const textColor = data.type === 'success' ? 'text-green-600' : 'text-red-600';
        const icon = data.type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
        const title = data.type === 'success' ? 'Success!' : 'Error!';
        const toast = document.createElement('div');
        toast.className = `payment-toast fixed top-6 right-6 z-50 bg-white border-l-4 ${borderColor} rounded-lg shadow-xl px-4 py-3 flex items-center gap-3 max-w-sm`;
        toast.style.display = 'flex';
        toast.innerHTML = `<div class="h-8 w-8 rounded-full ${bgColor} flex items-center justify-center flex-shrink-0"><i class="ph ${icon} text-sm ${textColor}"></i></div><div><p class="text-sm font-semibold text-gray-900">${title}</p><p class="text-xs text-gray-600">${data.message}</p></div><button onclick="this.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600 flex-shrink-0"><i class="ph ph-x text-xs"></i></button>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4500);
    }
});

function showToast(type, message) {
    const existingToasts = document.querySelectorAll('.payment-toast');
    existingToasts.forEach(t => t.remove());
    const borderColor = type === 'success' ? 'border-green-500' : 'border-red-500';
    const bgColor = type === 'success' ? 'bg-green-100' : 'bg-red-100';
    const textColor = type === 'success' ? 'text-green-600' : 'text-red-600';
    const icon = type === 'success' ? 'ph-check-circle' : 'ph-warning-circle';
    const title = type === 'success' ? 'Success!' : 'Error!';
    const toast = document.createElement('div');
    toast.className = `payment-toast fixed top-6 right-6 bg-white border-l-4 ${borderColor} rounded-lg shadow-xl px-4 py-3 flex items-center gap-3 max-w-sm transition-all duration-300 ease-out`;
    toast.style.zIndex = '99999';
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.innerHTML = `<div class="h-8 w-8 rounded-full ${bgColor} flex items-center justify-center flex-shrink-0"><i class="ph ${icon} text-sm ${textColor}"></i></div><div><p class="text-sm font-semibold text-gray-900">${title}</p><p class="text-xs text-gray-600">${message}</p></div><button onclick="this.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600 flex-shrink-0"><i class="ph ph-x text-xs"></i></button>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '1'; toast.style.transform = 'translateX(0)'; }, 10);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function exportPayments(button) {
    const icon = button.querySelector('i');
    const text = button.querySelector('span');
    const originalIcon = icon.className;
    const originalText = text.textContent;
    const fromDate = document.getElementById('filterFromDate').value;
    const toDate = document.getElementById('filterToDate').value;
    const visibleRows = Array.from(document.querySelectorAll('.payment-row')).filter(row => row.style.display !== 'none');
    if (visibleRows.length === 0) { showToast('error', 'No payments found in the selected date range'); return; }
    button.disabled = true; icon.className = 'ph ph-spinner ph-spin mr-1'; text.textContent = 'Exporting...';
    let exportUrl = '{{ route("admin.payments.export") }}';
    const params = new URLSearchParams();
    if (fromDate) params.append('from_date', fromDate);
    if (toDate) params.append('to_date', toDate);
    if (params.toString()) exportUrl += '?' + params.toString();
    window.location.href = exportUrl;
    setTimeout(() => { button.disabled = false; icon.className = originalIcon; text.textContent = originalText; }, 3000);
}

let createQrImageData = null;
function previewCreateQR(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            createQrImageData = e.target.result;
            document.getElementById('qrFileName').textContent = file.name;
            document.getElementById('qrFileInfo').classList.remove('hidden');
            document.getElementById('viewCreateQrBtn').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}
function toggleQRPreview(event) {
    event.stopPropagation();
    if (createQrImageData) {
        const popover = document.getElementById('qrPreviewPopover');
        if (popover) {
            const isHidden = popover.classList.contains('hidden');
            if (isHidden) { document.getElementById('qrPreviewPopoverImage').src = createQrImageData; popover.classList.remove('hidden'); }
            else { popover.classList.add('hidden'); }
        }
    } else { alert('Please upload a QR code first'); }
}
function closeQRPreview() { const p = document.getElementById('qrPreviewPopover'); if (p) p.classList.add('hidden'); }
function previewEditQR(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            editQrImageData = e.target.result;
            document.getElementById('editQrFileName').textContent = file.name;
            document.getElementById('editQrFileInfo').classList.remove('hidden');
            document.getElementById('viewEditQrBtn').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}
function toggleEditQRPreview(event) {
    event.stopPropagation();
    if (editQrImageData) {
        const popover = document.getElementById('editQrPreviewPopover');
        if (popover) {
            const isHidden = popover.classList.contains('hidden');
            if (isHidden) { document.getElementById('editQrPreviewPopoverImage').src = editQrImageData; popover.classList.remove('hidden'); }
            else { popover.classList.add('hidden'); }
        }
    } else { alert('Please upload a QR code first'); }
}
function closeEditQRPreview() { const p = document.getElementById('editQrPreviewPopover'); if (p) p.classList.add('hidden'); }
document.addEventListener('click', function(event) {
    const popover = document.getElementById('qrPreviewPopover');
    const viewBtn = document.getElementById('viewCreateQrBtn');
    if (popover && !popover.contains(event.target) && event.target !== viewBtn && !viewBtn.contains(event.target)) popover.classList.add('hidden');
    const editPopover = document.getElementById('editQrPreviewPopover');
    const editViewBtn = document.getElementById('viewEditQrBtn');
    if (editPopover && !editPopover.contains(event.target) && event.target !== editViewBtn && !editViewBtn.contains(event.target)) editPopover.classList.add('hidden');
});
</script>

@endsection
