<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUnshared implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sharedDocumentId;
    public $studentId;
    public $documentType;

    public function __construct($sharedDocumentId, $studentId, $documentType = null)
    {
        $this->sharedDocumentId = $sharedDocumentId;
        $this->studentId = $studentId;
        $this->documentType = $documentType;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('documents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'document-unshared';
    }

    public function broadcastWith(): array
    {
        // Get student with all remaining shared documents
        $student = \App\Models\User::find($this->studentId);
        
        if (!$student) {
            return [
                'document_id' => null,
                'shared_document_id' => $this->sharedDocumentId,
                'student_id' => $this->studentId,
                'document_type' => $this->documentType,
                'remove_student' => true,
            ];
        }
        
        // Get all remaining shared documents for this student
        $allSharedDocs = \App\Models\SharedDocument::where('student_id', $this->studentId)
            ->with('document')
            ->get();
        
        // If no more shared documents, signal to remove student from list
        if ($allSharedDocs->isEmpty()) {
            return [
                'document_id' => null,
                'shared_document_id' => $this->sharedDocumentId,
                'student_id' => $this->studentId,
                'document_type' => $this->documentType,
                'remove_student' => true,
            ];
        }
        
        // Otherwise, return updated student data
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
            'document_id' => null, // Document was unshared, no specific document ID
            'shared_document_id' => $this->sharedDocumentId,
            'student_id' => $this->studentId,
            'document_type' => $this->documentType,
            'remove_student' => false,
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
        ];
    }
}
