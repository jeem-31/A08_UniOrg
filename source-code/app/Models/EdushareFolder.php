<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdushareFolder extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'parent_id',
        'name',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(EdushareFolder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(EdushareFolder::class, 'parent_id');
    }

    public function items()
    {
        return $this->hasMany(EdushareItem::class, 'folder_id');
    }

    public function shares()
    {
        return $this->hasMany(EdushareShare::class, 'folder_id');
    }
}
