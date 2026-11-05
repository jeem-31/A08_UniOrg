@extends('layouts.admin')

@section('title', 'Edit FAQ - Admin')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex items-center mb-4">
            <a href="{{ route('admin.chatbot.faqs') }}" class="text-indigo-600 hover:text-indigo-800 mr-4">
                <i class="ph ph-arrow-left"></i> Back to FAQs
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">Edit FAQ</h1>
        <p class="text-gray-600 mt-2">Update the frequently asked question</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.chatbot.faqs.update', $faq) }}" method="POST" data-admin-inplace-form data-admin-inplace-success-url="{{ route('admin.chatbot.faqs') }}">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label for="question" class="block text-sm font-medium text-gray-700 mb-2">
                    Question <span class="text-red-500">*</span>
                </label>
                <input type="text" name="question" id="question" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('question') border-red-500 @enderror"
                    value="{{ old('question', $faq->question) }}"
                    placeholder="e.g., How do I pay my membership fee?">
                @error('question')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="answer" class="block text-sm font-medium text-gray-700 mb-2">
                    Answer <span class="text-red-500">*</span>
                </label>
                <textarea name="answer" id="answer" rows="6" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('answer') border-red-500 @enderror"
                    placeholder="Provide a detailed answer to the question...">{{ old('answer', $faq->answer) }}</textarea>
                @error('answer')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                    Category
                </label>
                <select name="category" id="category"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select a category</option>
                    <option value="general" {{ old('category', $faq->category) === 'general' ? 'selected' : '' }}>General</option>
                    <option value="payment" {{ old('category', $faq->category) === 'payment' ? 'selected' : '' }}>Payment</option>
                    <option value="event" {{ old('category', $faq->category) === 'event' ? 'selected' : '' }}>Event</option>
                    <option value="membership" {{ old('category', $faq->category) === 'membership' ? 'selected' : '' }}>Membership</option>
                    <option value="technical" {{ old('category', $faq->category) === 'technical' ? 'selected' : '' }}>Technical</option>
                </select>
                @error('category')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">
                    Keywords
                </label>
                <input type="text" name="keywords" id="keywords"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    value="{{ old('keywords', is_array($faq->keywords) ? implode(', ', $faq->keywords) : '') }}"
                    placeholder="payment, fee, gcash (comma-separated)">
                <p class="text-sm text-gray-500 mt-1">Enter keywords separated by commas to help the chatbot match questions</p>
                @error('keywords')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published', $faq->is_published) ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="is_published" class="ml-2 block text-sm text-gray-700">
                        Published
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-1">If unchecked, this FAQ will be saved as a draft</p>
            </div>

            <!-- Statistics -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Statistics</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">Status</p>
                        <p class="text-sm font-semibold">
                            @if($faq->status === 'published')
                                <span class="text-green-600"><i class="ph ph-check-circle mr-1"></i>Published</span>
                            @elseif($faq->status === 'draft')
                                <span class="text-yellow-600"><i class="ph ph-clock mr-1"></i>Draft</span>
                            @else
                                <span class="text-red-600"><i class="ph ph-eye-slash mr-1"></i>Hidden</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Views</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $faq->view_count ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Created</p>
                        <p class="text-sm text-gray-900">{{ $faq->created_at->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Publish Button - Only show for draft status -->
            @if($faq->status === 'draft')
            <div class="mb-6" x-data="{ showPublish: false }">
                <input type="hidden" name="publish_faq" value="0" x-ref="publishInput">
                <button type="button" 
                    @click="$refs.publishInput.value = '1'; showPublish = true; $el.classList.add('hidden')"
                    class="w-full px-4 py-3 bg-gradient-to-r from-green-50 to-blue-50 hover:from-green-100 hover:to-blue-100 border-2 border-green-300 rounded-lg text-green-800 font-medium transition flex items-center justify-center">
                    <i class="ph ph-paper-plane-tilt mr-2"></i>Publish This FAQ
                </button>
                <div x-show="showPublish" x-cloak class="p-3 bg-green-50 border-2 border-green-300 rounded-lg text-green-800 text-sm mt-2">
                    <i class="ph ph-check-circle mr-2"></i>This FAQ will be published and visible to students when you save.
                </div>
            </div>
            @endif

            <!-- Hide Button - Only show for published status -->
            @if($faq->status === 'published')
            <div class="mb-6" x-data="{ showHide: false }">
                <input type="hidden" name="hide_faq" value="0" x-ref="hideInput">
                <button type="button" 
                    @click="$refs.hideInput.value = '1'; showHide = true; $el.classList.add('hidden')"
                    class="w-full px-4 py-3 bg-yellow-50 hover:bg-yellow-100 border-2 border-yellow-300 rounded-lg text-yellow-800 font-medium transition flex items-center justify-center">
                    <i class="ph ph-eye-slash mr-2"></i>Hide from Students
                </button>
                <div x-show="showHide" x-cloak class="p-3 bg-red-50 border-2 border-red-300 rounded-lg text-red-800 text-sm mt-2">
                    <i class="ph ph-warning mr-2"></i>This FAQ will be hidden when you save.
                </div>
            </div>
            @endif

            <!-- Unhide Button - Only show for hidden status -->
            @if($faq->status === 'hidden')
            <div class="mb-6" x-data="{ showUnhide: false }">
                <input type="hidden" name="unhide_faq" value="0" x-ref="unhideInput">
                <button type="button" 
                    @click="$refs.unhideInput.value = '1'; showUnhide = true; $el.classList.add('hidden')"
                    class="w-full px-4 py-3 bg-green-50 hover:bg-green-100 border-2 border-green-300 rounded-lg text-green-800 font-medium transition flex items-center justify-center">
                    <i class="ph ph-eye mr-2"></i>Unhide & Make Published
                </button>
                <div x-show="showUnhide" x-cloak class="p-3 bg-green-50 border-2 border-green-300 rounded-lg text-green-800 text-sm mt-2">
                    <i class="ph ph-check-circle mr-2"></i>This FAQ will be visible to students when you save.
                </div>
            </div>
            @endif

            <div class="flex items-center justify-end space-x-4">
                <a href="{{ route('admin.chatbot.faqs') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition">
                    <i class="ph ph-floppy-disk mr-2"></i> Update FAQ
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
