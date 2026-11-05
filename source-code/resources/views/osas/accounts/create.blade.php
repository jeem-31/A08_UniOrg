@extends('layouts.osas')

@section('title', 'Create Account - OSAS')

@section('content')
<div class="container-fluid px-6 py-5">
    
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none mb-1">
                Create <span class="text-[#26488C]">Account</span>
            </h1>
            <p class="text-gray-500 text-xs font-medium">Add another OSAS admin or set the Dean account for a college. One active Dean per college.</p>
        </div>
        <a href="{{ route('osas.accounts.index') }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
            <i class="ph ph-arrow-left mr-1.5"></i> Back to Directory
        </a>
    </div>

    <!-- Form Section -->
    <div class="max-w-3xl bg-white rounded-lg border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50">
            <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                <i class="ph ph-user-plus text-[#26488C]"></i> Account Details
            </h3>
        </div>
        
        <form method="POST" action="{{ route('osas.accounts.store') }}" class="p-6">
            @csrf

            <!-- Name Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label for="first_name" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('first_name') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                    @error('first_name')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('last_name') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                    @error('last_name')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Optional Name Fields -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                <div class="md:col-span-2">
                    <label for="middle_name" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Middle Name</label>
                    <input type="text" name="middle_name" id="middle_name" value="{{ old('middle_name') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                </div>
                <div>
                    <label for="suffix" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Suffix</label>
                    <input type="text" name="suffix" id="suffix" value="{{ old('suffix') }}" placeholder="e.g. Jr, III" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                </div>
            </div>

            <div class="h-px bg-gray-100 my-6"></div>

            <!-- Credentials & Identity -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label for="email" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="off" class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('email') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                    @error('email')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="student_id" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Student/Employee ID</label>
                    <input type="text" name="student_id" id="student_id" value="{{ old('student_id') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                </div>
            </div>

            <!-- Role (OSAS admin or Dean per college) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
                <div>
                    <label for="role" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">System Role <span class="text-red-500">*</span></label>
                    <select name="role" id="role" required onchange="toggleDeanCollege()" class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('role') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                        <option value="osas_admin" {{ old('role', request('role', 'osas_admin')) === 'osas_admin' ? 'selected' : '' }}>OSAS Admin</option>
                        <option value="super_admin" {{ old('role', request('role')) === 'super_admin' ? 'selected' : '' }}>Dean (per College)</option>
                    </select>
                    <p class="mt-1 text-[9px] text-gray-500 font-bold uppercase tracking-wider">Dean accounts are tied to one college and used to autofill Apply/Renew.</p>
                    @error('role')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div id="college-field">
                    <label for="department_id" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">College <span class="text-red-500" id="college-required-mark">*</span></label>
                    <select name="department_id" id="department_id" class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('department_id') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                        <option value="">Select college...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ (string) old('department_id') === (string) $dept->id ? 'selected' : '' }}>{{ $dept->department_name }}{{ $dept->department_acronym ? ' ('.$dept->department_acronym.')' : '' }}</option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <script>
                function toggleDeanCollege() {
                    const role = document.getElementById('role').value;
                    const collegeField = document.getElementById('college-field');
                    const dept = document.getElementById('department_id');
                    const mark = document.getElementById('college-required-mark');
                    const isDean = role === 'super_admin';
                    collegeField.style.display = isDean ? '' : 'none';
                    dept.required = isDean;
                    mark.style.display = isDean ? '' : 'none';
                }
                document.addEventListener('DOMContentLoaded', toggleDeanCollege);
            </script>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
                <div>
                    <label for="password" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" id="password" required autocomplete="new-password" class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('password') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                    @error('password')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password" class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('password_confirmation') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                    @error('password_confirmation')
                        <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-50 mt-4">
                <a href="{{ route('osas.accounts.index') }}" class="px-5 py-2.5 text-xs font-bold text-gray-600 hover:text-gray-900 transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-[#26488C] text-white text-xs font-bold rounded-lg hover:bg-[#1a3461] transition-colors shadow-sm focus:ring-4 focus:ring-[#26488C]/20">
                    <i class="ph ph-floppy-disk mr-1.5"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>
@endsection
