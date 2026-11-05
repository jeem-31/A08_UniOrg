<?php

namespace App\Services;

use App\Models\AuthenticationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthenticationActivity
{
    public function record(string $event, Request $request, string $identifierField = 'student_id'): void
    {
        $identifier = $request->input($identifierField);
        $identifier = is_string($identifier) ? $identifier : '';
        $identifier = preg_replace('/[\x00-\x1F\x7F]/u', '', $identifier) ?? '';

        try {
            AuthenticationEvent::create([
                'event' => $event,
                'identifier' => mb_substr(trim($identifier), 0, 255) ?: null,
                'ip_address' => $request->ip(),
                'path' => mb_substr('/'.$request->path(), 0, 120),
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            Log::warning('Unable to store authentication activity.', ['event' => $event]);
        }
    }
}
