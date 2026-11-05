@extends('layouts.admin')

@section('title', 'Create FAQ - Admin')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex items-center mb-4">
            <a href="{{ route('admin.chatbot.faqs') }}" class="text-indigo-600 hover:text-indigo-800 mr-4">
                <i class="ph ph-arrow-left"></i> Back to FAQs
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-900">Create New FAQ</h1>
        <p class="text-gray-600 mt-2">Add a new frequently asked question for the chatbot</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.chatbot.faqs.store') }}" method="POST" data-admin-inplace-form data-admin-inplace-success-url="{{ route('admin.chatbot.faqs') }}">
            @csrf

            <div class="mb-6">
                <label for="question" class="block text-sm font-medium text-gray-700 mb-2">
                    Question <span class="text-red-500">*</span>
                </label>
                <input type="text" name="question" id="question" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('question') border-red-500 @enderror"
                    value="{{ old('question') }}"
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
                    placeholder="Provide a detailed answer to the question...">{{ old('answer') }}</textarea>
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
                    <option value="general" {{ old('category') === 'general' ? 'selected' : '' }}>General</option>
                    <option value="payment" {{ old('category') === 'payment' ? 'selected' : '' }}>Payment</option>
                    <option value="event" {{ old('category') === 'event' ? 'selected' : '' }}>Event</option>
                    <option value="membership" {{ old('category') === 'membership' ? 'selected' : '' }}>Membership</option>
                    <option value="technical" {{ old('category') === 'technical' ? 'selected' : '' }}>Technical</option>
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
                    value="{{ old('keywords') }}"
                    placeholder="payment, fee, gcash (comma-separated)">
                <p class="text-sm text-gray-500 mt-1">Enter keywords separated by commas to help the chatbot match questions</p>
                @error('keywords')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="is_published" class="ml-2 block text-sm text-gray-700">
                        Publish immediately
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-1">If unchecked, this FAQ will be saved as a draft</p>
            </div>

            <div class="flex items-center justify-end space-x-4">
                <a href="{{ route('admin.chatbot.faqs') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition">
                    <i class="ph ph-floppy-disk mr-2"></i> Create FAQ
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
