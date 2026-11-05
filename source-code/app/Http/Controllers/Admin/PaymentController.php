<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\MembershipFeeEligibilityUpdated;
use App\Events\StudentPaymentUpdated;
use App\Models\Payment;
use App\Models\Organization;
use App\Models\StudentPayment;
use App\Models\User;
use App\Services\MembershipFeeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class PaymentController extends Controller
{
    public function index(MembershipFeeService $membershipFees)
    {
        $user = auth()->user();
        $currentOrgId = session('current_organization_id');
        $currentOrg = $currentOrgId ? Organization::find($currentOrgId) : null;

        if ($currentOrg?->isMotherOrg() && $currentOrg->canBeUsed()) {
            $membershipFees->ensureForOrganization($currentOrg);
        }
        
        // Get user's role in current organization
        $userRole = null;
        if ($currentOrgId) {
            $membership = $user->organizations()->where('organizations.id', $currentOrgId)->first();
            $userRole = $membership ? $membership->pivot->role : null;
        }
        
        // Content Aggregation Logic for Mother Org Students
        if ($userRole === 'Student' && is_mother_org()) {
            // Get current organization
            $currentOrg = Organization::find($currentOrgId);
            
            if ($currentOrg && $currentOrg->department_id) {
                // Get all Sub-Orgs with the same department_id
                $subOrgs = Organization::where('department_id', $currentOrg->department_id)
                    ->where('type', 'Sub-Org')
                    ->pluck('id')
                    ->toArray();
                
                // Combine Mother Org + Sub-Orgs
                $organizationIds = array_merge([$currentOrgId], $subOrgs);
                
                // Query payment announcements from Mother Org and all related Sub-Orgs
                $announcements = Payment::allOrganizations()->with(['user', 'organization'])
                    ->whereIn('organization_id', $organizationIds)
                    ->orderByDesc('is_required_membership_fee')
                    ->orderBy('created_at', 'desc')
                    ->get();
            } else {
                // Fallback to current org only
                $announcements = Payment::with('user')->orderByDesc('is_required_membership_fee')->latest()->get();
            }
        } else {
            // For non-students or Sub-Org users, show only current org payments
            $announcements = Payment::with('user')->orderByDesc('is_required_membership_fee')->latest()->get();
        }
        
        // Get only the latest payment for each payment_id (announcement)
        // This prevents showing rejected/old attempts in history
        $payments = auth()->user()->studentPayments()
            ->select('student_payments.*')
            ->whereIn('id', function($query) {
                $query->selectRaw('MAX(id)')
                    ->from('student_payments')
                    ->where('user_id', auth()->id())
                    ->groupBy('payment_id');
            })
            ->latest()
            ->paginate(10);
            
        return view('student.payments.index', compact('payments', 'announcements'));
    }

    public function create()
    {
        $announcements = Payment::with('user')->get();
        return view('student.payments.create', compact('announcements'));
    }

    public function store(Request $request)
    {
        // This method is deprecated - students should use payment announcements
        return redirect()->route('payments.index')
            ->with('error', 'Please use the payment announcements to make payments.');
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);
        return view('student.payments.show', compact('payment'));
    }

    // AJAX: Render receipt HTML for modal view (student)
    public function studentReceiptHtml($paymentId)
    {
        $payment = \App\Models\StudentPayment::with('user', 'payment')->where('id', $paymentId)
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$payment) {
            return response('<div class="p-6 text-red-600">Receipt not found or access denied.</div>', 404);
        }

        if ($payment->status !== 'completed') {
            return response('<div class="p-6 text-center text-gray-500">
                <i class="ph ph-receipt text-gray-300 text-4xl mb-3 block"></i>
                <p class="font-medium">No receipt available yet.</p>
                <p class="text-xs mt-1">A receipt will be available once this payment is verified as paid.</p>
            </div>', 404);
        }
        
        return view('student.payments.receipt-body', ['payment' => $payment])->render();
    }

    public function adminIndex(Request $request, MembershipFeeService $membershipFees)
    {
        $currentOrgId = session('current_organization_id');
        $currentOrg = $currentOrgId ? Organization::find($currentOrgId) : null;

        if ($currentOrg?->isMotherOrg() && $currentOrg->canBeUsed()) {
            $membershipFees->ensureForOrganization($currentOrg);
        }

        $announcements = Payment::where('organization_id', $currentOrgId)
            ->with('user:id,first_name,last_name,email,suffix')
            ->orderByDesc('is_required_membership_fee')
            ->latest()
            ->get();

        $allPayments = StudentPayment::where('organization_id', $currentOrgId)
            ->with(['user:id,first_name,last_name,email,student_id,suffix', 'payment:id,title,category'])
            ->latest()
            ->get();

        $stats = [
            'completed' => StudentPayment::where('organization_id', $currentOrgId)->where('status', 'completed')->count(),
            'total_revenue' => StudentPayment::where('organization_id', $currentOrgId)->where('status', 'completed')->sum('amount'),
            'posted' => $announcements->count(),
        ];

        return view('admin.payments.index', compact('allPayments', 'announcements', 'stats'));
    }

    public function verify(StudentPayment $payment)
    {
        $payment->update([
            'status' => 'completed',
            'verification_status' => 'approved',
            'verified_by' => auth()->id(),
            'paid_at' => now(),
        ]);

        // Clear cache
        cache()->flush();
        $this->broadcastMembershipEligibility($payment, 'verified');

        // Send notification to student
        if ($payment->user) {
            // Send both email and database notification in background
            register_shutdown_function(function() use ($payment) {
                try {
                    $payment->user->notify(new \App\Notifications\PaymentStatusNotification(
                        $payment,
                        'completed'
                    ));
                } catch (\Exception $e) {
                    \Log::error('Failed to send payment notification: ' . $e->getMessage());
                    
                    // Create database notification as fallback if email fails
                    $payment->user->notifications()->create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'type' => 'App\Notifications\PaymentStatusNotification',
                        'data' => [
                            'payment_id' => $payment->id,
                            'organization_id' => $payment->organization_id,
                            'transaction_id' => $payment->transaction_id ?? 'N/A',
                            'amount' => $payment->amount,
                            'category' => $payment->category ?? 'payment',
                            'status' => 'completed',
                            'title' => 'Payment Completed',
                            'message' => 'Your payment of ₱' . number_format((float) ($payment->amount ?? 0), 2) . ' has been completed successfully!',
                            'icon' => 'ph-check-circle',
                            'color' => 'green',
                            'url' => route('payments.index'),
                        ],
                        'read_at' => null,
                    ]);
                }
            });
        }

        return back()->with('success', 'Payment verified successfully.');
    }

    public function reject(StudentPayment $payment)
    {
        $payment->update([
            'status' => 'failed',
            'verification_status' => 'rejected',
            'verified_by' => auth()->id(),
        ]);

        // Clear cache
        cache()->flush();
        $this->broadcastMembershipEligibility($payment, 'rejected');

        return back()->with('success', 'Payment rejected.');
    }

    public function exportExcel(Request $request)
    {
        $currentOrgId = session('current_organization_id');
        // Get only the latest payment for each payment_id (announcement) per user
        // This prevents exporting rejected/old attempts
        $payments = StudentPayment::where('organization_id', $currentOrgId)
            ->with('user')
            ->select('student_payments.*')
            ->whereIn('id', function($query) use ($currentOrgId) {
                $query->selectRaw('MAX(id)')
                    ->from('student_payments')
                    ->where('organization_id', $currentOrgId)
                    ->groupBy('user_id', 'payment_id');
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->payment_method, fn($q) => $q->where('payment_method', $request->payment_method))
            ->when($request->from_date, fn($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->latest()
            ->get();

        // Check if there are any payments to export
        if ($payments->isEmpty()) {
            return redirect()->back()->with('error', 'No payments found in the selected date range');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        // Headers
        $sheet->setCellValue('A1', 'Transaction ID');
        $sheet->setCellValue('B1', 'Student Name');
        $sheet->setCellValue('C1', 'Student ID');
        $sheet->setCellValue('D1', 'Category');
        $sheet->setCellValue('E1', 'Amount');
        $sheet->setCellValue('F1', 'Payment Method');
        $sheet->setCellValue('G1', 'Status');
        $sheet->setCellValue('H1', 'Payment Date');
        $sheet->setCellValue('I1', 'Created Date');

        // Apply header style
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Data
        $row = 2;
        foreach ($payments as $payment) {
            $sheet->setCellValue('A' . $row, $payment->transaction_id);
            $sheet->setCellValue('B' . $row, $payment->user->first_name . ' ' . $payment->user->last_name);
            $sheet->setCellValue('C' . $row, $payment->user->student_id);
            $sheet->setCellValue('D' . $row, ucfirst(str_replace('_', ' ', $payment->category)));
            $sheet->setCellValue('E' . $row, '₱' . number_format((float) ($payment->amount ?? 0), 2));
            $sheet->setCellValue('F' . $row, strtoupper($payment->payment_method));
            $sheet->setCellValue('G' . $row, ucfirst($payment->status));
            $sheet->setCellValue('H' . $row, $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : 'N/A');
            $sheet->setCellValue('I' . $row, $payment->created_at->format('Y-m-d H:i:s'));
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'payments_report_' . date('Y-m-d_His') . '.xlsx';
        $filepath = storage_path('app/public/reports/' . $filename);

        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $writer->save($filepath);

        return response()->download($filepath)->deleteFileAfterSend();
    }

    public function deanApprovals()
    {
        // Pending payments and complaints are scoped to the Dean's college.
        $departmentId = (int) auth()->user()->department_id;
        $orgIds = \App\Models\Organization::where('department_id', $departmentId)->pluck('id');

        $pendingPayments = \App\Models\StudentPayment::with('user')
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);
        $pendingComplaints = \App\Models\Complaint::with('user')
            ->whereIn('organization_id', $orgIds)
            ->where('status', 'pending')
            ->latest()
            ->paginate(15);

        return view('dean.approvals', compact('pendingPayments', 'pendingComplaints'));
    }

    // Payment Announcements Management
    public function announcementsIndex()
    {
        $currentOrgId = session('current_organization_id');
        $announcements = Payment::where('organization_id', $currentOrgId)->with('user')->latest()->paginate(15);
        return view('admin.payments.announcements', compact('announcements'));
    }

    public function createAnnouncement()
    {
        return view('admin.payments.create-announcement');
    }

    public function storeAnnouncement(Request $request)
    {
        try {
            \Log::info('Payment creation started');
            
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'category' => 'required|in:tour_fee,event_fee,document_fee,medical_fee,other',
                'amount' => 'required|numeric|min:0',
                'due_date' => 'required|date',
                'gcash_number' => 'required|string',
                'qr_code' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            \Log::info('Validation passed');

            $validated['user_id'] = auth()->id();
            $validated['organization_id'] = session('current_organization_id');

            // Handle QR code upload with better timeout handling
            if ($request->hasFile('qr_code')) {
                \Log::info('QR code file detected, starting upload...');
                
                try {
                    $qrCode = $request->file('qr_code');
                    
                    // Get file info
                    $originalSize = $qrCode->getSize();
                    \Log::info('Original QR code size: ' . number_format($originalSize / 1024, 2) . ' KB');
                    
                    // Increase PHP execution time for this upload only
                    ini_set('max_execution_time', 120);
                    set_time_limit(120);
                    
                    // Upload to R2 with progress logging
                    $startTime = microtime(true);
                    
                    // Use putFileAs for more control
                    $filename = time() . '_' . $qrCode->getClientOriginalName();
                    $path = Storage::disk(storage_disk())->putFileAs('qr_codes', $qrCode, $filename);
                    
                    $uploadTime = microtime(true) - $startTime;
                    
                    \Log::info('QR code uploaded successfully to: ' . $path . ' in ' . number_format($uploadTime, 2) . ' seconds');
                    $validated['qr_code'] = $path;
                    
                } catch (\Exception $uploadError) {
                    \Log::error('QR code upload failed: ' . $uploadError->getMessage());
                    \Log::error('Upload error trace: ' . $uploadError->getTraceAsString());
                    
                    // Return error to user via JSON
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to upload QR code to cloud storage. Please try a smaller image or try again later.'
                        ], 500);
                    }
                    
                    return back()->withInput()->with('error', 'Failed to upload QR code. Please try again or use a smaller image.');
                }
            }

            \Log::info('Creating payment record...');
            $payment = Payment::create($validated);
            $payment->load('user');
            \Log::info('Payment created successfully with ID: ' . $payment->id);
            
            // Clear cache
            cache()->flush();
            
            // Broadcast real-time update
            // The creator updates its list from the AJAX response. Exclude that
            // browser from the Pusher event while all other open tabs still update.
            $this->broadcastPaymentEvent(new \App\Events\PaymentCreated($payment), true);
            
            // Return JSON for AJAX requests
            if ($request->expectsJson() || $request->ajax()) {
                // Format data for Alpine.js
                $paymentData = [
                    'id' => $payment->id,
                    'title' => $payment->title,
                    'category' => $payment->category,
                    'amount' => $payment->amount,
                    'due_date' => $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->toIso8601String() : null,
                    'gcash_number' => $payment->gcash_number,
                    'qr_code' => $payment->qr_code,
                    'qr_code_url' => $payment->qr_code ? storage_url($payment->qr_code) : null,
                    'created_at' => $payment->created_at ? \Carbon\Carbon::parse($payment->created_at)->toIso8601String() : null,
                    'user' => [
                        'id' => $payment->user->id,
                        'name' => $payment->user->name,
                    ],
                    'isNew' => true,
                ];
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment created successfully.',
                    'payment' => $paymentData
                ]);
            }
            
            return redirect()->route('admin.payments.index', ['tab' => 'announcements'])
                ->with('success', 'Payment created successfully.');
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed: ' . json_encode($e->errors()));
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }
            
            return back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            \Log::error('Payment creation failed: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withInput()->with('error', 'Failed to create payment: ' . $e->getMessage());
        }
    }

    public function editAnnouncement(Payment $announcement)
    {
        return view('admin.payments.edit-announcement', compact('announcement'));
    }

    public function updateAnnouncement(Request $request, Payment $announcement)
    {
        $isProtectedMembershipFee = $announcement->isProtectedMembershipFee();

        if ($isProtectedMembershipFee) {
            $this->assertProtectedMembershipFeeImmutability($request, $announcement);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'gcash_number' => 'nullable|string|max:30',
                'qr_code' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            ]);
        } else {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'category' => 'required|in:tour_fee,event_fee,document_fee,medical_fee,other',
                'amount' => 'required|numeric|min:0',
                'due_date' => 'required|date',
                'gcash_number' => 'nullable|string',
                'qr_code' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
                'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'existing_images' => 'nullable|array',
            ]);
        }

        try {
            // Handle QR code update
            if ($request->hasFile('qr_code')) {
                // Delete old QR code if exists
                if ($announcement->qr_code) {
                    try {
                        Storage::disk(storage_disk())->delete($announcement->qr_code);
                    } catch (\Exception $deleteError) {
                        \Log::warning('Failed to delete old QR code: ' . $deleteError->getMessage());
                    }
                }

                $qrCode = $request->file('qr_code');
                // Increase PHP execution time for this upload
                ini_set('max_execution_time', 120);
                set_time_limit(120);

                $filename = time() . '_' . $qrCode->getClientOriginalName();
                $path = Storage::disk(storage_disk())->putFileAs('qr_codes', $qrCode, $filename);
                $validated['qr_code'] = $path;
            }
            if (! $isProtectedMembershipFee) {
                // Handle images
                $existingImages = $request->input('existing_images', []);
                $newImages = [];

                // Upload new images
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $image) {
                        $path = $image->store('announcements', storage_disk());
                        $newImages[] = $path;
                    }
                }

                // Combine existing and new images (max 5)
                $allImages = array_merge($existingImages, $newImages);
                $validated['images'] = array_slice($allImages, 0, 5);

                // Delete removed images from storage
                if ($announcement->images) {
                    $removedImages = array_diff($announcement->images, $existingImages);
                    foreach ($removedImages as $removedImage) {
                        Storage::disk(storage_disk())->delete($removedImage);
                    }
                }
            }

            $announcement->update($validated);
            $announcement->refresh();
            $announcement->load('user');

            // The editor updates its list from the AJAX response. Other open
            // browser sessions still receive this Pusher event.
            $this->broadcastPaymentEvent(new \App\Events\PaymentUpdated($announcement), true);

            // Clear cache
            cache()->flush();

            // Return JSON for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment updated successfully.',
                    'payment' => $this->paymentAnnouncementData($announcement),
                ]);
            }

            return redirect()->route('admin.payments.announcements')
                ->with('success', 'Payment updated successfully.');
        } catch (\Exception $e) {
            // Return JSON error for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update payment: ' . $e->getMessage()
                ], 500);
            }

            return back()->withInput()->with('error', 'Failed to update payment: ' . $e->getMessage());
        }
    }

    public function deleteAnnouncement(Request $request, Payment $announcement)
    {
        if ($announcement->isProtectedMembershipFee()) {
            $message = 'Membership Fee is a required annual payment and cannot be deleted.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'code' => 'membership_fee_protected',
                    'message' => $message,
                ], 422);
            }

            return back()->with('error', $message);
        }

        $paymentId = $announcement->id;
        
        // Delete images from storage
        if ($announcement->images) {
            foreach ($announcement->images as $image) {
                Storage::disk(storage_disk())->delete($image);
            }
        }
        
        $announcement->delete();
        
        // The deleting browser removes the row from its Alpine state. Other
        // open browser sessions still receive this Pusher event.
        $this->broadcastPaymentEvent(new \App\Events\PaymentDeleted($paymentId), true);
        
        // Clear cache
        cache()->flush();
        
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully.',
                'payment_id' => $paymentId,
            ]);
        }

        return back()->with('success', 'Payment deleted successfully.');
    }

    private function paymentAnnouncementData(Payment $payment): array
    {
        $payment->loadMissing('user');

        return [
            'id' => $payment->id,
            'title' => $payment->title,
            'category' => $payment->category,
            'amount' => $payment->amount,
            'is_required_membership_fee' => $payment->is_required_membership_fee,
            'membership_cycle_start' => $payment->membership_cycle_start?->toDateString(),
            'due_date' => $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->toIso8601String() : null,
            'gcash_number' => $payment->gcash_number,
            'qr_code' => $payment->qr_code,
            'qr_code_url' => $payment->qr_code ? storage_url($payment->qr_code) : null,
            'created_at' => $payment->created_at ? \Carbon\Carbon::parse($payment->created_at)->toIso8601String() : null,
            'user' => $payment->user ? [
                'id' => $payment->user->id,
                'name' => $payment->user->name,
            ] : null,
        ];
    }

    // Manual Payment by Admin
    public function createManualPayment()
    {
        $currentOrgId = session('current_organization_id');
        $announcements = Payment::where('organization_id', $currentOrgId)->with('user')->get();
        return view('admin.payments.create-manual', compact('announcements'));
    }

    public function storeManualPayment(Request $request)
    {
        $validated = $request->validate([
            'announcement_id' => 'nullable|exists:payments,id',
            'student_id' => 'required',
            'amount' => 'required|numeric|min:0',
        ]);

        $announcement = $validated['announcement_id']
            ? Payment::find($validated['announcement_id'])
            : null;

        if (! $announcement instanceof Payment) {
            return response()->json([
                'success' => false,
                'message' => 'Select a payment announcement before recording a manual payment.',
            ], 422);
        }

        if ($announcement->isProtectedMembershipFee()) {
            $this->assertCurrentMembershipFee($announcement, false);
            $validated['amount'] = (float) $announcement->amount;
        }

        $user = User::where('student_id', $validated['student_id'])->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'This student doesn\'t have an account yet.'
            ]);
        }

        // Check if student already has a COMPLETED payment for this announcement
        if ($validated['announcement_id']) {
            $existingPayment = StudentPayment::where('user_id', $user->id)
                ->where('payment_id', $validated['announcement_id'])
                ->where('status', 'completed')
                ->first();
            
            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'This student has already completed payment for this announcement.'
                ]);
            }
        }

        // Generate random transaction ID
        $transactionId = $this->generateTransactionId($announcement->organization);

        $currentOrgId = session('current_organization_id');

        $studentPayment = StudentPayment::create([
            'user_id' => $user->id,
            'organization_id' => $announcement->organization_id ?? $currentOrgId,
            'payment_id' => $announcement->id,
            'transaction_id' => $transactionId,
            'category' => $announcement->category,
            'amount' => $validated['amount'],
            'payment_method' => 'manual',
            'status' => 'completed',
            'verification_status' => 'approved',
            'paid_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        // Generate PDF receipt
        $receiptPath = $this->generatePdfReceipt($studentPayment);
        $studentPayment->update(['receipt_path' => $receiptPath]);

        // Send notification to student
        if ($user) {
            // Send both email and database notification in background
            register_shutdown_function(function() use ($user, $studentPayment) {
                try {
                    $user->notify(new \App\Notifications\PaymentStatusNotification(
                        $studentPayment,
                        'completed'
                    ));
                } catch (\Exception $e) {
                    \Log::error('Failed to send payment notification: ' . $e->getMessage());
                    
                    // Create database notification as fallback if email fails
                    $user->notifications()->create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'type' => 'App\Notifications\PaymentStatusNotification',
                        'data' => [
                            'payment_id' => $studentPayment->id,
                            'organization_id' => $studentPayment->organization_id,
                            'transaction_id' => $studentPayment->transaction_id,
                            'amount' => $studentPayment->amount,
                            'category' => $studentPayment->category,
                            'status' => 'completed',
                            'title' => 'Payment Completed',
                            'message' => 'Your payment of ₱' . number_format((float) ($studentPayment->amount ?? 0), 2) . ' has been completed successfully!',
                            'icon' => 'ph-check-circle',
                            'color' => 'green',
                            'url' => route('payments.index'),
                        ],
                        'read_at' => null,
                    ]);
                }
            });
        }

        // Clear cache
        cache()->flush();
        $this->broadcastMembershipEligibility($studentPayment, 'manual-recorded');

        return response()->json([
            'success' => true,
            'message' => 'Manual payment processed and receipt generated successfully.'
        ]);
    }

    // AJAX: Render receipt HTML for modal view in admin
    public function adminReceiptHtml($paymentId)
    {
        $payment = \App\Models\StudentPayment::find($paymentId) ?? \App\Models\Payment::find($paymentId);
        if (!$payment) {
            return response('<div class="p-6 text-red-600">Receipt not found.</div>', 404);
        }
        if (!$payment instanceof \App\Models\StudentPayment || $payment->status !== 'completed') {
            return response('<div class="p-6 text-center text-gray-500">
                <i class="ph ph-receipt text-gray-300 text-4xl mb-3 block"></i>
                <p class="font-medium">No receipt available yet.</p>
                <p class="text-xs mt-1">A receipt is only issued once the payment is verified as paid.</p>
            </div>', 404);
        }
        return view('student.payments.receipt-body', ['payment' => $payment])->render();
    }

    private function receiptFilename($payment)
    {
        $name = '';
        if ($payment->user) {
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $payment->user->first_name . $payment->user->last_name);
        }
        return $name . '-' . $payment->transaction_id;
    }

    private function generateTransactionId(?Organization $organization)
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($organization->org_acronym ?? '')));
        if ($prefix === '') {
            $prefix = 'ORG';
        }
        $prefix = substr($prefix, 0, 8);

        return $prefix . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 12));
    }

    // Receipt Generation - Store receipt path
    public function generatePdfReceipt($payment)
    {
        $filename = $this->receiptFilename($payment) . '.pdf';
        $path = 'receipts/' . $filename;

        // Ensure directory exists
        $storagePath = storage_path('app/public/' . dirname($path));
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Create a formatted HTML receipt
        $receiptHtml = $this->generateReceiptHtml($payment);

        // Save as HTML file (can be opened in browser)
        $htmlFilename = 'receipt_' . $payment->transaction_id . '.html';
        $htmlPath = 'receipts/' . $htmlFilename;
        file_put_contents(storage_path('app/public/' . $htmlPath), $receiptHtml);

        return $htmlPath;
    }

    private function generateReceiptHtml($payment)
    {
        $paymentDate = $payment->paid_at ? $payment->paid_at->format('M d, Y h:i A') : $payment->created_at->format('M d, Y h:i A');
        $paymentMethod = strtoupper($payment->payment_method ?? 'N/A');
        $category = ucfirst(str_replace('_', ' ', $payment->category ?? 'N/A'));
        $amount = number_format((float) ($payment->amount ?? 0), 2);
        $transactionId = $payment->transaction_id;
        $generatedDate = now()->format('M d, Y h:i A');
        $studentName = ($payment->user->first_name ?? '') . ' ' . ($payment->user->last_name ?? '');
        $studentId = $payment->user->student_id ?? 'N/A';
        $studentEmail = $payment->user->email ?? 'N/A';
        $refNumber = $payment->reference_number ?: null;
        $paymentTitle = $payment->payment->title ?? null;
        $paymentDue = $payment->payment->due_date ?? null;
        $dueDate = $paymentDue ? \Carbon\Carbon::parse($paymentDue)->format('M d, Y') : null;
        $orgLogo = $this->getLogoDataUri();

        $logoCell = $orgLogo ? '<td style="width:54px;text-align:right;"><img class="r-logo" src="' . $orgLogo . '" alt="Logo"></td>' : '';

        $html = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - $transactionId</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #111827; }
        .r-top { border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 18px; }
        .r-top table { width: 100%; }
        .r-top td { vertical-align: middle; }
        .r-top h1 { font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 2px 0; }
        .r-top p { font-size: 12px; color: #6b7280; margin: 0; }
        .r-logo { width: 44px; height: 44px; object-fit: contain; border-radius: 6px; }
        .r-badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; margin-bottom: 18px; }
        .r-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .r-grid td { width: 50%; vertical-align: top; padding: 0; }
        .r-grid td + td { padding-left: 14px; }
        .r-panel { border: 1px solid #e5e7eb; border-radius: 5px; padding: 10px 12px; }
        .r-panel h3 { font-size: 10px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 6px 0; padding: 0; }
        .r-tbl { width: 100%; border-collapse: collapse; }
        .r-tbl td { padding: 2px 0; font-size: 12px; }
        .r-tbl .lbl { color: #6b7280; width: 40%; white-space: nowrap; }
        .r-tbl .val { color: #111827; font-weight: 500; text-align: right; width: 60%; }
        .r-amt { border: 1px solid #e5e7eb; border-radius: 5px; padding: 14px; text-align: center; margin-bottom: 18px; background: #f9fafb; }
        .r-amt h2 { font-size: 12px; font-weight: 600; color: #6b7280; margin: 0 0 4px 0; }
        .r-amt .num { font-size: 26px; font-weight: 700; color: #111827; }
        .r-foot { font-size: 10px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="r-top">
        <table><tr>
            <td>
                <h1>Student Organization of the College of Computer Studies</h1>
                <p>Official Payment Receipt &middot; $transactionId</p>
            </td>
            $logoCell
        </tr></table>
    </div>
    <div class="r-badge"><svg width="10" height="10" viewBox="0 0 10 10" fill="none" style="vertical-align:middle;margin-right:3px;"><path d="M8.5 2.5L4 7 1.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg> Payment Completed</div>
    <table class="r-grid"><tr>
        <td>
            <div class="r-panel">
                <h3>Transaction</h3>
                <table class="r-tbl">
                    <tr><td class="lbl">Transaction ID</td><td class="val">$transactionId</td></tr>
                    <tr><td class="lbl">Date Paid</td><td class="val">$paymentDate</td></tr>
                    <tr><td class="lbl">Method</td><td class="val">$paymentMethod</td></tr>
EOT;
        if ($refNumber) {
            $html .= "\n                    <tr><td class=\"lbl\">Reference</td><td class=\"val\">$refNumber</td></tr>";
        }
        $html .= <<<EOT
                </table>
            </div>
        </td>
        <td>
            <div class="r-panel">
                <h3>Student</h3>
                <table class="r-tbl">
                    <tr><td class="lbl">Name</td><td class="val">$studentName</td></tr>
                    <tr><td class="lbl">Student ID</td><td class="val">$studentId</td></tr>
                    <tr><td class="lbl">Email</td><td class="val">$studentEmail</td></tr>
                </table>
            </div>
        </td>
    </tr></table>
    <div class="r-amt">
        <h2>Payment Details &mdash; $category</h2>
        <div class="num">&#x20B1;$amount</div>
    </div>
EOT;
        if ($paymentTitle && $dueDate) {
            $html .= <<<EOT
    <div class="r-panel" style="margin-bottom:16px;">
        <h3>Announcement</h3>
        <table class="r-tbl">
            <tr><td class="lbl">Title</td><td class="val">$paymentTitle</td></tr>
            <tr><td class="lbl">Due Date</td><td class="val">$dueDate</td></tr>
        </table>
    </div>
EOT;
        }
        $html .= <<<EOT
    <div class="r-foot">This is an official receipt. Generated $generatedDate</div>
</body>
</html>
EOT;

        return $html;
    }

    private function getLogoDataUri()
    {
        $logoUrl = organization_logo();
        $imageData = null;

        if (str_starts_with($logoUrl, 'http')) {
            $context = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']]);
            $imageData = @file_get_contents($logoUrl, false, $context);
        } elseif (file_exists($logoUrl)) {
            $imageData = @file_get_contents($logoUrl);
        } else {
            $path = public_path(ltrim(parse_url($logoUrl, PHP_URL_PATH) ?? '', '/'));
            if ($path && file_exists($path)) {
                $imageData = @file_get_contents($path);
            }
        }

        if ($imageData) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($imageData);
            return 'data:' . $mime . ';base64,' . base64_encode($imageData);
        }

        return null;
    }

    public function downloadReceipt($paymentId)
    {
        // Try to find in StudentPayment first, then Payment
        $payment = StudentPayment::find($paymentId) ?? Payment::find($paymentId);

        if (!$payment) {
            return back()->with('error', 'Payment not found.');
        }

        if (!$payment instanceof \App\Models\StudentPayment || $payment->status !== 'completed') {
            return back()->with('error', 'Receipt is only available for verified, completed payments.');
        }

        // Generate receipt if it doesn't exist
        if (!$payment->receipt_path) {
            $receiptPath = $this->generatePdfReceipt($payment);
            $payment->update(['receipt_path' => $receiptPath]);
        }

        try {
            $pdf = new \Dompdf\Dompdf();
            $pdf->loadHtml(view('student.payments.receipt', [
                'payment' => $payment,
                'forPdf' => true,
                'logoDataUri' => $this->getLogoDataUri()
            ])->render());
            $pdf->setPaper('A4', 'portrait');
            $pdf->render();
            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $this->receiptFilename($payment) . '.pdf"'
            ]);
        } catch (\Throwable $e) {
            // Fallback: HTML with auto-print
            $html = view('student.payments.receipt-html-print', ['payment' => $payment])->render();
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="' . $this->receiptFilename($payment) . '.html"'
            ]);
        }
    }

    // PayMongo GCash Checkout Creation
    public function createCheckout(Request $request)
    {
        try {
            \Log::info('createCheckout called with data: ' . json_encode($request->all()));

            $amount = $request->input('amount');
            $title = $request->input('title');
            $announcementId = $request->input('announcement_id');

            \Log::info('Parsed values - Amount: ' . $amount . ', Title: ' . $title . ', AnnouncementId: ' . $announcementId);

            if (!$amount || !$title) {
                \Log::error('Missing required fields: amount=' . $amount . ', title=' . $title);
                return response()->json([
                    'error' => 'Missing required fields'
                ], 400);
            }

            // Convert amount to centavos (PayMongo uses centavos)
            $amountInCentavos = (int)($amount * 100);

            // Initialize PayMongo API
            $paymongoSecretKey = env('PAYMONGO_SECRET_KEY');

            \Log::info('PayMongo Secret Key: ' . ($paymongoSecretKey ? 'SET' : 'NOT SET'));

            if (!$paymongoSecretKey) {
                \Log::error('PayMongo Secret Key not configured');
                return response()->json([
                    'error' => 'Payment gateway not configured. Please contact administrator.'
                ], 500);
            }

            // Create checkout session via PayMongo API
            $client = new \GuzzleHttp\Client();

            $response = $client->post('https://api.paymongo.com/v1/checkout_sessions', [
                'auth' => [$paymongoSecretKey, ''],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'amount' => $amountInCentavos,
                            'currency' => 'PHP',
                            'description' => $title,
                            'line_items' => [
                                [
                                    'name' => $title,
                                    'quantity' => 1,
                                    'amount' => $amountInCentavos,
                                    'currency' => 'PHP',
                                ]
                            ],
                            'payment_method_types' => ['gcash'],
                            'success_url' => route('payments.success') . '?amount=' . $amount . '&announcement_id=' . $announcementId . '&title=' . urlencode($title),
                            'cancel_url' => route('payments.index'),
                            'metadata' => [
                                'user_id' => auth()->id(),
                                'announcement_id' => $announcementId,
                                'title' => $title,
                            ]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['data']['attributes']['checkout_url'])) {
                return response()->json([
                    'checkout_url' => $data['data']['attributes']['checkout_url']
                ]);
            }

            \Log::error('PayMongo Response: ' . json_encode($data));
            return response()->json([
                'error' => 'Failed to create checkout session'
            ], 400);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            \Log::error('PayMongo Client Error: ' . $errorBody);
            return response()->json([
                'error' => 'Payment gateway error. Please try again.'
            ], 400);
        } catch (\Exception $e) {
            \Log::error('PayMongo Checkout Error: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            \Log::error('Full Exception: ' . (string)$e);
            return response()->json([
                'error' => 'An error occurred while processing your payment. Please try again.'
            ], 500);
        }
    }

    // PayMongo Payment Success Handler
    public function paymentSuccess(Request $request)
    {
        try {
            // For mock/testing - accept data from URL parameters
            $announcementId = $request->get('announcement_id');
            $amount = $request->get('amount', 0);
            $title = $request->get('title', 'Payment');

            // Validate required parameters
            if (!$announcementId || !$amount) {
                return redirect()->route('payments.index')
                    ->with('error', 'Payment information is incomplete.');
            }

            // Get the announcement to validate it exists
            $announcement = Payment::find($announcementId);

            if (!$announcement) {
                return redirect()->route('payments.index')
                    ->with('error', 'Payment not found.');
            }

            // Don't save payment yet - just set session flag to show modal
            // Payment will be saved when user clicks "Done" button
            return redirect()->route('payments.index')
                ->with('payment_success', true)
                ->with('payment_data', [
                    'announcement_id' => $announcementId,
                    'amount' => $amount,
                    'title' => $title,
                    'category' => $announcement->category
                ]);

        } catch (\Exception $e) {
            \Log::error('PayMongo Success Handler Error: ' . $e->getMessage());
            return redirect()->route('payments.index')
                ->with('error', 'An error occurred while processing your payment: ' . $e->getMessage());
        }
    }

    // Complete Payment - Save to database when Done is clicked
    public function completePayment(Request $request, Payment $announcement)
    {
        try {
            \Log::info('CompletePayment called', [
                'announcement_id' => $announcement->id,
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $announcement->amount,
            ]);

            if ($announcement->isProtectedMembershipFee()) {
                $this->assertCurrentMembershipFee($announcement);
                $validated['amount'] = (float) $announcement->amount;
            }

            \Log::info('Validation passed', ['validated' => $validated]);

            // Generate random transaction ID
            $transactionId = $this->generateTransactionId($announcement->organization);

            // Create student payment record with completed status
            $payment = StudentPayment::create([
                'user_id' => auth()->id(),
                'organization_id' => $announcement->organization_id,
                'payment_id' => $announcement->id,
                'transaction_id' => $transactionId,
                'category' => $announcement->category,
                'amount' => $validated['amount'],
                'payment_method' => 'gcash',
                'status' => 'completed',
                'verification_status' => 'approved',
                'paid_at' => now(),
                'verified_by' => auth()->id(),
            ]);

            \Log::info('Payment created successfully', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status
            ]);

            // Generate receipt
            $receiptPath = $this->generatePdfReceipt($payment);
            $payment->update(['receipt_path' => $receiptPath]);

            \Log::info('Receipt generated', ['receipt_path' => $receiptPath]);

            // Send database notification only (no email for PayMongo)
            auth()->user()->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\PaymentStatusNotification',
                'data' => [
                    'payment_id' => $payment->id,
                    'organization_id' => $payment->organization_id,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => $payment->amount,
                    'category' => $payment->category,
                    'status' => 'completed',
                    'title' => 'Payment Completed',
                    'message' => 'Your payment of ₱' . number_format((float) ($payment->amount ?? 0), 2) . ' has been completed successfully!',
                    'icon' => 'ph-check-circle',
                    'color' => 'green',
                    'url' => route('payments.index'),
                ],
                'read_at' => null,
            ]);

            $this->broadcastMembershipEligibility($payment, 'completed');

            return response()->json([
                'success' => true,
                'message' => 'Payment submitted successfully! Your payment is now pending verification by SOCCS officers.',
                'transaction_id' => $payment->transaction_id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation Error in CompletePayment', [
                'errors' => $e->validator->errors()->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Complete Payment Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // GCash Payment Integration (Mock API)
    public function processGcashPayment(Request $request, Payment $announcement)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $announcement->amount,
            ]);

            if ($announcement->isProtectedMembershipFee()) {
                $this->assertCurrentMembershipFee($announcement);
                $validated['amount'] = (float) $announcement->amount;
            }

            // Generate transaction ID
            $transactionId = $this->generateTransactionId($announcement->organization);

            // Create student payment record with pending status
            $studentPayment = StudentPayment::create([
                'user_id' => auth()->id(),
                'organization_id' => $announcement->organization_id,
                'payment_id' => $announcement->id,
                'transaction_id' => $transactionId,
                'category' => $announcement->category,
                'amount' => $validated['amount'],
                'payment_method' => 'gcash',
                'status' => 'pending',
                'paid_at' => now(),
            ]);

            $this->broadcastMembershipEligibility($studentPayment, 'submitted');

            return response()->json([
                'success' => true,
                'message' => 'Payment submitted successfully. Awaiting verification.',
                'transaction_id' => $transactionId,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Create Pending GCash Payment
    public function createPendingGcashPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'title' => 'required|string',
                'payment_id' => 'required|exists:payments,id',
            ]);

            $announcement = Payment::findOrFail($validated['payment_id']);

            if ($announcement->isProtectedMembershipFee()) {
                $this->assertCurrentMembershipFee($announcement);
                $validated['amount'] = (float) $announcement->amount;
                $validated['title'] = $announcement->title;
            }

            // Get the latest payment attempt for this announcement
            $latestPayment = auth()->user()->studentPayments()
                ->where('payment_id', $announcement->id)
                ->latest()
                ->first();

            // If the latest attempt was rejected/failed, allow resubmission by reusing it
            if ($latestPayment && in_array($latestPayment->verification_status, ['rejected', 'failed'], true)) {
                // Keep the payment rejected until the student actually uploads a
                // new receipt. The old receipt stays visible in the upload modal
                // so the student can see what they are replacing.
                $latestPayment->update([
                    'amount' => $validated['amount'],
                    'organization_id' => $announcement->organization_id,
                    'verified_by' => null,
                ]);

                $studentPayment = $latestPayment;
            } elseif ($latestPayment && in_array($latestPayment->verification_status, ['pending', 'approved'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an existing payment for this.',
                ], 400);
            } else {
                // Generate transaction ID only for new payments
                $transactionId = $this->generateTransactionId($announcement->organization);

                // Create new student payment record
                $studentPayment = StudentPayment::create([
                    'user_id' => auth()->id(),
                    'organization_id' => $announcement->organization_id,
                    'payment_id' => $announcement->id,
                    'transaction_id' => $transactionId,
                    'category' => $announcement->category,
                    'amount' => $validated['amount'],
                    'payment_method' => 'gcash',
                    'status' => 'pending',
                    'verification_status' => 'pending',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment record created. Please upload your receipt.',
                'student_payment_id' => $studentPayment->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error creating pending payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Upload GCash Receipt
    public function uploadGcashReceipt(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment_id' => 'required|exists:student_payments,id',
                'reference_number' => 'required|string|max:50',
                'receipt' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            ]);

            $studentPayment = StudentPayment::findOrFail($validated['payment_id']);

            // Check if this payment belongs to the authenticated user
            if ($studentPayment->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            // Store the receipt
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $filename = 'gcash_receipt_' . $studentPayment->transaction_id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('receipts/gcash', $filename, 'public');

                // Update student payment with receipt path and reference number
                $studentPayment->update([
                    'reference_number' => $validated['reference_number'],
                    'gcash_receipt_path' => $path,
                    'status' => 'pending',
                    'verification_status' => 'pending',
                    'rejection_reason' => null,
                    'verified_by' => null,
                ]);

                $this->broadcastMembershipEligibility($studentPayment, 'receipt-uploaded');

                return response()->json([
                    'success' => true,
                    'message' => 'Receipt uploaded successfully. Your payment is now pending verification.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No receipt file provided.',
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error uploading receipt: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Submit GCash Payment with Receipt (Create record and upload receipt in one go)
    public function submitGcashWithReceipt(Request $request)
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0',
                'title' => 'required|string',
                'payment_id' => 'required|exists:payments,id',
                'reference_number' => 'required|string|max:50',
                'receipt' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            ]);

            $announcement = Payment::findOrFail($validated['payment_id']);

            if ($announcement->isProtectedMembershipFee()) {
                $this->assertCurrentMembershipFee($announcement);
                $validated['amount'] = (float) $announcement->amount;
                $validated['title'] = $announcement->title;
            }

            // Check if student already has a pending or approved payment for this announcement
            $existingPayment = auth()->user()->studentPayments()
                ->where('payment_id', $announcement->id)
                ->whereIn('verification_status', ['pending', 'approved'])
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an existing payment for this.',
                ], 400);
            }

            // Generate transaction ID
            $transactionId = $this->generateTransactionId($announcement->organization);

            // Store the receipt
            $file = $request->file('receipt');
            $filename = 'gcash_receipt_' . $transactionId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('receipts/gcash', $filename, 'public');

            // Create student payment record with receipt
            $studentPayment = StudentPayment::create([
                'user_id' => auth()->id(),
                'organization_id' => $announcement->organization_id,
                'payment_id' => $announcement->id,
                'transaction_id' => $transactionId,
                'reference_number' => $validated['reference_number'],
                'category' => $announcement->category,
                'amount' => $validated['amount'],
                'payment_method' => 'gcash',
                'status' => 'pending',
                'verification_status' => 'pending',
                'gcash_receipt_path' => $path,
            ]);

            $this->broadcastMembershipEligibility($studentPayment, 'submitted');

            return response()->json([
                'success' => true,
                'message' => 'Payment submitted successfully! Your payment is now pending verification.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error submitting payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Admin: Approve GCash Payment
    public function approveGcashPayment(Request $request, $paymentId)
    {
        try {
            $studentPayment = StudentPayment::findOrFail($paymentId);

            $studentPayment->update([
                'status' => 'completed',
                'verification_status' => 'approved',
                'verified_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            $this->broadcastMembershipEligibility($studentPayment, 'approved');

            // Send notification to student
            $studentPayment->user->notify(new \App\Notifications\PaymentStatusNotification(
                $studentPayment,
                'approved'
            ));

            return response()->json([
                'success' => true,
                'message' => 'Payment approved successfully!',
                'reload' => false
            ]);
        } catch (\Exception $e) {
            \Log::error('Error approving payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'reload' => true
            ], 500);
        }
    }

    // Admin: Reject GCash Payment
    public function rejectGcashPayment(Request $request, $paymentId)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $studentPayment = StudentPayment::findOrFail($paymentId);

            $studentPayment->update([
                'status' => 'failed',
                'verification_status' => 'rejected',
                'rejection_reason' => $validated['reason'],
                'verified_by' => auth()->id(),
            ]);

            $this->broadcastMembershipEligibility($studentPayment, 'rejected');

            // Send notification to student
            $studentPayment->user->notify(new \App\Notifications\PaymentStatusNotification(
                $studentPayment,
                'rejected',
                $validated['reason']
            ));

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully!',
                'reload' => false
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'reload' => true
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error rejecting payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'reload' => true
            ], 500);
        }
    }

    private function assertCurrentMembershipFee(Payment $announcement, bool $requirePaymentInstructions = true): void
    {
        $currentOrganizationId = session('current_organization_id');
        $organization = $currentOrganizationId ? Organization::find($currentOrganizationId) : null;

        $isActiveMember = $organization && auth()->user()?->organizations()
            ->whereKey($organization->getKey())
            ->wherePivot('membership_status', 'Active')
            ->exists();

        $currentFee = $organization
            ? app(MembershipFeeService::class)->currentFeeFor($organization)
            : null;

        if (! $organization?->isMotherOrg()
            || ! $organization->canBeUsed()
            || ! $isActiveMember
            || ! $currentFee instanceof Payment
            || $currentFee->getKey() !== $announcement->getKey()) {
            throw ValidationException::withMessages([
                'payment_id' => ['This Membership Fee is not available for your current Mother Organization.'],
            ]);
        }

        if ($requirePaymentInstructions && trim((string) $announcement->gcash_number) === '') {
            throw ValidationException::withMessages([
                'payment_id' => ['Membership Fee payment details are still being set up. Please contact your organization officer.'],
            ]);
        }
    }

    private function assertProtectedMembershipFeeImmutability(Request $request, Payment $announcement): void
    {
        $expected = [
            'title' => (string) $announcement->title,
            'category' => (string) $announcement->category,
            'due_date' => $announcement->due_date?->format('Y-m-d'),
            'membership_cycle_start' => $announcement->membership_cycle_start?->format('Y-m-d'),
        ];

        foreach ($expected as $field => $value) {
            if (! $request->has($field)) {
                continue;
            }

            $submitted = (string) $request->input($field);
            if (in_array($field, ['due_date', 'membership_cycle_start'], true)) {
                $submitted = substr($submitted, 0, 10);
            }

            if ($submitted !== (string) $value) {
                throw ValidationException::withMessages([
                    $field => ['This field is locked for the required annual Membership Fee.'],
                ]);
            }
        }

        if ($request->has('is_required_membership_fee')
            && ! filter_var($request->input('is_required_membership_fee'), FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'is_required_membership_fee' => ['Membership Fee protection cannot be removed.'],
            ]);
        }
    }

    private function broadcastPaymentEvent(object $event, bool $toOthers = false): void
    {
        try {
            $broadcast = broadcast($event);
            if ($toOthers) {
                $broadcast->toOthers();
            }
        } catch (Throwable $exception) {
            \Log::warning('Payment realtime broadcast failed; the payment change was saved.', [
                'event' => $event::class,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function broadcastMembershipEligibility(StudentPayment $studentPayment, string $action = 'updated'): void
    {
        $this->broadcastStudentPaymentUpdate($studentPayment, $action);

        if (! $studentPayment->user_id || ! $studentPayment->payment_id) {
            return;
        }

        $announcement = Payment::allOrganizations()->find($studentPayment->payment_id);
        if (! $announcement?->isProtectedMembershipFee()) {
            return;
        }

        $this->broadcastPaymentEvent(new MembershipFeeEligibilityUpdated(
            userId: (int) $studentPayment->user_id,
            eligible: $studentPayment->status === 'completed' && $studentPayment->verification_status === 'approved',
            membershipPaymentId: $studentPayment->getKey(),
        ));
    }

    private function broadcastStudentPaymentUpdate(StudentPayment $studentPayment, string $action): void
    {
        if (! $studentPayment->user_id || ! $studentPayment->organization_id) {
            return;
        }

        $this->broadcastPaymentEvent(new StudentPaymentUpdated($studentPayment, $action), true);
    }

    public function getQRCode($id)
    {
        $payment = Payment::findOrFail($id);
        
        if ($payment->qr_code) {
            return response()->json([
                'qr_code' => storage_url($payment->qr_code)
            ]);
        }
        
        return response()->json([
            'qr_code' => null,
            'message' => 'QR Code not available'
        ], 404);
    }
}
