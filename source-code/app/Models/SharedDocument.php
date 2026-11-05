<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedDocument extends Model
{
    protected $fillable = [
        'student_document_id',
        'student_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function document()
    {
        return $this->belongsTo(StudentDocument::class, 'student_document_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
