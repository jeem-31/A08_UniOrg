@extends('layouts.app')

@section('title', 'Submit Complaint/Suggestion')

@section('content')
<div class="student-neu-page max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
    <div class="mb-4 sm:mb-6">
        <a href="{{ route('chatbot.index') }}" style="color: var(--primary-color);" class="hover:opacity-80 text-sm sm:text-base">
            <i class="ph ph-arrow-left mr-2"></i>Back
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-5 sm:p-8">
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-gray-900 mb-4 sm:mb-6">Submit Complaint or Suggestion</h1>

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 sm:mb-6 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('complaints.store') }}">
            @csrf

            <div class="mb-4 sm:mb-6">
                <label for="type" class="block text-gray-700 text-sm font-bold mb-2">Type</label>
                <select name="type" id="type" required style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none">
                    <option value="">Select Type</option>
                    <option value="complaint" {{ old('type') == 'complaint' ? 'selected' : '' }}>Complaint</option>
                    <option value="suggestion" {{ old('type') == 'suggestion' ? 'selected' : '' }}>Suggestion</option>
                    <option value="inquiry" {{ old('type') == 'inquiry' ? 'selected' : '' }}>Inquiry</option>
                    <option value="feedback" {{ old('type') == 'feedback' ? 'selected' : '' }}>Feedback</option>
                </select>
            </div>

            <div class="mb-4 sm:mb-6">
                <label for="subject" class="block text-gray-700 text-sm font-bold mb-2">Subject</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none">
            </div>

            <div class="mb-4 sm:mb-6">
                <label for="message" class="block text-gray-700 text-sm font-bold mb-2">Message</label>
                <textarea name="message" id="message" rows="6" required
                    style="border-color: var(--primary-color);" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none">{{ old('message') }}</textarea>
            </div>

            <div class="mb-4 sm:mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_anonymous" value="1" {{ old('is_anonymous') ? 'checked' : '' }}
                        style="color: var(--primary-color);" class="rounded border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-sm text-gray-700">Submit anonymously</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">Your identity will not be revealed to anyone</p>
            </div>

            <button type="submit" style="background-color: var(--primary-color);" class="w-full text-white font-bold py-3 px-4 rounded-lg hover:opacity-90 text-sm sm:text-base">
                Submit
            </button>
        </form>
    </div>
</div>
@endsection
