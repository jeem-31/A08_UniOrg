<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdushareShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'folder_id',
        'share_type',
        'shared_with_email',
        'shared_by',
    ];

    public function item()
    {
        return $this->belongsTo(EdushareItem::class, 'item_id');
    }

    public function folder()
    {
        return $this->belongsTo(EdushareFolder::class, 'folder_id');
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
