@extends(request()->boolean('fragment') ? 'layouts.fragment' : 'layouts.admin')

@section('title', 'Manage FAQs - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="{ showCreateModal: false, showEditModal: false, showDeleteModal: false, editFaq: null, deleteFaq: null, statusFilter: 'all', faqStatuses: {{ json_encode($faqs->pluck('status')->values()->all()) }} }">
    <div class="flex flex-col gap-4 border-b border-gray-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Manage FAQs</h1>
        </div>
        <button @click="showCreateModal = true" type="button" style="background-color: var(--primary-color);" class="inline-flex w-full items-center justify-center rounded-md px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 sm:w-auto">
            <i style="color: var(--complementary-color);" class="ph ph-plus mr-2"></i> New FAQ
        </button>
    </div>

    @if(session('success'))
        <div x-data="{ show: true }"
             x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             x-init="setTimeout(() => show = false, 4000)"
             class="mt-4 flex items-start gap-3 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
             style="display: none;">
            <i class="ph ph-check-circle mt-0.5 text-green-600"></i>
            <p class="flex-1">{{ session('success') }}</p>
            <button @click="show = false" type="button" class="text-green-700 hover:text-green-900" aria-label="Dismiss notification">
                <i class="ph ph-x"></i>
            </button>
        </div>
    @endif

    <div class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">FAQ library</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ $totalFaqs }} {{ Str::plural('entry', $totalFaqs) }} in this library</p>
            </div>
            <div class="flex flex-wrap items-center gap-1" role="tablist" aria-label="FAQ status filter">
                <button type="button" @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'text-white' : 'text-gray-600 hover:bg-gray-200'" :style="statusFilter === 'all' ? 'background-color: var(--primary-color);' : ''" class="rounded-md px-3 py-1.5 text-xs font-semibold transition" role="tab" :aria-selected="statusFilter === 'all'">All <span class="ml-1 opacity-75">{{ $totalFaqs }}</span></button>
                <button type="button" @click="statusFilter = 'published'" :class="statusFilter === 'published' ? 'bg-green-100 text-green-800' : 'text-gray-600 hover:bg-gray-200'" class="rounded-md px-3 py-1.5 text-xs font-semibold transition" role="tab" :aria-selected="statusFilter === 'published'">Published <span class="ml-1 opacity-75">{{ $publishedCount }}</span></button>
                <button type="button" @click="statusFilter = 'draft'" :class="statusFilter === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'text-gray-600 hover:bg-gray-200'" class="rounded-md px-3 py-1.5 text-xs font-semibold transition" role="tab" :aria-selected="statusFilter === 'draft'">Drafts <span class="ml-1 opacity-75">{{ $draftCount }}</span></button>
                <button type="button" @click="statusFilter = 'hidden'" :class="statusFilter === 'hidden' ? 'bg-red-100 text-red-800' : 'text-gray-600 hover:bg-gray-200'" class="rounded-md px-3 py-1.5 text-xs font-semibold transition" role="tab" :aria-selected="statusFilter === 'hidden'">Hidden <span class="ml-1 opacity-75">{{ $hiddenCount }}</span></button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-white">
                    <tr>
                        <th class="w-[24%] px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500">Question</th>
                        <th class="w-[31%] px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500">Answer preview</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500">Category</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500">Views</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($faqs as $faq)
                        <tr x-show="statusFilter === 'all' || statusFilter === '{{ $faq->status }}'" class="transition hover:bg-gray-50">
                            <td class="max-w-xs px-4 py-3 align-top">
                                <div class="flex items-start gap-2.5">
                                    <span class="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md" style="background-color: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-color);">
                                        <i class="ph ph-question text-xs"></i>
                                    </span>
                                    <p class="text-sm font-semibold leading-5 text-gray-900">{{ Str::limit($faq->question, 70) }}</p>
                                </div>
                            </td>
                            <td class="max-w-sm px-4 py-3 align-top">
                                <p class="text-sm leading-5 text-gray-500">{{ Str::limit($faq->answer, 100) }}</p>
                            </td>
                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                @if($faq->category)
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                        {{ $faq->category === 'general' ? 'bg-gray-100 text-gray-700' : '' }}
                                        {{ $faq->category === 'payment' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $faq->category === 'event' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $faq->category === 'membership' ? 'bg-indigo-100 text-indigo-700' : '' }}
                                        {{ $faq->category === 'technical' ? 'bg-red-100 text-red-700' : '' }}">
                                        {{ ucfirst($faq->category) }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-900">{{ $faq->view_count ?? 0 }}</span>
                            </td>
                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                @if($faq->status === 'published')
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"><i class="ph ph-check mr-1.5 text-[10px]"></i>Published</span>
                                @elseif($faq->status === 'hidden')
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800"><i class="ph ph-prohibit mr-1.5 text-[10px]"></i>Hidden</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800"><i class="ph ph-clock mr-1.5 text-[10px]"></i>Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top whitespace-nowrap">
                                <button @click="editFaq = {{ json_encode($faq) }}; showEditModal = true" type="button" class="mr-3 inline-flex items-center text-xs font-semibold text-blue-600 hover:text-blue-900" aria-label="Edit FAQ">
                                    <i class="ph ph-pencil-simple mr-1.5"></i> Edit
                                </button>
                                <button @click="deleteFaq = {{ json_encode($faq) }}; showDeleteModal = true" type="button" class="inline-flex items-center text-xs font-semibold text-red-600 hover:text-red-900" aria-label="Delete FAQ">
                                    <i class="ph ph-trash mr-1.5"></i> Delete
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    <tr x-show="faqStatuses.length === 0 || (statusFilter !== 'all' && !faqStatuses.some(status => status === statusFilter))">
                        <td colspan="6" class="px-4 py-12 text-center">
                            <i class="ph ph-funnel text-2xl text-gray-300"></i>
                            <p class="mt-2 text-sm font-medium text-gray-700" x-text="({ all: 'No FAQs yet', published: 'No published FAQs', draft: 'No drafts', hidden: 'No hidden FAQs' })[statusFilter]"></p>
                            <p class="mt-1 text-xs text-gray-500" x-text="statusFilter === 'all' ? 'Create your first FAQ to help students.' : 'Choose another status or create a new FAQ.'"></p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create FAQ Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
            <div @click="showCreateModal = false" class="fixed inset-0 bg-gray-900/60 modal-backdrop"></div>
            <div class="relative my-6 w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-6">
                <div class="mb-4 flex items-start justify-between border-b border-gray-200 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Create new FAQ</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Add an answer to the chatbot knowledge base.</p>
                    </div>
                    <button @click="showCreateModal = false" type="button" class="text-gray-400 hover:text-gray-600" aria-label="Close dialog"><i class="ph ph-x"></i></button>
                </div>
                <form action="{{ route('admin.chatbot.faqs.store') }}" method="POST" data-admin-inplace-form x-data="{ draftLoading: false, publishLoading: false, actionType: '' }" @submit="if(event.submitter.name === 'save_as_draft') { draftLoading = true; actionType = 'draft'; } else if(event.submitter.name === 'is_published') { publishLoading = true; actionType = 'publish'; }">
                    @csrf
                    <input type="hidden" name="action_type" x-model="actionType">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Question <span class="text-red-500">*</span></label>
                            <input type="text" name="question" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="e.g., How do I pay my membership fee?">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Answer <span class="text-red-500">*</span></label>
                            <textarea name="answer" rows="4" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" placeholder="Provide a detailed answer..."></textarea>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                            <select name="category" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select a category</option>
                                <option value="general">General</option>
                                <option value="payment">Payment</option>
                                <option value="event">Event</option>
                                <option value="membership">Membership</option>
                                <option value="technical">Technical</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" @click="showCreateModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" name="save_as_draft" value="1" :disabled="draftLoading || publishLoading" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50">
                            <i class="ph mr-1.5" :class="draftLoading ? 'ph-spinner ph-spin' : 'ph-file'" style="color: var(--complementary-color);"></i>
                            <span x-text="draftLoading ? 'Saving...' : 'Save as Draft'"></span>
                        </button>
                        <button type="submit" name="is_published" value="1" :disabled="draftLoading || publishLoading" style="background-color: var(--primary-color);" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                            <i class="ph mr-1.5" :class="publishLoading ? 'ph-spinner ph-spin' : 'ph-check'" style="color: var(--complementary-color);"></i>
                            <span x-text="publishLoading ? 'Publishing...' : 'Publish'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit FAQ Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
            <div @click="showEditModal = false" class="fixed inset-0 bg-gray-900/60 modal-backdrop"></div>
            <div class="relative my-6 w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-6">
                <div class="mb-4 flex items-start justify-between border-b border-gray-200 pb-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Edit FAQ</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Update content or change visibility.</p>
                    </div>
                    <button @click="showEditModal = false" type="button" class="text-gray-400 hover:text-gray-600" aria-label="Close dialog"><i class="ph ph-x"></i></button>
                </div>
                <form :action="`{{ url('admin/chatbot/faqs') }}/${editFaq?.id}`" method="POST" data-admin-inplace-form x-show="editFaq" x-data="{ updateLoading: false }" @submit="updateLoading = true">
                    @csrf
                    @method('PUT')
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Question <span class="text-red-500">*</span></label>
                            <input type="text" name="question" :value="editFaq?.question" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Answer <span class="text-red-500">*</span></label>
                            <textarea name="answer" rows="4" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500" x-text="editFaq?.answer"></textarea>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Category</label>
                            <select name="category" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select a category</option>
                                <option value="general" :selected="editFaq?.category === 'general'">General</option>
                                <option value="payment" :selected="editFaq?.category === 'payment'">Payment</option>
                                <option value="event" :selected="editFaq?.category === 'event'">Event</option>
                                <option value="membership" :selected="editFaq?.category === 'membership'">Membership</option>
                                <option value="technical" :selected="editFaq?.category === 'technical'">Technical</option>
                            </select>
                        </div>
                    </div>

                    <!-- Publish Button - Only show for draft status -->
                    <div class="mt-4" x-show="editFaq?.status === 'draft'">
                        <input type="hidden" name="publish_faq" value="0" x-ref="publishInput">
                        <button type="button" @click="$refs.publishInput.value = '1'; $refs.publishBtn.classList.add('hidden'); $refs.publishMsg.classList.remove('hidden')" x-ref="publishBtn" class="w-full rounded-md border border-green-300 bg-green-50 px-3 py-2.5 text-sm font-medium text-green-800 transition hover:bg-green-100">
                            <i class="ph ph-paper-plane-tilt mr-2"></i>Publish This FAQ
                        </button>
                        <div x-ref="publishMsg" class="hidden rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800"><i class="ph ph-check-circle mr-2"></i>This FAQ will be published and visible to students when you save.</div>
                    </div>

                    <!-- Hide Button - Only show for published status -->
                    <div class="mt-4" x-show="editFaq?.status === 'published'">
                        <input type="hidden" name="hide_faq" value="0" x-ref="hideInput">
                        <button type="button" @click="$refs.hideInput.value = '1'; $refs.hideBtn.classList.add('hidden'); $refs.hideMsg.classList.remove('hidden')" x-ref="hideBtn" class="w-full rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2.5 text-sm font-medium text-yellow-800 transition hover:bg-yellow-100">
                            <i class="ph ph-eye-slash mr-2"></i>Hide from Students
                        </button>
                        <div x-ref="hideMsg" class="hidden rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800"><i class="ph ph-warning mr-2"></i>This FAQ will be hidden when you save.</div>
                    </div>

                    <!-- Unhide Button - Only show for hidden status -->
                    <div class="mt-4" x-show="editFaq?.status === 'hidden'">
                        <input type="hidden" name="unhide_faq" value="0" x-ref="unhideInput">
                        <button type="button" @click="$refs.unhideInput.value = '1'; $refs.unhideBtn.classList.add('hidden'); $refs.unhideMsg.classList.remove('hidden')" x-ref="unhideBtn" class="w-full rounded-md border border-green-300 bg-green-50 px-3 py-2.5 text-sm font-medium text-green-800 transition hover:bg-green-100">
                            <i class="ph ph-eye mr-2"></i>Unhide &amp; Publish
                        </button>
                        <div x-ref="unhideMsg" class="hidden rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800"><i class="ph ph-check-circle mr-2"></i>This FAQ will be visible to students when you save.</div>
                    </div>

                    <div class="mt-4 rounded-md bg-gray-50 p-3" x-show="editFaq?.status !== 'draft'">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Statistics</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="mt-1 text-sm font-semibold">
                                    <span x-show="editFaq?.status === 'published'" class="text-green-600"><i class="ph ph-check-circle mr-1"></i>Published</span>
                                    <span x-show="editFaq?.status === 'draft'" class="text-yellow-600"><i class="ph ph-clock mr-1"></i>Draft</span>
                                    <span x-show="editFaq?.status === 'hidden'" class="text-red-600"><i class="ph ph-eye-slash mr-1"></i>Hidden</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Views</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900" x-text="editFaq?.view_count || 0"></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Created</p>
                                <p class="mt-1 text-sm text-gray-900" x-text="editFaq?.created_at ? new Date(editFaq.created_at).toLocaleDateString() : ''"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-gray-200 pt-4 sm:flex-row sm:justify-end">
                        <button type="button" @click="showEditModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" :disabled="updateLoading" style="background-color: var(--primary-color);" class="rounded-md px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                            <i class="ph mr-1.5" :class="updateLoading ? 'ph-spinner ph-spin' : 'ph-floppy-disk'" style="color: var(--complementary-color);"></i>
                            <span x-text="updateLoading ? 'Updating...' : 'Update FAQ'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex min-h-full items-center justify-center p-4">
            <div @click="showDeleteModal = false" class="fixed inset-0 bg-gray-900/60 modal-backdrop"></div>
            <div class="relative my-6 w-full max-w-md rounded-lg bg-white p-5 shadow-xl sm:p-6">
                <div class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100"><i class="ph ph-warning text-xl text-red-600"></i></div>
                    <h3 class="mt-3 text-lg font-bold text-gray-900">Delete FAQ</h3>
                    <p class="mt-2 text-sm text-gray-600">Are you sure you want to delete this FAQ? This action cannot be undone.</p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row">
                        <button @click="showDeleteModal = false" type="button" class="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i class="ph ph-x mr-1.5"></i>Cancel</button>
                        <form :action="`{{ url('admin/chatbot/faqs') }}/${deleteFaq?.id}`" method="POST" data-admin-inplace-form class="flex-1" x-data="{ isDeleting: false }" @submit="isDeleting = true">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700" :disabled="isDeleting" :class="{ 'opacity-50 cursor-not-allowed': isDeleting }">
                                <i class="ph mr-1.5" :class="isDeleting ? 'ph-spinner ph-spin' : 'ph-trash'"></i>
                                <span x-show="!isDeleting">Delete</span>
                                <span x-show="isDeleting">Deleting...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
