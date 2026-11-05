@if (config('services.turnstile.enabled'))
    <div class="mb-4" data-turnstile data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="{{ $action }}">
        <div data-turnstile-widget></div>
        <p data-turnstile-status class="mt-1 text-xs text-gray-600" role="status" aria-live="polite">Loading security verification…</p>
        <noscript><p class="text-xs text-red-600">Please enable JavaScript to complete security verification.</p></noscript>
    </div>
@endif
