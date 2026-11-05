<?php

namespace App\Models;

use App\Casts\PostgresBoolean;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationRolePermission extends Model
{
    protected $table = 'organization_role_permissions';

    protected $fillable = [
        'organization_id',
        'role',
        'module',
        'is_granted',
    ];

    protected $casts = [
        'is_granted' => PostgresBoolean::class,
    ];

    /**
     * The organization this permission grant belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
