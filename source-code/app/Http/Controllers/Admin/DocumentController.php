<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function myDocuments()
    {
        $documents = auth()->user()->documents()->latest()->get();
        return view('student.documents.my-documents', compact('documents'));
    }

    public function uploadDocument(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:cor,medical_certificate,parent_consent',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'issue_date' => 'required_unless:document_type,cor|nullable|date|before_or_equal:today',
            'year_level' => 'required_if:document_type,cor|nullable|in:1st Year,2nd Year,3rd Year,4th Year',
            'semester' => 'required_if:document_type,cor|nullable|in:1st Semester,2nd Semester',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['file_path'] = $request->file('file')->store('student_documents', storage_disk());

        // Auto-calculate expiry date based on document type
        $issueDate = \Carbon\Carbon::parse($validated['issue_date']);
        if ($validated['document_type'] === 'parent_consent') {
            $validated['expiry_date'] = $issueDate->copy()->addYear();
        } elseif ($validated['document_type'] === 'medical_certificate') {
            $validated['expiry_date'] = $issueDate->copy()->addMonths(6);
        } else {
            $validated['expiry_date'] = null; // COR doesn't expire
        }

        StudentDocument::create($validated);

        return back()->with([
            'success' => 'Document uploaded successfully.',
            'uploaded_document_type' => $validated['document_type']
        ]);
    }

    public function deleteDocument(StudentDocument $document)
    {
        $this->authorize('delete', $document);

        Storage::disk(storage_disk())->delete($document->file_path);
        $document->delete();

        // Clear cache for this organization
        cache()->forget('documents.student.admin.' . $document->organization_id);

        return back()->with('success', 'Document deleted successfully.');
    }

    public function shareDocument(StudentDocument $document)
    {
        $this->authorize('delete', $document); // Only owner can share

        // Check if already shared
        $existingShare = \App\Models\SharedDocument::where('student_document_id', $document->id)
            ->where('student_id', auth()->id())
            ->first();

        if ($existingShare) {
            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Document is already shared with admin.']);
            }
            return back()->with('success', 'Document is already shared with admin.');
        }

        $sharedDocument = \App\Models\SharedDocument::create([
            'student_document_id' => $document->id,
            'student_id' => auth()->id(),
        ]);

        // Clear cache for this organization
        cache()->forget('documents.student.admin.' . $document->organization_id);

        // Broadcast real-time update
        broadcast(new \App\Events\DocumentShared($sharedDocument));

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Document shared with admin successfully.']);
        }
        return back()->with('success', 'Document shared with admin successfully.');
    }

    public function unshareDocument(StudentDocument $document)
    {
        $this->authorize('delete', $document); // Only owner can unshare

        $sharedDocument = \App\Models\SharedDocument::where('student_document_id', $document->id)
            ->where('student_id', auth()->id())
            ->first();

        if ($sharedDocument) {
            $sharedDocumentId = $sharedDocument->id;
            $studentId = auth()->id();
            $documentType = $document->document_type;
            
            $sharedDocument->delete();

            // Clear cache for this organization
            cache()->forget('documents.student.admin.' . $document->organization_id);

            // Broadcast real-time update
            broadcast(new \App\Events\DocumentUnshared($sharedDocumentId, $studentId, $documentType));
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Admin access revoked successfully.']);
        }
        return back()->with('success', 'Admin access revoked successfully.');
    }

    // Admin methods
    public function adminStudentDocuments()
    {
        $currentOrgId = session('current_organization_id', 1);
        $cacheKey = 'documents.student.admin.' . $currentOrgId;
        
        $studentsByYear = cache()->remember($cacheKey, 120, function () use ($currentOrgId) {
            // Get only students who have shared documents for this organization, with their documents
            return \App\Models\User::where('role', 'student')
                ->whereHas('sharedDocuments.document', function ($query) use ($currentOrgId) {
                    $query->where('organization_id', $currentOrgId);
                })
                ->with(['sharedDocuments.document' => function($query) use ($currentOrgId) {
                    $query->where('organization_id', $currentOrgId)
                        ->select('id', 'user_id', 'document_type', 'file_path', 'issue_date', 'year_level', 'semester');
                }])
                ->select('id', 'first_name', 'last_name', 'suffix', 'student_id', 'year_level', 'section', 'course')
                ->orderBy('year_level')
                ->orderBy('section')
                ->orderBy('last_name')
                ->get()
                ->groupBy('year_level');
        });
        
        // Transform students data for Alpine.js
        $studentsForAlpine = $studentsByYear->flatten()->values()->map(function($student) {
            $corDocs = $student->sharedDocuments->filter(fn($s) => $s->document->document_type === 'cor');
            $parentDoc = $student->sharedDocuments->first(fn($s) => $s->document->document_type === 'parent_consent');
            $medicalDoc = $student->sharedDocuments->first(fn($s) => $s->document->document_type === 'medical_certificate');
            
            return [
                'id' => $student->id,
                'first_name' => title_case_name((string) $student->first_name),
                'last_name' => title_case_name((string) $student->last_name),
                'suffix' => $student->suffix ? title_case_name((string) $student->suffix) : '',
                'student_id' => $student->student_id,
                'year_level' => $student->year_level,
                'section' => $student->section,
                'cors' => $corDocs->map(fn($s) => [
                    'id' => $s->id,
                    'path' => storage_url($s->document->file_path),
                    'extension' => strtolower(pathinfo($s->document->file_path, PATHINFO_EXTENSION)),
                    'year_level' => $s->document->year_level,
                    'semester' => $s->document->semester,
                ])->values()->toArray(),
                'parent' => $parentDoc ? [
                    'id' => $parentDoc->id,
                    'path' => storage_url($parentDoc->document->file_path),
                    'extension' => strtolower(pathinfo($parentDoc->document->file_path, PATHINFO_EXTENSION)),
                    'date' => $parentDoc->document->issue_date ? \Carbon\Carbon::parse($parentDoc->document->issue_date)->format('M d, Y') : null,
                ] : null,
                'medical' => $medicalDoc ? [
                    'id' => $medicalDoc->id,
                    'path' => storage_url($medicalDoc->document->file_path),
                    'extension' => strtolower(pathinfo($medicalDoc->document->file_path, PATHINFO_EXTENSION)),
                    'date' => $medicalDoc->document->issue_date ? \Carbon\Carbon::parse($medicalDoc->document->issue_date)->format('M d, Y') : null,
                ] : null,
            ];
        });
        
        return response()
            ->view('admin.documents.student-documents', compact('studentsByYear', 'studentsForAlpine'))
            ->header('Cache-Control', 'private, max-age=120');
    }
    
    public function viewSharedDocuments($userId)
    {
        $student = \App\Models\User::findOrFail($userId);
        $sharedDocuments = \App\Models\SharedDocument::where('student_id', $userId)
            ->with('document')
            ->latest()
            ->get();
        
        // Mark as read
        \App\Models\SharedDocument::where('student_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        return view('admin.documents.view-shared', compact('student', 'sharedDocuments'));
    }
    
    public function viewStudentDocuments($userId)
    {
        $student = \App\Models\User::findOrFail($userId);
        $documents = StudentDocument::where('user_id', $userId)->latest()->get();
        return view('admin.documents.view-student', compact('student', 'documents'));
    }

    public function downloadStudentDocuments($userId)
    {
        $student = \App\Models\User::findOrFail($userId);
        $sharedDocuments = \App\Models\SharedDocument::where('student_id', $userId)
            ->with('document')
            ->get();
        
        if ($sharedDocuments->isEmpty()) {
            return back()->with('error', 'No documents to download.');
        }
        
        // Create a ZIP file
        $zip = new \ZipArchive();
        $fileName = $student->first_name . '_' . $student->last_name . '_Documents_' . date('Y-m-d') . '.zip';
        $zipPath = storage_path('app/temp/' . $fileName);
        
        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $disk = Storage::disk(storage_disk());
            $addedFiles = 0;
            foreach ($sharedDocuments as $shared) {
                $document = $shared->document;

                if ($disk->exists($document->file_path)) {
                    $docType = ucwords(str_replace('_', ' ', $document->document_type));
                    $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
                    $tempFile = tempnam(sys_get_temp_dir(), 'doc_');
                    if ($tempFile !== false) {
                        file_put_contents($tempFile, $disk->get($document->file_path));
                        $zip->addFile($tempFile, $docType . '.' . $extension);
                        $addedFiles++;
                    }
                }
            }
            $zip->close();
        }

        if (empty($sharedDocuments) || !isset($addedFiles) || $addedFiles === 0) {
            return back()->with('error', 'No documents available to download.');
        }
        
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
    
    public function downloadAllDocuments(Request $request)
    {
        $year = $request->query('year');
        $section = $request->query('section', 'all');
        
        // Build query for students
        $query = \App\Models\User::where('role', 'student')
            ->whereHas('sharedDocuments')
            ->where('year_level', $year);
        
        if ($section !== 'all') {
            $query->where('section', $section);
        }
        
        $students = $query->with(['sharedDocuments.document'])->get();
        
        if ($students->isEmpty()) {
            return back()->with('error', 'No documents to download.');
        }
        
        // Create ZIP file name based on year and section
        $yearNum = substr($year, 0, 1); // Get first character (1, 2, 3, 4)
        if ($section !== 'all') {
            // Get program from first student
            $program = $students->first()->course ?? 'BSIT';
            $fileName = $program . '_' . $yearNum . $section . '_Documents_' . date('Y-m-d') . '.zip';
        } else {
            $fileName = $year . '_All_Sections_Documents_' . date('Y-m-d') . '.zip';
        }
        
        $zipPath = storage_path('app/temp/' . $fileName);
        
        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $disk = Storage::disk(storage_disk());
            $addedFiles = 0;
            foreach ($students as $student) {
                $studentFolder = $student->first_name . '_' . $student->last_name . '_' . $student->student_id;
                
                foreach ($student->sharedDocuments as $shared) {
                    $document = $shared->document;

                    if ($disk->exists($document->file_path)) {
                        $docType = ucwords(str_replace('_', ' ', $document->document_type));
                        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
                        $tempFile = tempnam(sys_get_temp_dir(), 'doc_');
                        if ($tempFile !== false) {
                            file_put_contents($tempFile, $disk->get($document->file_path));
                            $zip->addFile($tempFile, $studentFolder . '/' . $docType . '.' . $extension);
                            $addedFiles++;
                        }
                    }
                }
            }
            $zip->close();
        }

        if (!isset($addedFiles) || $addedFiles === 0) {
            return back()->with('error', 'No documents available to download.');
        }
        
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
    
    public function verifyDocument(StudentDocument $document)
    {
        $document->update([
            'is_verified' => \DB::raw('TRUE'),
            'verified_by' => auth()->id(),
        ]);

        // Clear cache for this organization
        cache()->forget('documents.student.admin.' . $document->organization_id);

        return back()->with('success', 'Document verified successfully.');
    }
}
