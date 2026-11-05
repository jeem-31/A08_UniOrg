<div class="mt-5 bg-white rounded-lg border border-gray-100 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-xs font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                <i class="ph ph-shield-warning text-[#26488C]"></i>
                Login Security
            </h2>
            <p class="text-xs text-gray-500 mt-1">Latest 50 matching failed logins and rate-limit hits across UniOrg</p>
        </div>
        <a href="{{ route('osas.settings', array_merge(['tab' => 'audit-logs'], array_filter($authenticationFilters))) }}" class="text-xs font-bold text-[#26488C] hover:underline">Refresh</a>
    </div>

    <form method="GET" action="{{ route('osas.settings') }}" class="px-5 py-4 border-b border-gray-100">
        <input type="hidden" name="tab" value="audit-logs">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 items-start">
            <div>
                <label for="login-security-event" class="block text-xs font-semibold text-gray-700 mb-1">Activity</label>
                <select id="login-security-event" name="security_event" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All activities</option>
                    @foreach(\App\Models\AuthenticationEvent::EVENT_LABELS as $eventValue => $eventLabel)
                        <option value="{{ $eventValue }}" @selected($authenticationFilters['security_event'] === $eventValue)>{{ $eventLabel }}</option>
                    @endforeach
                </select>
                @if($authenticationFilterErrors->has('security_event'))
                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $authenticationFilterErrors->first('security_event') }}</p>
                @endif
            </div>
            <div>
                <label for="login-security-from" class="block text-xs font-semibold text-gray-700 mb-1">From date and time</label>
                <input id="login-security-from" name="security_from" type="datetime-local" value="{{ $authenticationFilters['security_from'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @if($authenticationFilterErrors->has('security_from'))
                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $authenticationFilterErrors->first('security_from') }}</p>
                @endif
            </div>
            <div>
                <label for="login-security-to" class="block text-xs font-semibold text-gray-700 mb-1">To date and time</label>
                <input id="login-security-to" name="security_to" type="datetime-local" value="{{ $authenticationFilters['security_to'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @if($authenticationFilterErrors->has('security_to'))
                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $authenticationFilterErrors->first('security_to') }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3 pt-5">
                <button type="submit" class="rounded-lg bg-[#26488C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1d376c]">Apply filters</button>
                <a href="{{ route('osas.settings', ['tab' => 'audit-logs']) }}" class="text-sm font-semibold text-gray-600 hover:underline">Reset</a>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">Times use {{ config('app.timezone') }}. Leave a date field blank for an open-ended range.</p>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th scope="col" class="px-5 py-3">Activity</th>
                    <th scope="col" class="px-5 py-3">Attempted ID / Email</th>
                    <th scope="col" class="px-5 py-3">IP Address</th>
                    <th scope="col" class="px-5 py-3">Date and Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($authenticationEvents as $authenticationEvent)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3 font-semibold text-gray-900">{{ $authenticationEvent->label() }}</td>
                        <td class="px-5 py-3 text-gray-600 break-all">{{ $authenticationEvent->identifier ?? 'Not provided' }}</td>
                        <td class="px-5 py-3 text-gray-600 whitespace-nowrap">{{ $authenticationEvent->ip_address ?? 'Unavailable' }}</td>
                        <td class="px-5 py-3 text-gray-600 whitespace-nowrap">{{ $authenticationEvent->occurred_at->timezone(config('app.timezone'))->format('M d, Y h:i:s A') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-gray-500">
                            @if($authenticationFilterErrors->isNotEmpty())
                                Update the filters above to view login security activity.
                            @elseif(array_filter($authenticationFilters))
                                No login security activity matches these filters.
                            @else
                                No failed logins or rate-limit hits recorded yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
