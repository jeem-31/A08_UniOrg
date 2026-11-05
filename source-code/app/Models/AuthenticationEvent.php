<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthenticationEvent extends Model
{
    public const EVENT_LABELS = [
        'login.failed' => 'Failed login',
        'login.throttled' => 'Login rate limit reached',
        'password-email.throttled' => 'Reset code request limit reached',
        'password-reset.throttled' => 'Password reset attempt limit reached',
    ];

    public $timestamps = false;

    protected $fillable = ['event', 'identifier', 'ip_address', 'path', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function label(): string
    {
        return self::EVENT_LABELS[$this->event] ?? 'Authentication event';
    }
}
