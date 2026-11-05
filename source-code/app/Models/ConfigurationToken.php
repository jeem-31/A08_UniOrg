<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigurationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'token',
        'expires_at',
        'is_used',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
        ];
    }

    /**
     * Generate a new secure configuration token
     *
     * @param int $organizationId
     * @param int $userId
     * @return self
     */
    public static function generate(int $organizationId, int $userId): self
    {
        // Invalidate any existing unused tokens for this organization
        \Illuminate\Support\Facades\DB::table('configuration_tokens')
            ->where('organization_id', $organizationId)
            ->whereRaw('is_used IS FALSE')
            ->update([
                'is_used' => \Illuminate\Support\Facades\DB::raw('TRUE'),
                'updated_at' => now()
            ]);

        // Create new token with secure random string using raw DB insert
        $token = bin2hex(random_bytes(32));
        $id = \Illuminate\Support\Facades\DB::table('configuration_tokens')->insertGetId([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'is_used' => \Illuminate\Support\Facades\DB::raw('FALSE'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return the created model instance
        return self::find($id);
    }

    /**
     * Validate if the token is still valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->is_used
            && $this->expires_at->isFuture()
            && $this->organization->has_system === false;
    }

    /**
     * Mark the token as used
     *
     * @return void
     */
    public function markAsUsed(): void
    {
        $this->update(['is_used' => \Illuminate\Support\Facades\DB::raw('TRUE'), 'updated_at' => now()]);
    }

    /**
     * Get the organization that owns the token
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who initiated the configuration
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
