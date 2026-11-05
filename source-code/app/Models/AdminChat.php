<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminChat extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'channel',
        'organization_id',
        'user_id',
        'admin_id',
        'message',
        'sender_type',
        'is_read',
        'attachment_path',
        'attachment_name',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Alias of user() for OSAS chats where user_id is always the sender.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Scope to get messages for a specific user
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Scope to get unread messages for admin
    public function scopeUnread($query)
    {
        return $query->where('sender_type', 'student')->whereRaw('CAST(is_read AS INTEGER) = ?', [0]);
    }

    // Scope to get messages from students
    public function scopeFromStudents($query)
    {
        return $query->where('sender_type', 'student');
    }

    // Scope to get messages from admin
    public function scopeFromAdmin($query)
    {
        return $query->where('sender_type', 'admin');
    }

    /**
     * OSAS <-> organization conversation thread (shared inbox per org).
     */
    public function scopeOsasThread($query, int $organizationId)
    {
        return $query->where('channel', 'osas')
            ->where('organization_id', $organizationId);
    }

    /**
     * Messages sent by the OSAS office.
     */
    public function scopeFromOsas($query)
    {
        return $query->where('channel', 'osas')->where('sender_type', 'osas');
    }

    /**
     * Messages sent by an organization (any admin of the org).
     */
    public function scopeFromOrg($query)
    {
        return $query->where('channel', 'osas')->where('sender_type', 'org');
    }

    /**
     * Unread OSAS messages from organizations (shown on the OSAS side).
     */
    public function scopeUnreadForOsas($query)
    {
        return $query->where('channel', 'osas')
            ->where('sender_type', 'org')
            ->whereRaw('CAST(is_read AS INTEGER) = ?', [0]);
    }

    /**
     * Unread OSAS messages from the office (shown on the organization side).
     */
    public function scopeUnreadFromOsas($query, int $organizationId)
    {
        return $query->where('channel', 'osas')
            ->where('organization_id', $organizationId)
            ->where('sender_type', 'osas')
            ->whereRaw('CAST(is_read AS INTEGER) = ?', [0]);
    }
}
