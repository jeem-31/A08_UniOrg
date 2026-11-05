@extends('layouts.osas')

@section('title', 'Edit Profile - OSAS')

@section('content')
<div class="container-fluid px-6 py-5">
    
    @if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-100 rounded-lg flex items-center gap-3">
        <i class="ph ph-warning-circle text-red-600"></i>
        <span class="text-sm font-bold text-red-800">{{ session('error') }}</span>
    </div>
    @endif

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight leading-none mb-1">
                Edit <span class="text-[#26488C]">Profile</span>
            </h1>
            <p class="text-gray-500 text-xs font-medium">Update profile information for {{ $details->fullName }}.</p>
        </div>
        <a href="{{ route('osas.accounts.show', $details->accountId) }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
            <i class="ph ph-arrow-left mr-1.5"></i> Back to Profile
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Left: Profile Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg border border-gray-100 overflow-hidden mb-5">
                <div class="px-6 py-4 border-b border-gray-50 flex justify-between items-center">
                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-user text-[#26488C]"></i> Personal Details
                    </h3>
                </div>
                
                <form method="POST" action="{{ route('osas.accounts.update-profile', $details->accountId) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    
                    <input type="hidden" name="freshness" value="{{ $details->managementVersion }}">

                    <!-- Name Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name', $details->profileValues['first_name'] ?? '') }}" required class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('first_name') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                            @error('first_name')
                                <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name', $details->profileValues['last_name'] ?? '') }}" required class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('last_name') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                            @error('last_name')
                                <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Middle Name</label>
                            <input type="text" name="middle_name" value="{{ old('middle_name', $details->profileValues['middle_name'] ?? '') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Suffix</label>
                            <input type="text" name="suffix" value="{{ old('suffix', $details->profileValues['suffix'] ?? '') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                        </div>
                    </div>

                    <div class="h-px bg-gray-100 my-6"></div>

                    <!-- Academic / Contact Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="{{ old('email', $details->profileValues['email'] ?? '') }}" required class="w-full px-4 py-2.5 bg-gray-50 border {{ $errors->has('email') ? 'border-red-300 focus:ring-red-500' : 'border-gray-200 focus:ring-[#26488C]' }} rounded-lg text-sm focus:ring-2 focus:border-transparent transition-all">
                            @error('email')
                                <p class="mt-1 text-[10px] font-bold text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Student/Employee ID</label>
                            <input type="text" name="student_id" value="{{ old('student_id', $details->profileValues['student_id'] ?? '') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Department ID</label>
                            <input type="number" name="department_id" value="{{ old('department_id', $details->profileValues['department_id'] ?? '') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Course</label>
                            <input type="text" name="course" value="{{ old('course', $details->profileValues['course'] ?? '') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Year Level</label>
                            <input type="number" name="year_level" value="{{ old('year_level', $details->profileValues['year_level'] ?? '') }}" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#26488C] focus:border-transparent transition-all">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-50 mt-4">
                        <button type="submit" class="px-6 py-2.5 bg-[#26488C] text-white text-xs font-bold rounded-lg hover:bg-[#1a3461] transition-colors shadow-sm focus:ring-4 focus:ring-[#26488C]/20">
                            <i class="ph ph-floppy-disk mr-1.5"></i> Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Role Management -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg border border-purple-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-purple-50 flex justify-between items-center">
                    <h3 class="text-xs font-black text-purple-700 uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-shield-check"></i> Role Management
                    </h3>
                </div>
                <form method="POST" action="{{ route('osas.accounts.update-role', $details->accountId) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    
                    <input type="hidden" name="freshness" value="{{ $details->managementVersion }}">
                    
                    <div class="mb-5">
                        <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">System Role</label>
                        @if($details->role === 'super_admin')
                            <div class="w-full px-4 py-2.5 bg-purple-50 border border-purple-200 rounded-lg text-sm font-black text-purple-700 uppercase tracking-widest">
                                <i class="ph ph-shield-check mr-1.5"></i> Dean
                            </div>
                            <p class="mt-2 text-[10px] text-gray-500 font-medium leading-relaxed">
                                This account holds the Dean appointment. The Dean role only changes through Dean appointment management.
                            </p>
                        @else
                            <select name="role" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-300 focus:border-transparent transition-all">
                                <option value="student" {{ $details->role === 'student' ? 'selected' : '' }}>Student</option>
                                <option value="admin" {{ $details->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="osas_admin" {{ $details->role === 'osas_admin' ? 'selected' : '' }}>OSAS Admin</option>
                            </select>
                            <p class="mt-2 text-[10px] text-gray-500 font-medium leading-relaxed">
                                Changing a user's role affects their system-wide access permissions. Dean appointments are managed separately.
                            </p>
                        @endif
                    </div>
                    
                    @if($details->role !== 'super_admin')
                        <div class="pt-4 border-t border-gray-50">
                            <button type="submit" class="w-full px-4 py-2 bg-purple-50 border border-purple-200 text-purple-700 text-xs font-bold rounded-lg hover:bg-purple-100 transition-colors">
                                Update Role
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    
    .container-fluid {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
</style>
@endsection
