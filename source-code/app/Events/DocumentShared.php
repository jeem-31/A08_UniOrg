<?php

namespace App\Events;

use App\Models\SharedDocument;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentShared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sharedDocument;

    public function __construct(SharedDocument $sharedDocument)
    {
        $this->sharedDocument = $sharedDocument;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('documents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document-shared';
    }

    public function broadcastWith(): array
    {
        // Load relationships
        $this->sharedDocument->load(['document', 'student']);
        
        $student = $this->sharedDocument->student;
        
        // Get all shared documents for this student with full details
        $allSharedDocs = \App\Models\SharedDocument::where('student_id', $student->id)
            ->with('document')
            ->get();
        
        // Group by document type
        $cors = $allSharedDocs->filter(fn($s) => $s->document->document_type === 'cor')
            ->map(fn($s) => [
                'id' => $s->id,
                'path' => storage_url($s->document->file_path),
                'year_level' => $s->document->year_level,
                'semester' => $s->document->semester,
            ])->values()->toArray();
        
        $parentDoc = $allSharedDocs->first(fn($s) => $s->document->document_type === 'parent_consent');
        $medicalDoc = $allSharedDocs->first(fn($s) => $s->document->document_type === 'medical_certificate');
        
        return [
            'document_id' => $this->sharedDocument->document->id,
            'student' => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => name_with_suffix($student->first_name, null, $student->last_name, $student->suffix),
                'student_id' => $student->student_id,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'cors' => $cors,
                'parent' => $parentDoc ? [
                    'id' => $parentDoc->id,
                    'path' => storage_url($parentDoc->document->file_path),
                    'date' => $parentDoc->document->issue_date ? \Carbon\Carbon::parse($parentDoc->document->issue_date)->format('M d, Y') : null,
                ] : null,
                'medical' => $medicalDoc ? [
                    'id' => $medicalDoc->id,
                    'path' => storage_url($medicalDoc->document->file_path),
                    'date' => $medicalDoc->document->issue_date ? \Carbon\Carbon::parse($medicalDoc->document->issue_date)->format('M d, Y') : null,
                ] : null,
            ],
            'document_type' => $this->sharedDocument->document->document_type,
        ];
    }
}
