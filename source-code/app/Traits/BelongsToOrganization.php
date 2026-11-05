<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    /**
     * Boot the trait and automatically assign organization_id on creation
     * Also add global scope to filter by current organization
     */
    protected static function bootBelongsToOrganization()
    {
        // Auto-assign organization_id on creating
        static::creating(function (Model $model) {
            // Respect an explicitly supplied null organization_id for system-wide records.
            // Only infer an organization when the attribute was omitted entirely.
            if (!array_key_exists('organization_id', $model->getAttributes())) {
                $currentOrgId = session('current_organization_id');
                
                if ($currentOrgId) {
                    $model->organization_id = $currentOrgId;
                } elseif (auth()->check()) {
                    // Fallback to user's first organization
                    $user = auth()->user();
                    
                    if (isset($user->organization_id)) {
                        $model->organization_id = $user->organization_id;
                    } elseif ($user->organizations && $user->organizations->isNotEmpty()) {
                        $model->organization_id = $user->organizations->first()->id;
                    } else {
                        // Fallback to organization_id = 1 (SOCCS) for backward compatibility
                        $model->organization_id = 1;
                    }
                }
            }
        });

        // Add global scope to automatically filter by current organization
        static::addGlobalScope('organization', function (Builder $builder) {
            $currentOrgId = session('current_organization_id');
            
            if ($currentOrgId) {
                $builder->where($builder->getModel()->getTable() . '.organization_id', $currentOrgId);
            }
        });
    }

    /**
     * Relationship to Organization
     */
    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class);
    }

    /**
     * Scope to bypass the global organization scope and get all organizations
     * 
     * Usage: Model::allOrganizations()->get()
     */
    public function scopeAllOrganizations($query)
    {
        return $query->withoutGlobalScope('organization');
    }

    /**
     * Scope to filter by specific organization
     * 
     * Usage: Model::forOrganization($orgId)->get()
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->withoutGlobalScope('organization')
            ->where('organization_id', $organizationId);
    }

    /**
     * Scope to filter by current user's organization
     * 
     * Usage: Model::forCurrentOrganization()->get()
     */
    public function scopeForCurrentOrganization($query)
    {
        $currentOrgId = session('current_organization_id');
        
        if ($currentOrgId) {
            return $query->withoutGlobalScope('organization')
                ->where('organization_id', $currentOrgId);
        }
        
        if (auth()->check()) {
            $user = auth()->user();
            
            if (isset($user->organization_id)) {
                return $query->withoutGlobalScope('organization')
                    ->where('organization_id', $user->organization_id);
            } elseif ($user->organizations && $user->organizations->isNotEmpty()) {
                return $query->withoutGlobalScope('organization')
                    ->where('organization_id', $user->organizations->first()->id);
            }
        }
        
        return $query;
    }
}
