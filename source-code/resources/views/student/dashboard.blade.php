@extends('layouts.app')
@section('title','Student Dashboard')
@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush
@section('content')
@php
 $user=auth()->user();$firstName=explode(' ',trim($user->first_name))[0];$nextEvent=$upcomingEvents->first();
 $outstanding=$paymentAnnouncements->filter(fn($p)=>optional($studentPaymentStatuses->get($p->id))->status!=='completed');$balance=$outstanding->sum('amount');
@endphp
<style>
.student-dashboard{
 --neu-bg:#E9EDF3;
 --neu-text:#2E3A4C;
 --neu-muted:#5A6B80;
 --neu-faint:#7C8A9E;
 --neu-divider:rgba(163,177,198,.45);
 --neu-dark-shadow:rgba(163,177,198,.6);
 --neu-light-shadow:rgba(255,255,255,.85);
 background:var(--neu-bg);
 color:var(--neu-text);
 font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
 -webkit-font-smoothing:antialiased;
}
.student-panel{background:var(--neu-bg);border-radius:22px;box-shadow:10px 10px 20px var(--neu-dark-shadow),-10px -10px 20px var(--neu-light-shadow)}
.student-panel-inset{background:var(--neu-bg);border-radius:22px;box-shadow:inset 6px 6px 12px rgba(163,177,198,.55),inset -6px -6px 12px rgba(255,255,255,.8)}
.student-row,.student-glance-item{transition:box-shadow .2s ease,transform .2s ease}
.student-row:hover,.student-row:focus-visible,.student-glance-item:hover,.student-glance-item:focus-visible{box-shadow:inset 4px 4px 8px rgba(163,177,198,.45),inset -4px -4px 8px rgba(255,255,255,.75)}
.student-chip{background:var(--neu-bg);box-shadow:5px 5px 10px rgba(163,177,198,.55),-5px -5px 10px rgba(255,255,255,.85)}
.student-chip-neutral{background:var(--neu-bg);color:var(--neu-faint);box-shadow:4px 4px 8px rgba(163,177,198,.5),-4px -4px 8px rgba(255,255,255,.85)}
.student-chip-primary{background:rgba(var(--primary-color-rgb,124,58,237),.13);color:var(--primary-color);box-shadow:5px 5px 10px rgba(var(--primary-color-rgb,124,58,237),.25),-5px -5px 10px rgba(255,255,255,.9)}
.student-chip-complementary{background:rgba(var(--complementary-color-rgb,215,172,40),.16);color:var(--complementary-color);box-shadow:5px 5px 10px rgba(var(--complementary-color-rgb,215,172,40),.25),-5px -5px 10px rgba(255,255,255,.9)}
.student-btn-solid{background:var(--primary-color);color:#fff;border-radius:14px;box-shadow:6px 6px 14px rgba(var(--primary-color-rgb,124,58,237),.4),-6px -6px 14px rgba(255,255,255,.85);transition:box-shadow .2s ease,transform .2s ease}
.student-btn-solid:hover,.student-btn-solid:focus-visible{transform:translateY(1px);box-shadow:inset 4px 4px 10px rgba(0,0,0,.18),inset -4px -4px 10px rgba(255,255,255,.18)}
.student-btn-soft{background:rgba(var(--primary-color-rgb,124,58,237),.12);color:var(--primary-color);border-radius:14px;box-shadow:5px 5px 10px rgba(var(--primary-color-rgb,124,58,237),.22),-5px -5px 10px rgba(255,255,255,.9);transition:box-shadow .2s ease}
.student-btn-soft:hover,.student-btn-soft:focus-visible{box-shadow:inset 4px 4px 8px rgba(var(--primary-color-rgb,124,58,237),.28),inset -4px -4px 8px rgba(255,255,255,.85)}
.student-kicker{color:var(--primary-color);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.student-line-clamp{display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;-webkit-line-clamp:2}
.student-timeline{position:relative}
.student-timeline:before{content:'';position:absolute;left:7px;top:12px;bottom:12px;width:1px;background:var(--neu-divider)}
.student-dot{background:var(--complementary-color);border:4px solid var(--neu-bg);box-shadow:0 0 0 1px rgba(163,177,198,.5)}
.student-dashboard .text-gray-900,.student-dashboard .text-gray-800{color:var(--neu-text)}
.student-dashboard .text-gray-600,.student-dashboard .text-gray-500{color:var(--neu-muted)}
.student-dashboard .text-gray-400,.student-dashboard .text-gray-300{color:var(--neu-faint)}
.student-dashboard .text-purple-600{color:var(--primary-color)}
.student-dashboard .text-green-600{color:#15803D}
.student-dashboard .text-orange-600{color:#C2410C}
.student-dashboard .bg-green-50{background:rgba(21,128,61,.1)}
.student-dashboard .bg-gray-100{background:#DDE3EC}
.student-dashboard .divide-gray-100>*+*{border-color:var(--neu-divider)}
.student-dashboard .border-gray-100{border-color:var(--neu-divider)}
.student-dashboard a:focus-visible{box-shadow:0 0 0 3px rgba(var(--primary-color-rgb,124,58,237),.45)}
</style>
<main class="student-dashboard min-h-screen -my-4 sm:-my-8 py-4 sm:py-8">
 <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8">
  @if(auth()->user()->isSystemlessFallback() && auth()->user()->role === 'admin')
   <div x-data="{ show: !sessionStorage.getItem('systemless_banner_dismissed') }" x-show="show" x-transition x-init="show && setTimeout(() => { show = false; sessionStorage.setItem('systemless_banner_dismissed', '1'); }, 5000)" class="student-panel mb-6 flex items-start gap-3 border border-amber-200/80 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status" style="display: none;">
    <i class="ph ph-info mt-0.5 text-amber-600"></i>
    <p class="flex-1">You are viewing <span class="font-semibold">{{ organization_name() }}</span> because your organization does not have a system yet. You can manage your organization once its system is created.</p>
    <button @click="show = false; sessionStorage.setItem('systemless_banner_dismissed', '1')" class="flex-shrink-0 text-amber-600 hover:text-amber-800 transition p-1 -m-1" aria-label="Dismiss">
     <i class="ph ph-x text-lg"></i>
    </button>
   </div>
  @endif
  <header class="mb-7 flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
   <div>
    <p class="student-kicker mb-2">{{ now()->format('l, F j') }} · {{ $orgAcronym??'Student Portal' }}</p>
    <h1 class="text-3xl sm:text-4xl font-black tracking-tight">Hi, {{ $firstName }}</h1>
    <p class="text-gray-500 mt-2">Here’s what’s happening in your student life today.</p>
   </div>
   <section class="student-panel w-full overflow-hidden xl:w-[690px] xl:flex-shrink-0" aria-label="Today at a glance">
    <h2 class="sr-only">Today at a glance</h2>
    <div class="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
     <a href="{{ $nextEvent ? route('events.show', $nextEvent) : route('events.index') }}" @if(! $nextEvent) data-student-navigation @endif class="student-glance-item flex min-w-0 items-center gap-2 px-4 py-3">
      <i class="ph ph-calendar-dots flex h-7 w-7 flex-shrink-0 items-center justify-center text-lg" style="color:var(--primary-color)" aria-hidden="true"></i>
      <span class="min-w-0">
       <span class="block text-[10px] font-bold uppercase tracking-wide text-gray-400">Next event</span>
       <span class="block text-sm font-bold leading-5 text-gray-800">{{ $nextEvent ? $nextEvent->title : 'No upcoming event' }}</span>
       <span class="block text-[11px] font-bold text-gray-500">{{ $nextEvent ? $nextEvent->start_date->format('M d') : '—' }}</span>
      </span>
     </a>
     <a href="{{ route('payments.index') }}" data-student-navigation class="student-glance-item flex min-w-0 items-center gap-2 px-4 py-3">
      <i class="ph ph-receipt flex h-7 w-7 flex-shrink-0 items-center justify-center text-lg" style="color:var(--complementary-color)" aria-hidden="true"></i>
      <span class="min-w-0">
       <span class="block text-[10px] font-bold uppercase tracking-wide text-gray-400">Payment status</span>
       <span class="block text-sm font-bold leading-5 text-gray-800">{{ $outstanding->isNotEmpty() ? number_format($balance, 2) . ' due' : 'All caught up' }}</span>
       <span class="block text-[11px] font-bold text-gray-500">{{ $outstanding->count() ? $outstanding->count() . ' open' : 'Paid' }}</span>
      </span>
     </a>
     <button type="button" onclick="document.querySelector('[data-student-notification-toggle]')?.click()" class="student-glance-item flex min-w-0 items-center gap-2 px-4 py-3 text-left">
      <i class="ph ph-bell flex h-7 w-7 flex-shrink-0 items-center justify-center text-lg" style="color:var(--primary-color)" aria-hidden="true"></i>
      <span class="min-w-0">
       <span class="block text-[10px] font-bold uppercase tracking-wide text-gray-400">New updates</span>
       <span class="block text-sm font-bold leading-5 text-gray-800">{{ $visibleUnreadNotifications->isNotEmpty() ? $visibleUnreadNotifications->count() . ' unread notification' . ($visibleUnreadNotifications->count() === 1 ? '' : 's') : 'You are up to date' }}</span>
       <span class="block text-[11px] font-bold text-gray-500">View notifications</span>
      </span>
     </button>
    </div>
   </section>
  </header>
  <section class="student-panel-inset p-6 sm:p-7 mb-6">
   <p class="student-kicker mb-4">Your next thing to look forward to</p>
   @if($nextEvent)
   <h2 class="text-2xl sm:text-3xl font-black">{{ $nextEvent->start_date->isToday()?'Something is happening today.':'Save the date for your next event.' }}</h2>
   <p class="text-gray-600 mt-2">{{ $nextEvent->title }} · {{ $nextEvent->start_date->format('M d, Y · h:i A') }}{{ $nextEvent->venue?' · '.$nextEvent->venue:'' }}</p>
   <a href="{{ route('events.show',$nextEvent) }}" data-student-navigation class="student-btn-solid inline-flex items-center mt-5 px-4 py-2.5 text-sm font-bold">See event details <i class="ph ph-arrow-right ml-2 text-sm leading-none"></i></a>
   @else
   <h2 class="text-2xl sm:text-3xl font-black">You’re all caught up for now.</h2>
   <p class="text-gray-600 mt-2">Take a look around, find a resource, or catch up on campus updates.</p>
   <a href="{{ route('student.announcements.index') }}" data-student-navigation class="student-btn-solid inline-flex items-center mt-5 px-4 py-2.5 text-sm font-bold">See campus updates <i class="ph ph-arrow-right ml-2 text-sm leading-none"></i></a>
   @endif
  </section>
  <nav class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">@foreach([['student.announcements.index','ph-megaphone','Catch up','Campus updates','student-chip-primary'],['events.index','ph-calendar-dots','Explore events','Join something new','student-chip-primary'],['payments.index','ph-receipt','My payments','Receipts and status','student-chip-complementary'],['library.index','ph-book-open','Study resources','Browse e-Library','student-chip-complementary']] as $a)<a href="{{ route($a[0]) }}" @if($a[0] !== 'events.index') data-student-navigation @endif class="student-panel student-row p-4 flex items-center gap-3"><i class="ph {{ $a[1] }} student-chip {{ $a[4] }} flex h-10 w-10 flex-shrink-0 items-center justify-center text-xl"></i><span class="min-w-0"><span class="block font-bold text-sm">{{ $a[2] }}</span><span class="block text-xs text-gray-500">{{ $a[3] }}</span></span><i class="ph ph-caret-right text-xs text-gray-300 ml-auto"></i></a>@endforeach</nav>
  <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.55fr)_minmax(330px,.85fr)] gap-5 items-start">
   <div class="space-y-5">
    <section class="student-panel">
     <div class="px-5 sm:px-6 py-5 flex justify-between items-center">
      <div>
       <p class="student-kicker">What’s new</p>
       <h2 class="text-xl font-black mt-1">Campus updates</h2>
      </div>
      <a href="{{ route('student.announcements.index') }}" data-student-navigation class="text-sm font-bold text-purple-600">View all <i class="ph ph-arrow-right ml-1"></i></a>
     </div>
     <div class="divide-y divide-gray-100 px-2 sm:px-3 pb-2 sm:pb-3">
       @forelse($announcements->take(4) as $an)
       <a href="{{ route('student.announcements.show',$an) }}" data-student-navigation class="student-row p-3 sm:p-4 flex gap-3 items-start">
        @if(is_array($an->images) && !empty($an->images[0]))
        <img src="{{ storage_url($an->images[0]) }}" alt="{{ $an->title }}" class="w-14 h-14 rounded-xl object-cover flex-shrink-0" loading="lazy">
       @else
       <span class="w-14 h-14 rounded-xl flex items-center justify-center student-chip-primary flex-shrink-0"><i class="ph ph-megaphone"></i></span>
       @endif
       <span class="min-w-0 flex-1">
        <span class="flex items-center gap-2 text-[10px] font-bold uppercase text-purple-600">{{ $an->type }} <span class="text-gray-400 normal-case font-normal">{{ $an->created_at->diffForHumans() }}</span></span>
        <span class="block font-bold text-sm mt-1 student-line-clamp">{{ $an->title }}</span>
        <span class="block text-sm text-gray-500 mt-1 student-line-clamp">{{ Str::limit(strip_tags($an->content??''),130) }}</span>
       </span>
       <i class="ph ph-arrow-square-out text-xs text-gray-300 mt-1"></i>
      </a>
      @empty
      <div class="p-8 text-center text-sm text-gray-500">No announcements available right now.</div>
      @endforelse
     </div>
    </section>
    <section class="student-panel p-5 sm:p-6">
     <div class="flex justify-between items-center mb-5">
      <div>
       <p class="student-kicker">Find your people</p>
       <h2 class="text-xl font-black mt-1">What’s coming up</h2>
      </div>
      <a href="{{ route('events.index') }}" class="text-sm font-bold text-purple-600">See events <i class="ph ph-arrow-right ml-1"></i></a>
     </div>
     <div class="student-timeline space-y-3">
       @forelse($upcomingEvents->take(3) as $event)
       <a href="{{ route('events.show',$event) }}" data-student-navigation class="student-row relative flex gap-3 p-2 -m-2 items-start">
        <span class="student-dot relative z-10 w-4 h-4 mt-1 rounded-full flex-shrink-0"></span>
        @if(is_array($event->images) && !empty($event->images[0]))
        <img src="{{ storage_url($event->images[0]) }}" alt="{{ $event->title }}" class="w-14 h-14 rounded-xl object-cover flex-shrink-0" loading="lazy">
        @endif
        <span class="flex-1 min-w-0">
        <span class="text-xs font-bold text-purple-600">{{ $event->start_date->isToday()?'TODAY':$event->start_date->format('M d') }} · {{ $event->start_date->format('h:i A') }}</span>
        <span class="block font-bold mt-1">{{ $event->title }}</span>
        <span class="block text-sm text-gray-500 mt-1 truncate"><i class="ph ph-map-pin mr-1"></i>{{ $event->venue?:'Venue to be announced' }}</span>
       </span>
      </a>
      @empty
      <p class="text-gray-500 py-3">No upcoming events scheduled.</p>
      @endforelse
     </div>
    </section>
   </div>
   <aside class="space-y-5">
    <section class="student-panel p-5 sm:p-6">
     <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
       @if($user->photo)
       <img src="{{ storage_url($user->photo) }}" alt="{{ $user->first_last_name }}" class="w-11 h-11 rounded-xl object-cover">
       @else
       <div class="w-11 h-11 rounded-xl flex items-center justify-center font-bold student-chip-primary">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</div>
       @endif
       <div>
        <p class="student-kicker">Make it yours</p>
        <h2 class="text-xl font-black mt-1">My student profile</h2>
       </div>
      </div>
      <a href="{{ route('profile.edit') }}" data-student-navigation class="text-xs font-bold text-purple-600">Update</a>
     </div>
     <div class="space-y-4">
      <div class="flex justify-between gap-4"><span class="text-sm text-gray-500">Student ID</span><b class="text-sm text-right">{{ $user->student_id?:'Not added' }}</b></div>
      <div class="flex justify-between gap-4"><span class="text-sm text-gray-500">Program</span><b class="text-sm text-right">{{ $user->course?:'Not added' }}</b></div>
      <div class="flex justify-between gap-4"><span class="text-sm text-gray-500">Year & section</span><b class="text-sm text-right">{{ $user->year_level?:'—' }}{{ $user->section?' · '.$user->section:'' }}</b></div>
     </div>
     <a href="{{ route('profile.show') }}" data-student-navigation class="student-btn-soft mt-5 flex justify-between px-3.5 py-3 text-sm font-bold">Keep your profile updated <i class="ph ph-arrow-right"></i></a>
    </section>
    <section class="student-panel p-5 sm:p-6">
     <div class="flex justify-between items-center mb-4">
      <div>
       <p class="student-kicker">Useful to know</p>
       <h2 class="text-xl font-black mt-1">My payment updates</h2>
      </div>
      <span class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-50 text-green-700"><i class="ph ph-receipt"></i></span>
     </div>
     <div class="student-btn-soft p-3.5 mb-3">
      <p class="text-sm font-bold">Need to pay something?</p>
      <p class="text-xs text-gray-500 mt-1">Check the latest fees and deadlines below.</p>
     </div>
     @forelse($paymentAnnouncements->take(3) as $pa)
     @php $ps=$studentPaymentStatuses->get($pa->id);@endphp
     <a href="{{ route('payments.index') }}" data-student-navigation class="student-row flex items-center gap-3 py-3 px-2 -mx-2 border-t border-gray-100">
      <span class="w-9 h-9 rounded-lg student-chip-neutral flex items-center justify-center"><i class="ph ph-wallet text-xs"></i></span>
      <span class="min-w-0 flex-1">
       <span class="block text-sm font-bold truncate">{{ $pa->title }}</span>
       <span class="block text-xs text-gray-500">₱{{ number_format($pa->amount,2) }}{{ $pa->due_date?' · Due '.$pa->due_date->format('M d'):'' }}</span>
      </span>
      <b class="text-[10px] uppercase {{ $ps?->status==='completed'?'text-green-600':'text-orange-600' }}">{{ $ps?->status==='completed'?'Paid':'Review' }}</b>
     </a>
     @empty
     <p class="text-sm text-gray-500 py-4">No payment announcements right now.</p>
     @endforelse
     <a href="{{ route('payments.index') }}" data-student-navigation class="block text-center mt-3 pt-3 border-t border-gray-100 text-sm font-bold text-purple-600">Open payments <i class="ph ph-arrow-right ml-1"></i></a>
    </section>
   </aside>
  </div>
 </div>
  @if($user->isStudent())
  <div x-data="{ show: !localStorage.getItem('uniorg_tnc_acknowledged') && !sessionStorage.getItem('uniorg_tnc_session_acknowledged'), dontShowAgain: false, isRejecting: false, agree() { try { sessionStorage.setItem('uniorg_tnc_session_acknowledged', '1'); if (this.dontShowAgain) { localStorage.setItem('uniorg_tnc_acknowledged', '1') } } catch (e) {} this.show = false }, reject() { if (this.isRejecting) return; this.isRejecting = true; document.getElementById('tnc-logout-form')?.submit(); } }" x-cloak x-show="show" x-transition.opacity x-init="if (show) { $nextTick(() => { if ($refs.agreeBtn) $refs.agreeBtn.focus(); }) }" class="fixed inset-0 z-[70] overflow-y-auto" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="student-tnc-title">
  <div class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
   <div class="relative min-h-full flex items-start sm:items-center justify-center p-3 sm:p-6">
    <div class="relative w-full max-w-2xl my-4 overflow-hidden bg-white rounded-2xl shadow-2xl border border-gray-200">
     <div class="px-5 sm:px-7 pt-6 pb-4 border-b border-gray-100">
      <p class="text-xs font-bold tracking-widest uppercase mb-1" style="color:var(--primary-color)">{{ organization_name() }}</p>
      <h2 id="student-tnc-title" class="text-xl sm:text-2xl font-black text-gray-900">Terms &amp; Conditions</h2>
      <p class="text-sm text-gray-500 mt-1">Please read and accept the terms below to continue using your student account.</p>
     </div>
     <div class="px-5 sm:px-7 py-5 max-h-[55vh] overflow-y-auto space-y-5 text-sm leading-6 text-gray-600 bg-white">
     <section>
       <h3 class="font-bold text-gray-800 mb-1">1. Data Privacy &amp; Collection</h3>
       <p>We collect your personal information such as your name, student number, email, year level, and section to manage your organization membership. This may also include payment records (including GCash receipts), submitted documents, event registrations, and election votes.</p>
      </section>
     <section>
      <h3 class="font-bold text-gray-800 mb-1">2. Data Usage</h3>
      <p>Your information is used only for legitimate organization purposes: verifying payments, processing documents, managing event registrations, running elections, and delivering announcements. We never sell your data or share it with third parties for marketing or commercial purposes.</p>
     </section>
     <section>
      <h3 class="font-bold text-gray-800 mb-1">3. Data Access &amp; Sharing</h3>
      <p>Your data is accessible only to authorized organization officers and school administrators who need it to perform their duties, such as verifying your payments. Your election vote is anonymous and cannot be traced back to your identity.</p>
     </section>
     <section>
      <h3 class="font-bold text-gray-800 mb-1">4. Account Security</h3>
      <p>You are responsible for keeping your login credentials confidential. Never share your password with anyone. Report any suspected unauthorized access to your account immediately.</p>
     </section>
     <section>
      <h3 class="font-bold text-gray-800 mb-1">5. Acceptable Use</h3>
      <p>Always provide truthful and accurate information, especially in payment receipts and submitted documents. Submitting fake or falsified receipts or documents may result in disciplinary action.</p>
     </section>
      <section>
       <h3 class="font-bold text-gray-800 mb-1">6. Changes to Terms</h3>
       <p>These terms may be updated at any time. Continued use of the system after an update means you accept the revised terms.</p>
      </section>
      <section>
       <h3 class="font-bold text-gray-800 mb-1">7. Consent</h3>
       <p>By clicking &ldquo;I Agree&rdquo;, you acknowledge that you have read and understood these terms and agree to follow them while using your student account.</p>
      </section>
    </div>
      <div class="px-5 sm:px-7 py-4 border-t border-gray-100 flex flex-col gap-3 bg-white">
      <label class="flex items-center gap-2.5 text-sm font-bold text-gray-600 cursor-pointer select-none">
       <input type="checkbox" x-model="dontShowAgain" :disabled="isRejecting" class="w-4 h-4 accent-[var(--primary-color)] cursor-pointer disabled:opacity-50">
       Don&rsquo;t show again
      </label>
      <div class="flex items-center gap-2 sm:gap-3 justify-end">
       <button type="button" @click="reject()" :disabled="isRejecting" class="px-5 py-2.5 text-sm font-bold rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition">Decline</button>
       <button type="button" x-ref="agreeBtn" @click="agree()" :disabled="isRejecting" class="px-6 py-2.5 text-sm font-bold rounded-xl text-white disabled:opacity-50 disabled:cursor-not-allowed transition" style="background:var(--primary-color)">I Agree</button>
      </div>
     </div>
     <form id="tnc-logout-form" method="POST" action="{{ route('logout') }}" data-no-loading class="hidden" aria-hidden="true">@csrf</form>
   </div>
  </div>
 </div>
 @endif
</main>
@endsection
