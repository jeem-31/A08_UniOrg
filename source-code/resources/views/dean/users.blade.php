@extends('layouts.dean')

@section('title', 'User Management')

@section('content')
@php
    $studentRecords = $students instanceof \Illuminate\Pagination\LengthAwarePaginator ? $students->getCollection() : collect($students);
    $adminRecords = $admins instanceof \Illuminate\Pagination\LengthAwarePaginator ? $admins->getCollection() : collect($admins);
    $userStats = $userStats ?? [
        'students' => $studentRecords->count(),
        'admins' => $adminRecords->count(),
        'total' => $studentRecords->count() + $adminRecords->count(),
        'active' => $studentRecords->where('is_active', true)->count() + $adminRecords->where('is_active', true)->count(),
    ];
    $search = $search ?? request('search', '');
@endphp

<div class="dean-users-page" x-data="deanUsersDirectory($el)" data-search="{{ e($search) }}" data-admin-ids="{{ $adminRecords->pluck('id')->values()->toJson() }}" data-student-ids="{{ $studentRecords->pluck('id')->values()->toJson() }}" @keydown.escape.window="closeToggleModal()">
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-x-8"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform translate-x-8"
             x-init="setTimeout(() => show = false, 4000)"
             class="fixed top-6 right-6 z-50 flex max-w-md items-center space-x-3 rounded-lg border-l-4 border-green-500 bg-white px-6 py-4 shadow-xl"
             role="status">
            <div class="flex-shrink-0"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100"><i class="ph ph-check-circle text-xl text-green-600"></i></div></div>
            <div class="flex-1"><p class="text-sm font-semibold text-gray-900">Success!</p><p class="text-sm text-gray-600">{!! e(session('success')) !!}</p></div>
            <button @click="show = false" class="flex-shrink-0 text-gray-400 transition hover:text-gray-600" aria-label="Dismiss notification"><i class="ph ph-x text-lg"></i></button>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform translate-x-8"
             x-transition:enter-end="opacity-100 transform translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-x-0"
             x-transition:leave-end="opacity-0 transform translate-x-8"
             x-init="setTimeout(() => show = false, 4000)"
             class="fixed top-6 right-6 z-50 flex max-w-md items-center space-x-3 rounded-lg border-l-4 border-red-500 bg-white px-6 py-4 shadow-xl"
             role="alert">
            <div class="flex-shrink-0"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100"><i class="ph ph-warning-circle text-xl text-red-600"></i></div></div>
            <div class="flex-1"><p class="text-sm font-semibold text-gray-900">Error!</p><p class="text-sm text-gray-600">{!! e(session('error')) !!}</p></div>
            <button @click="show = false" class="flex-shrink-0 text-gray-400 transition hover:text-gray-600" aria-label="Dismiss notification"><i class="ph ph-x text-lg"></i></button>
        </div>
    @endif

    <div class="dean-users-shell">
        <header class="dean-users-header">
            <div>
                <div class="dean-users-eyebrow">Department directory</div>
                <h1>User management</h1>
                <p>Students are view-only. You can deactivate or reactivate organization admins in your department.</p>
            </div>
            <a href="{{ route('dean.dashboard') }}" class="dean-users-back"><i class="ph ph-arrow-left"></i>Dashboard</a>
        </header>

        <section class="dean-users-metrics" aria-label="User directory summary">
            <div class="dean-users-metric"><span class="dean-users-metric__icon dean-users-metric__icon--purple"><i class="ph ph-users-three"></i></span><div><span>Total accounts</span><strong>{{ number_format($userStats['total']) }}</strong></div></div>
            <div class="dean-users-metric"><span class="dean-users-metric__icon dean-users-metric__icon--blue"><i class="ph ph-graduation-cap"></i></span><div><span>Students</span><strong>{{ number_format($userStats['students']) }}</strong></div></div>
            <div class="dean-users-metric"><span class="dean-users-metric__icon dean-users-metric__icon--gold"><i class="ph ph-user"></i></span><div><span>Organization admins</span><strong>{{ number_format($userStats['admins']) }}</strong></div></div>
            <div class="dean-users-metric"><span class="dean-users-metric__icon dean-users-metric__icon--teal"><i class="ph ph-check-circle"></i></span><div><span>Active accounts</span><strong>{{ number_format($userStats['active']) }}</strong></div></div>
        </section>

        <form method="GET" action="{{ route('dean.users.index') }}" class="dean-users-toolbar" data-dean-users-search @submit="isSearching = true">
            <div class="dean-users-toolbar__controls">
                <div class="dean-users-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="search" name="search" x-model="search" placeholder="Search name, email, student ID, course, or year" aria-label="Search users">
                    <a href="{{ route('dean.users.index') }}" class="dean-users-search__clear" data-dean-navigation data-dean-partial="users" data-dean-skeleton="users" x-show="search.length > 0" x-cloak aria-label="Clear search"><i class="ph ph-x"></i></a>
                </div>
                <button type="submit" class="dean-users-filter-btn" :disabled="isSearching"><i class="ph" :class="isSearching ? 'ph-spinner ph-spin' : 'ph-funnel'"></i><span x-text="isSearching ? 'Loading...' : 'Filter'">Filter</span></button>
            </div>
            <div class="dean-users-toolbar__meta"><strong>{{ number_format($userStats['total']) }}</strong> matching account{{ $userStats['total'] === 1 ? '' : 's' }}@if($search !== '') <span>for “{{ $search }}”</span>@endif</div>
        </form>

        <div class="dean-users-grid">
            <section class="dean-users-panel dean-users-panel--admins">
                <div class="dean-users-panel__header">
                    <div><span class="dean-users-panel__eyebrow">ORGANIZATION ACCESS</span><h2>Admins</h2><p>Deactivate or reactivate org admins. Students are view-only.</p></div>
                    <span class="dean-users-panel__count">{{ number_format($userStats['admins']) }}</span>
                </div>
                <div class="dean-users-table-wrap">
                    <table class="dean-users-table dean-users-table--admins">
                        <thead><tr><th>User</th><th>Position</th><th>Status</th><th><span class="sr-only">Deactivate</span></th></tr></thead>
                        <tbody>
                            @forelse($admins as $admin)
                                @php $adminInitial = strtoupper(substr(trim((string) $admin->first_name), 0, 1)) ?: strtoupper(substr(trim((string) $admin->full_name), 0, 1)) ?: 'A'; @endphp
                                <tr x-show="visibleAdminIds.has(String({{ $admin->id }}))">
                                    <td><div class="dean-user-cell"><span class="dean-user-avatar dean-user-avatar--gold">@if($admin->photo)<img src="{{ storage_url($admin->photo) }}" alt="" loading="lazy" decoding="async" onerror="this.hidden=true; this.nextElementSibling.hidden=false;"><span hidden>{{ $adminInitial }}</span>@else{{ $adminInitial }}@endif</span><div><strong>{{ $admin->full_name }}</strong><small>{{ $admin->email }}</small></div></div></td>
                                    <td><span class="dean-user-position">{{ $admin->primary_position }}</span></td>
                                    <td><span class="dean-user-status {{ $admin->is_active ? 'dean-user-status--active' : 'dean-user-status--inactive' }}"><span></span>{{ $admin->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="dean-users-action"><button type="button" @click="openToggleModal('{{ $admin->id }}', {{ Js::from($admin->full_name) }}, {{ $admin->is_active ? 'true' : 'false' }})" aria-label="{{ $admin->is_active ? 'Deactivate ' : 'Reactivate ' }}{{ $admin->full_name }}" title="{{ $admin->is_active ? 'Deactivate account' : 'Reactivate account' }}"><i class="ph {{ $admin->is_active ? 'ph-pause-circle' : 'ph-power' }}"></i></button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="dean-users-empty"><i class="ph ph-shield-check"></i><strong>No admins found</strong><span>Try a different search term.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dean-users-panel__footer"><span x-text="admins.length ? 'Showing ' + (((adminPage - 1) * perPage) + 1) + '–' + Math.min(adminPage * perPage, admins.length) + ' of ' + admins.length : 'No records'"></span><nav class="dean-users-pagination" x-show="adminPages > 1" aria-label="Admins pagination"><a href="#" @click.prevent="goToPage('admins', adminPage - 1)" :class="adminPage === 1 ? 'is-disabled' : ''" aria-label="Previous admin page"><i class="ph ph-caret-left"></i></a><template x-for="page in adminPages" :key="'admin-' + page"><a href="#" @click.prevent="goToPage('admins', page)" :class="page === adminPage ? 'is-current' : ''" x-text="page" :aria-label="'Go to admin page ' + page"></a></template><a href="#" @click.prevent="goToPage('admins', adminPage + 1)" :class="adminPage === adminPages ? 'is-disabled' : ''" aria-label="Next admin page"><i class="ph ph-caret-right"></i></a></nav></div>
            </section>

            <section class="dean-users-panel dean-users-panel--students">
                <div class="dean-users-panel__header">
                    <div><span class="dean-users-panel__eyebrow">STUDENT ACCOUNTS</span><h2>Students</h2><p>Enrolled users in your department.</p></div>
                    <span class="dean-users-panel__count">{{ number_format($userStats['students']) }}</span>
                </div>
                <div class="dean-users-table-wrap">
                    <table class="dean-users-table dean-users-table--students">
                        <thead><tr><th>User</th><th>Academic record</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($students as $student)
                                @php $studentInitial = strtoupper(substr(trim((string) $student->first_name), 0, 1)) ?: strtoupper(substr(trim((string) $student->full_name), 0, 1)) ?: 'S'; @endphp
                                <tr x-show="visibleStudentIds.has(String({{ $student->id }}))">
                                    <td><div class="dean-user-cell"><span class="dean-user-avatar dean-user-avatar--purple">@if($student->photo)<img src="{{ storage_url($student->photo) }}" alt="" loading="lazy" decoding="async" onerror="this.hidden=true; this.nextElementSibling.hidden=false;"><span hidden>{{ $studentInitial }}</span>@else{{ $studentInitial }}@endif</span><div><strong>{{ $student->full_name }}</strong><small>{{ $student->email }}</small></div></div></td>
                                    <td><div class="dean-academic-cell"><strong>{{ $student->student_id ?: 'No ID' }}</strong><small>{{ $student->course ?: 'Course not set' }} · {{ $student->year_level ?: 'Year not set' }}</small></div></td>
                                    <td><span class="dean-user-status {{ $student->is_active ? 'dean-user-status--active' : 'dean-user-status--inactive' }}"><span></span>{{ $student->is_active ? 'Active' : 'Inactive' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><div class="dean-users-empty"><i class="ph ph-student"></i><strong>No students found</strong><span>Try a different search term.</span></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="dean-users-panel__footer"><span x-text="students.length ? 'Showing ' + (((studentPage - 1) * perPage) + 1) + '–' + Math.min(studentPage * perPage, students.length) + ' of ' + students.length : 'No records'"></span><nav class="dean-users-pagination" x-show="studentPages > 1" aria-label="Students pagination"><a href="#" @click.prevent="goToPage('students', studentPage - 1)" :class="studentPage === 1 ? 'is-disabled' : ''" aria-label="Previous student page"><i class="ph ph-caret-left"></i></a><template x-for="page in studentPages" :key="'student-' + page"><a href="#" @click.prevent="goToPage('students', page)" :class="page === studentPage ? 'is-current' : ''" x-text="page" :aria-label="'Go to student page ' + page"></a></template><a href="#" @click.prevent="goToPage('students', studentPage + 1)" :class="studentPage === studentPages ? 'is-disabled' : ''" aria-label="Next student page"><i class="ph ph-caret-right"></i></a></nav></div>
            </section>
        </div>
    </div>

    <div x-show="showToggleModal" x-cloak class="fixed inset-0 z-[9999] overflow-y-auto" style="display: none;">
        <div class="flex min-h-screen items-center justify-center px-4 py-6">
            <div @click="closeToggleModal()" class="fixed inset-0 bg-slate-950/55 modal-backdrop"></div>
            <div class="dean-delete-modal relative z-10 w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <div class="dean-delete-modal__icon"><i class="ph" :class="toggleIsActive ? 'ph-pause-circle' : 'ph-power'"></i></div>
                <div class="text-center"><h3 x-text="toggleIsActive ? 'Deactivate admin account?' : 'Reactivate admin account?'"></h3><p><span x-text="toggleIsActive ? 'This will deactivate ' : 'This will reactivate '"></span><strong x-text="toggleUserName"></strong><span x-text="toggleIsActive ? ' and block their organization access until reactivated.' : ' and restore their organization access.'"></span></p></div>
                <form :action="'{{ url('/dean/users') }}/' + toggleUserId + '/lifecycle'" method="POST" @submit="isToggling = true">
                    @csrf @method('PUT')
                    <input type="hidden" name="action" :value="toggleIsActive ? 'deactivate' : 'activate'">
                    <div class="dean-delete-modal__actions"><button type="button" @click="closeToggleModal()" :disabled="isToggling" class="dean-delete-modal__cancel">Cancel</button><button type="submit" :disabled="isToggling" class="dean-delete-modal__confirm" :style="toggleIsActive ? '' : 'background:#19794e'"><i class="ph" :class="isToggling ? 'ph-spinner ph-spin' : (toggleIsActive ? 'ph-pause-circle' : 'ph-power')"></i><span x-text="isToggling ? 'Updating...' : (toggleIsActive ? 'Deactivate' : 'Reactivate')"></span></button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .dean-users-page { --du-accent: var(--primary-color, #8238b3); --du-gold: var(--complementary-color, #d7ac28); --du-ink: #20212b; --du-muted: #767985; --du-line: #e7e8ed; min-height: calc(100vh - 64px); background: #f7f7fa; color: var(--du-ink); font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif; transition: opacity .16s ease; }
    .dean-users-page.is-loading { opacity: .7; pointer-events: none; }
    .dean-users-shell { width: min(100%, 1680px); margin: 0 auto; padding: 28px clamp(20px, 2.4vw, 42px) 42px; }
    .dean-users-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; margin-bottom: 20px; }
    .dean-users-eyebrow, .dean-users-panel__eyebrow { color: var(--du-muted); font-size: 10px; font-weight: 800; letter-spacing: .14em; line-height: 1.2; text-transform: uppercase; }
    .dean-users-eyebrow { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
    .dean-users-header h1 { margin: 0; font-size: clamp(25px, 2.1vw, 34px); font-weight: 800; letter-spacing: -.04em; line-height: 1.1; }
    .dean-users-header p { margin: 7px 0 0; color: var(--du-muted); font-size: 13px; line-height: 1.5; }
    .dean-users-back { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--du-line); border-radius: 7px; background: #fff; color: #555967; font-size: 11px; font-weight: 750; padding: 9px 12px; text-decoration: none; transition: border-color .18s ease, background .18s ease; }
    .dean-users-back:hover { border-color: #cfd1d9; background: #fbfbfc; }
    .dean-users-back i { color: var(--du-accent); }
    .dean-users-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
    .dean-users-metric { display: flex; align-items: center; gap: 11px; min-width: 0; border: 1px solid var(--du-line); border-radius: 8px; background: #fff; padding: 13px 14px; box-shadow: 0 1px 2px rgba(32,33,43,.02); }
    .dean-users-metric__icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 9px; font-size: 14px; line-height: 1; box-shadow: inset 0 0 0 1px rgba(32,33,43,.04); }
    .dean-users-metric__icon i { display: block; line-height: 1; }
    .dean-users-metric__icon--purple { background: #f2ebf8; color: var(--du-accent); } .dean-users-metric__icon--blue { background: #eaf2fb; color: #3173b8; } .dean-users-metric__icon--gold { background: #fbf5e4; color: #ad8211; } .dean-users-metric__icon--teal { background: #e8f5f2; color: #16816f; }
    .dean-users-metric div { min-width: 0; } .dean-users-metric > div > span { display: block; overflow: hidden; color: var(--du-muted); font-size: 10px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; } .dean-users-metric strong { display: block; margin-top: 3px; font-size: 21px; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
    .dean-users-toolbar { display: flex; align-items: center; justify-content: flex-end; gap: 14px; margin-bottom: 12px; border: 1px solid var(--du-line); border-radius: 8px; background: #fff; padding: 9px; }
    .dean-users-toolbar__controls { display: flex; align-items: center; min-width: 0; width: min(100%, 440px); flex: 0 1 440px; gap: 9px; }
    .dean-users-search { display: flex; align-items: center; min-width: 240px; flex: 1; gap: 9px; color: #a0a2aa; }
    .dean-users-search > i { padding-left: 3px; font-size: 12px; }
    .dean-users-search input { min-width: 0; width: 100%; border: 0; outline: 0; background: transparent; color: #3f414b; font-size: 11px; }
    .dean-users-search input::placeholder { color: #a4a6ae; }
    .dean-users-search__clear { display: inline-flex; align-items: center; justify-content: center; width: 23px; height: 23px; border-radius: 5px; color: #9497a0; font-size: 10px; text-decoration: none; }
    .dean-users-search__clear:hover { background: #f1f1f4; color: #555963; }
    .dean-users-filter-btn { display: inline-flex; align-items: center; gap: 7px; min-height: 32px; border: 0; border-radius: 6px; background: var(--du-accent); color: #fff; padding: 0 12px; font-size: 10px; font-weight: 800; cursor: pointer; }
    .dean-users-filter-btn:hover { filter: brightness(.95); }
    .dean-users-toolbar__meta { border-left: 1px solid var(--du-line); color: #92959f; font-size: 10px; padding: 0 8px 0 14px; white-space: nowrap; }
    .dean-users-toolbar__meta strong { color: #51545f; font-size: 11px; }
    .dean-users-toolbar__meta span { color: var(--du-accent); }
    .dean-users-grid { display: grid; grid-template-columns: minmax(320px, .78fr) minmax(520px, 1.22fr); gap: 12px; align-items: start; }
    .dean-users-panel { overflow: hidden; min-width: 0; border: 1px solid var(--du-line); border-radius: 8px; background: #fff; box-shadow: 0 1px 2px rgba(32,33,43,.02); }
    .dean-users-panel__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; border-bottom: 1px solid #eff0f3; padding: 16px 17px 13px; }
    .dean-users-panel__header h2 { margin: 5px 0 0; font-size: 15px; font-weight: 800; letter-spacing: -.02em; }
    .dean-users-panel__header p { margin: 4px 0 0; color: var(--du-muted); font-size: 10px; }
    .dean-users-panel__count { display: inline-flex; align-items: center; justify-content: center; min-width: 29px; height: 25px; border-radius: 6px; background: #f3f1f7; color: var(--du-accent); font-size: 11px; font-weight: 800; }
    .dean-users-table-wrap { overflow-x: auto; }
    .dean-users-table { width: 100%; border-collapse: collapse; }
    .dean-users-table th { padding: 9px 14px; border-bottom: 1px solid #eff0f3; color: #999ca5; font-size: 9px; font-weight: 800; letter-spacing: .1em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .dean-users-table td { padding: 10px 14px; border-bottom: 1px solid #f0f1f3; font-size: 10px; vertical-align: middle; }
    .dean-users-table tbody tr { transition: background .15s ease; } .dean-users-table tbody tr:hover { background: #fcfbfd; } .dean-users-table tbody tr:last-child td { border-bottom: 0; }
    .dean-user-cell { display: flex; align-items: center; min-width: 185px; gap: 9px; }
    .dean-user-avatar { display: inline-flex; align-items: center; justify-content: center; overflow: hidden; width: 30px; height: 30px; flex: 0 0 30px; border-radius: 7px; font-size: 9px; font-weight: 800; }
    .dean-user-avatar img { display: block; width: 100%; height: 100%; object-fit: cover; }
    .dean-user-avatar--purple { background: #f2ebf8; color: var(--du-accent); } .dean-user-avatar--gold { background: #fbf5e4; color: #ad8211; }
    .dean-user-cell div, .dean-academic-cell { min-width: 0; } .dean-user-cell strong, .dean-user-cell small, .dean-academic-cell strong, .dean-academic-cell small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dean-user-cell strong { max-width: 190px; color: #444650; font-size: 10px; font-weight: 800; } .dean-user-cell small { max-width: 190px; margin-top: 3px; color: #999ca5; font-size: 9px; }
    .dean-academic-cell strong { color: #555861; font-size: 10px; font-weight: 800; } .dean-academic-cell small { max-width: 150px; margin-top: 3px; color: #999ca5; font-size: 9px; }
    .dean-user-position { display: inline-flex; max-width: 145px; overflow: hidden; border-radius: 5px; background: #f3f1f7; color: var(--du-accent); padding: 4px 7px; font-size: 9px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
    .dean-user-status { display: inline-flex; align-items: center; gap: 5px; border-radius: 5px; padding: 4px 7px; font-size: 9px; font-weight: 800; white-space: nowrap; } .dean-user-status > span { width: 5px; height: 5px; border-radius: 50%; }
    .dean-user-status--active { background: #effaf4; color: #19794e; } .dean-user-status--active > span { background: #27a36b; } .dean-user-status--inactive { background: #fcecee; color: #b24857; } .dean-user-status--inactive > span { background: #d45d6b; }
    .dean-users-action { width: 31px; text-align: right; } .dean-users-action button { border: 0; background: transparent; color: #b7bac1; cursor: pointer; font-size: 10px; padding: 5px; } .dean-users-action button:hover { color: #c24d5b; }
    .dean-users-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; min-height: 170px; color: #9a9da6; text-align: center; } .dean-users-empty i { margin-bottom: 3px; color: #c6c8ce; font-size: 18px; } .dean-users-empty strong { color: #61646e; font-size: 11px; } .dean-users-empty span { font-size: 9px; }
    .dean-users-panel__footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 43px; border-top: 1px solid #eff0f3; color: #9a9da6; padding: 7px 14px; font-size: 9px; }
    .dean-users-pagination { display: flex; align-items: center; gap: 2px; max-width: 65%; overflow: hidden; } .dean-users-pagination a, .dean-users-pagination > span { display: inline-flex; align-items: center; justify-content: center; min-width: 23px; height: 23px; border: 1px solid var(--du-line); border-radius: 4px; background: #fff; color: #777a84; font-size: 9px; text-decoration: none; } .dean-users-pagination a:hover { border-color: var(--du-accent); color: var(--du-accent); } .dean-users-pagination a.is-current, .dean-users-pagination > span.is-current { border-color: var(--du-accent); background: var(--du-accent); color: #fff; } .dean-users-pagination a.is-disabled, .dean-users-pagination > span.is-disabled { pointer-events: none; color: #c4c6cc; background: #fafafd; } .dean-users-pagination > span.dean-users-pagination__ellipsis { min-width: 15px; border-color: transparent; background: transparent; color: #9a9da6; }
    .dean-delete-modal__icon { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; margin: 0 auto 13px; border-radius: 50%; background: #fcecee; color: #c24d5b; font-size: 16px; }
    .dean-delete-modal h3 { margin: 0; color: #2d2f38; font-size: 16px; font-weight: 800; } .dean-delete-modal p { margin: 7px auto 20px; color: #777a84; font-size: 11px; line-height: 1.5; } .dean-delete-modal p strong { color: #464852; }
    .dean-delete-modal__actions { display: flex; justify-content: center; gap: 8px; } .dean-delete-modal__actions button { border-radius: 6px; padding: 9px 13px; font-size: 10px; font-weight: 800; cursor: pointer; } .dean-delete-modal__cancel { border: 1px solid var(--du-line); background: #f7f7f9; color: #5f626d; } .dean-delete-modal__confirm { display: inline-flex; align-items: center; gap: 7px; border: 0; background: #c24d5b; color: #fff; } .dean-delete-modal__confirm:hover { background: #ae3f4d; } .dean-delete-modal__actions button:disabled { cursor: not-allowed; opacity: .55; }
    @media (max-width: 1180px) { .dean-users-grid { grid-template-columns: 1fr; } .dean-users-panel--admins { order: 1; } .dean-users-panel--students { order: 2; } }
    @media (max-width: 760px) { .dean-users-shell { padding: 20px 14px 30px; } .dean-users-header { align-items: flex-start; flex-direction: column; } .dean-users-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dean-users-toolbar { align-items: stretch; justify-content: flex-start; flex-wrap: wrap; gap: 7px; } .dean-users-toolbar__controls { width: 100%; flex-basis: 100%; } .dean-users-search { min-width: 0; } .dean-users-toolbar__meta { border-left: 0; padding-left: 3px; } }
    @media (max-width: 460px) { .dean-users-metrics { gap: 7px; } .dean-users-metric { padding: 11px; } .dean-users-metric__icon { width: 31px; height: 31px; flex-basis: 31px; font-size: 13px; } .dean-users-metric strong { font-size: 19px; } .dean-users-metric > div > span { font-size: 9px; } .dean-users-panel__header { padding-left: 13px; padding-right: 13px; } .dean-users-panel__footer { align-items: flex-start; flex-direction: column; } .dean-users-pagination { max-width: 100%; } }
</style>

<script>
function deanUsersDirectory(element) {
    return {
        showToggleModal: false,
        toggleUserId: null,
        toggleUserName: '',
        toggleIsActive: true,
        isToggling: false,
        showDeleteModal: false,
        deleteUserId: null,
        deleteUserName: '',
        isDeleting: false,
        isSearching: false,
        search: element.dataset.search || '',
        admins: JSON.parse(element.dataset.adminIds || '[]'),
        students: JSON.parse(element.dataset.studentIds || '[]'),
        adminPage: 1,
        studentPage: 1,
        perPage: 10,
        get adminPages() { return Math.ceil(this.admins.length / this.perPage) || 1; },
        get studentPages() { return Math.ceil(this.students.length / this.perPage) || 1; },
        get visibleAdminIds() { return new Set(this.admins.slice((this.adminPage - 1) * this.perPage, this.adminPage * this.perPage).map(String)); },
        get visibleStudentIds() { return new Set(this.students.slice((this.studentPage - 1) * this.perPage, this.studentPage * this.perPage).map(String)); },
        goToPage(type, page) {
            const total = type === 'admins' ? this.adminPages : this.studentPages;
            if (page < 1 || page > total) return;
            if (type === 'admins') this.adminPage = page;
            else this.studentPage = page;
        },
        openDeleteModal(id, name) {
            this.deleteUserId = id;
            this.deleteUserName = name;
            this.isDeleting = false;
            this.showDeleteModal = true;
        },
        closeDeleteModal() {
            if (!this.isDeleting) this.showDeleteModal = false;
        },
        openToggleModal(id, name, isActive) {
            this.toggleUserId = String(id);
            this.toggleUserName = name;
            this.toggleIsActive = !!isActive;
            this.isToggling = false;
            this.showToggleModal = true;
        },
        closeToggleModal() {
            if (!this.isToggling) this.showToggleModal = false;
        }
    };
}
</script>
@endsection
