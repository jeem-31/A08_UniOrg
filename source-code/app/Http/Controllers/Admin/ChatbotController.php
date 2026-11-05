<?php

namespace App\Http\Controllers\Admin;

use App\Events\FaqCreated;
use App\Events\FaqDeleted;
use App\Events\FaqUpdated;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\ChatLog;
use App\Models\AdminChat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatbotController extends Controller
{
    /** Maximum upload size for chat attachments (KB). */
    private const MAX_ATTACHMENT_KB = 20480;

    /** @var list<string> Extension backstop for the video ban (MIME may misreport). */
    private const VIDEO_EXTENSIONS = [
        'mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', '3gp', 'mpeg', 'mpg',
    ];

    /** @var list<string> Executable/scripting types that chat uploads never accept. */
    private const UNSAFE_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'sh', 'php', 'js', 'html', 'htm', 'svg', 'msi', 'dll', 'scr', 'ps1', 'vbs', 'jar', 'apk',
    ];

    public function index()
    {
        $faqs = Faq::published()->latest()->take(10)->get();
        return view('student.chatbot.index', compact('faqs'));
    }

    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
        ]);

        $question = strtolower($request->question);
        $answer = $this->findAnswer($question);

        // Log the conversation
        ChatLog::create([
            'user_id' => auth()->id(),
            'question' => $request->question,
            'answer' => $answer,
        ]);

        return response()->json([
            'answer' => $answer,
            'found' => $answer !== $this->getDefaultResponse(),
        ]);
    }

    private function findAnswer($question)
    {
        // Get all FAQs
        $faqs = Faq::published()->get();

        // Try to find matching FAQ
        foreach ($faqs as $faq) {
            $faqQuestion = strtolower($faq->question);
            $keywords = $faq->keywords ?? [];

            // Check if question matches FAQ question
            if (str_contains($faqQuestion, $question) || str_contains($question, $faqQuestion)) {
                $faq->incrementViewCount();
                return $faq->answer;
            }

            // Check keywords
            foreach ($keywords as $keyword) {
                if (str_contains($question, strtolower($keyword))) {
                    $faq->incrementViewCount();
                    return $faq->answer;
                }
            }
        }

        // Rule-based responses for common queries
        $rules = [
            'payment' => 'For payment inquiries, please visit the Payments section or contact the SOCCS treasurer.',
            'event' => 'You can view all upcoming events in the Events Calendar section.',
            'membership' => 'To become a member, please complete your profile and pay the membership fee in the Payments section.',
            'document' => 'You can download document templates from the Documents section and upload your requirements in My Documents.',
            'election' => 'Election information and voting can be accessed in the Elections section when active.',
            'library' => 'Study materials and resources are available in the e-Library section.',
            'contact' => 'You can reach SOCCS officers through the Contact page or submit a message in the Complaints & Suggestions section.',
        ];

        foreach ($rules as $keyword => $response) {
            if (str_contains($question, $keyword)) {
                return $response;
            }
        }

        return $this->getDefaultResponse();
    }

    private function getDefaultResponse()
    {
        return "I'm sorry, I couldn't find an answer to your question. Please contact the SOCCS officers for assistance or visit the FAQ section for more information.";
    }

    public function feedback(Request $request)
    {
        $request->validate([
            'chat_log_id' => 'required|exists:chat_logs,id',
            'was_helpful' => 'required|boolean',
        ]);

        $chatLog = ChatLog::find($request->chat_log_id);
        $chatLog->update(['was_helpful' => $request->was_helpful]);

        return response()->json(['success' => true]);
    }

    // Admin methods
    public function adminFaqs()
    {
        // Load the FAQ dataset once so status tabs can switch instantly in Alpine.js.
        $cacheKey = 'chatbot.faqs.admin.all';

        $data = cache()->remember($cacheKey, 120, function () {
            $faqs = Faq::select('id', 'question', 'answer', 'category', 'keywords', 'status', 'is_published', 'view_count', 'created_at', 'updated_at')
                ->latest()
                ->get();

            // Get counts for the status tabs.
            $totalFaqs = $faqs->count();
            $publishedCount = $faqs->where('status', 'published')->count();
            $draftCount = $faqs->where('status', 'draft')->count();
            $hiddenCount = $faqs->where('status', 'hidden')->count();

            return compact('faqs', 'totalFaqs', 'publishedCount', 'draftCount', 'hiddenCount');
        });

        return response()
            ->view('admin.chatbot.faqs', $data)
            ->header('Cache-Control', 'private, max-age=120');
    }

    public function createFaq()
    {
        return view('admin.chatbot.create-faq');
    }

    public function storeFaq(Request $request)
    {
        // Debug: Log what's being received
        \Log::info('FAQ Store Request', [
            'all_data' => $request->all(),
            'action_type' => $request->input('action_type'),
            'has_is_published' => $request->has('is_published'),
            'has_save_as_draft' => $request->has('save_as_draft'),
        ]);

        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string',
            'keywords' => 'nullable|string',
        ]);

        if ($request->keywords) {
            $validated['keywords'] = array_map('trim', explode(',', $request->keywords));
        }

        // Check action_type from hidden field (set by Alpine.js)
        $actionType = $request->input('action_type');
        
        if ($actionType === 'publish' || $request->has('is_published')) {
            // Publish button clicked
            $validated['status'] = 'published';
            $validated['is_published'] = \DB::raw('TRUE');
            \Log::info('Setting status to PUBLISHED');
        } else {
            // Save as Draft button clicked or default
            $validated['status'] = 'draft';
            $validated['is_published'] = \DB::raw('FALSE');
            \Log::info('Setting status to DRAFT');
        }

        \Log::info('Final validated data', $validated);

        $faq = Faq::create($validated);

        // Broadcast realtime update to the faqs channel
        broadcast(new FaqCreated($faq));

        // Clear cache
        cache()->flush();

        return redirect()->route('admin.chatbot.faqs')
            ->with('success', 'FAQ created successfully.');
    }

    public function editFaq(Faq $faq)
    {
        return view('admin.chatbot.edit-faq', compact('faq'));
    }

    public function updateFaq(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string',
            'keywords' => 'nullable|string',
            'is_published' => 'boolean',
        ]);

        if ($request->keywords) {
            $validated['keywords'] = array_map('trim', explode(',', $request->keywords));
        }

        // Handle publish/hide/unhide functionality
        if ($request->has('publish_faq') && $request->publish_faq == '1') {
            // Publish a draft FAQ
            $validated['status'] = 'published';
            $validated['is_published'] = \DB::raw('TRUE');
        } elseif ($request->has('hide_faq') && $request->hide_faq == '1') {
            // Hide a published FAQ
            $validated['status'] = 'hidden';
            $validated['is_published'] = \DB::raw('FALSE');
        } elseif ($request->has('unhide_faq') && $request->unhide_faq == '1') {
            // Unhide a hidden FAQ
            $validated['status'] = 'published';
            $validated['is_published'] = \DB::raw('TRUE');
        }
        // If no status change requested, preserve current status (don't include in $validated)

        $faq->update($validated);

        // Broadcast realtime update to the faqs channel
        broadcast(new FaqUpdated($faq->fresh()));

        // Clear cache
        cache()->flush();

        return redirect()->route('admin.chatbot.faqs')
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroyFaq(Faq $faq)
    {
        $faqId = $faq->id;
        $faq->delete();

        // Broadcast realtime update to the faqs channel
        broadcast(new FaqDeleted($faqId));

        // Clear cache
        cache()->flush();

        return back()->with('success', 'FAQ deleted successfully.');
    }

    public function chatLogs()
    {
        $logs = ChatLog::with('user')->latest()->paginate(20);
        return view('admin.chatbot.logs', compact('logs'));
    }

    // Student Chat Methods (In-page mode)
    public function getMessages()
    {
        $user = auth()->user();
        
        // Debug: Log the user info
        \Log::info('Loading messages for user', [
            'auth_id' => auth()->id(),
            'user_db_id' => $user ? $user->id : 'null',
            'student_id' => $user ? $user->student_id : 'null',
        ]);
        
        $messages = AdminChat::where('user_id', $user->id)
            ->with(['user', 'admin'])
            ->orderBy('created_at', 'asc')
            ->get();

        \Log::info('Found messages', ['count' => $messages->count()]);

        // Attach presentation meta (url / is_image / file_type) so the
        // student chat can render attachments sent by the admin.
        $messages = $messages->map(function ($message) {
            return array_merge($message->toArray(), $this->attachmentPayload($message));
        })->values();

        return response()->json(['messages' => $messages]);
    }

    public function markMessagesAsRead()
    {
        try {
            $user = auth()->user();
            
            // Mark admin messages as read when student clicks "Chat with SOCCS Admin"
            // Use raw SQL for PostgreSQL boolean compatibility
            \DB::table('admin_chats')
                ->where('user_id', $user->id)
                ->where('sender_type', 'admin')
                ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
                ->update(['is_read' => \DB::raw('CAST(1 AS BOOLEAN)')]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Error marking messages as read', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string|max:1000',
            'attachment' => [
                'nullable',
                'file',
                'max:'.self::MAX_ATTACHMENT_KB,
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('The attachment must be a file.');
                        return;
                    }
                    if (str_starts_with((string) $value->getMimeType(), 'video/')) {
                        $fail('Video files are not allowed.');
                        return;
                    }
                    if (in_array(strtolower($value->getClientOriginalExtension()), [
                        ...self::VIDEO_EXTENSIONS,
                        ...self::UNSAFE_EXTENSIONS,
                    ], true)) {
                        $fail('This file type is not allowed.');
                    }
                },
            ],
        ]);

        try {
            // Debug: Check if user is authenticated
            if (!auth()->check()) {
                \Log::error('User not authenticated when sending message');
                return response()->json(['success' => false, 'error' => 'User not authenticated'], 401);
            }

            if (!$request->filled('message') && !$request->hasFile('attachment')) {
                return response()->json(['success' => false, 'error' => 'Message or attachment is required.'], 422);
            }

            $user = auth()->user();

            // Explicitly set organization_id to handle cases where user doesn't have one
            $messageData = [
                'user_id' => $user->id,
                'message' => trim((string) $request->message),
                'sender_type' => 'student',
                'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'), // Cast to boolean for PostgreSQL
            ];

            // Resolve the organization from the session (set at login), falling
            // back to the user's first organization so saved messages stay
            // visible inside the organization-scoped chat queries.
            $organizationId = session('current_organization_id')
                ?: ($user->organizations->first()?->id ?? null);

            if ($organizationId) {
                $messageData['organization_id'] = $organizationId;
            }

            $message = AdminChat::create(array_merge($messageData, $this->attachmentColumns($request->file('attachment'))));

            // No broadcast needed: Supabase Realtime streams the inserted
            // row straight from the database to subscribed clients.
            return response()->json([
                'success' => true,
                'message' => array_merge(
                    [
                        'id' => $message->id,
                        'user_id' => $message->user_id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type,
                        'created_at' => $message->created_at,
                    ],
                    $this->attachmentPayload($message)
                ),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending message', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to send message'
            ], 500);
        }
    }

    // Admin Chat Methods
    public function adminChats()
    {
        // Don't mark messages as read automatically
        // Messages will be marked as read only when admin views specific conversation
            
        return view('admin.chatbot.chats');
    }
    
    public function getUnreadCount()
    {
        $count = AdminChat::where('sender_type', 'student')
            ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
            ->count();
            
        return response()->json(['count' => $count]);
    }

    public function getAdminUnreadCount()
    {
        $count = AdminChat::where('channel', 'student')
            ->where('sender_type', 'student')
            ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
            ->count();

        // OSAS messages for the current organization count toward the same
        // floating chat badge.
        $organizationId = session('current_organization_id');
        if ($organizationId) {
            $count += AdminChat::allOrganizations()
                ->unreadFromOsas($organizationId)
                ->count();
        }

        return response()->json(['count' => $count]);
    }

    public function getStudentUnreadCount()
    {
        // Count unread messages from admin for the current student
        $count = AdminChat::where('user_id', auth()->id())
            ->where('sender_type', 'admin')
            ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
            ->count();
            
        return response()->json(['count' => $count]);
    }

    public function getStudents()
    {
        // Only student-channel conversations appear as contacts: OSAS threads
        // live under the synthetic 'osas' contact below, and OSAS admins never
        // show up as regular students (their osas-channel sends would otherwise
        // create phantom "OSAS Administrator" / self contacts).
        $users = User::where('role', '!=', 'osas_admin')
            ->whereHas('adminChats', function ($q) {
                $q->where('channel', 'student');
            })
            ->get();

        // Single grouped query for unread counts (replaces the N+1 loop).
        $unreadCounts = AdminChat::selectRaw('user_id, COUNT(*) as total')
            ->where('channel', 'student')
            ->where('sender_type', 'student')
            ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // Single query for the whole org's chat history so the widget can
        // render a clicked conversation instantly without a follow-up fetch.
        $messagesByUser = AdminChat::select('id', 'user_id', 'message', 'sender_type', 'created_at', 'attachment_path', 'attachment_name')
            ->where('channel', 'student')
            ->whereIn('user_id', $users->pluck('id'))
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('user_id');

        $students = $users->map(function($user) use ($unreadCounts, $messagesByUser) {
            $messages = $messagesByUser->get($user->id, collect())
                ->sortByDesc('created_at')
                ->take(200)
                ->sortBy('created_at')
                ->values();

            $lastMessage = $messages->last();

            return [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'photo' => $user->photo,
                'role' => $user->role,
                'last_message' => $lastMessage ? \Str::limit($lastMessage->message, 30) : 'No messages yet',
                'last_message_time' => $lastMessage ? $lastMessage->created_at : null,
                'unread_count' => (int) ($unreadCounts[$user->id] ?? 0),
                'messages' => $messages->map(function($message) {
                    return array_merge(
                        [
                            'id' => $message->id,
                            'user_id' => $message->user_id,
                            'message' => $message->message,
                            'sender_type' => $message->sender_type,
                            'created_at' => $message->created_at,
                        ],
                        $this->attachmentPayload($message)
                    );
                })->values(),
            ];
        });

        // Add the OSAS thread as a synthetic contact so the same floating
        // chat handles both student messages and OSAS messages. The OSAS
        // admin role drives the LSPU logo avatar on the client side.
        $organizationId = session('current_organization_id');
        if ($organizationId) {
            $osasMessages = AdminChat::allOrganizations()
                ->osasThread($organizationId)
                ->orderBy('created_at', 'asc')
                ->get();

            // Only the newest batch is bundled so long conversations never
            // ship the whole history; older messages load via getHistory.
            $capped = collect($osasMessages)
                ->sortByDesc('created_at')
                ->take(50)
                ->sortBy('created_at')
                ->values();

            $lastOsas = $capped->last();

            $students->push([
                'id' => 'osas',
                'name' => 'OSAS Administration',
                'email' => 'osas@lspu.edu.ph',
                'photo' => null,
                'role' => 'osas_admin',
                'channel' => 'osas',
                'last_message' => $lastOsas
                    ? ($lastOsas->attachment_path && ! $lastOsas->message
                        ? '[Attachment]'
                        : \Str::limit($lastOsas->message, 30))
                    : 'No messages yet',
                'last_message_time' => $lastOsas ? $lastOsas->created_at : null,
                'unread_count' => AdminChat::allOrganizations()->unreadFromOsas($organizationId)->count(),
                'has_more' => $osasMessages->count() > $capped->count(),
                'oldest_id' => $capped->first()?->id,
                'messages' => $capped->map(function($message) {
                    return array_merge([
                        'id' => $message->id,
                        'user_id' => $message->user_id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type,
                        'created_at' => $message->created_at,
                    ], $this->attachmentPayload($message));
                })->values(),
            ]);
        }

        $students = $students
            // Most recently active conversations first
            ->sortByDesc(function($student) {
                return optional($student['last_message_time'])->timestamp ?? 0;
            })
            ->values();

        return response()->json(['students' => $students]);
    }

    public function getStudentMessages($userId)
    {
        try {
            \Log::info('getStudentMessages called', [
                'user_id' => $userId,
                'auth_user_id' => auth()->id(),
                'auth_user_role' => auth()->user()->role ?? 'unknown'
            ]);

            // Validate userId ('osas' is the synthetic OSAS thread contact)
            if ($userId !== 'osas' && (!is_numeric($userId) || $userId <= 0)) {
                \Log::error('Invalid user ID', ['user_id' => $userId]);
                return response()->json(['error' => 'Invalid user ID'], 400);
            }

            $organizationId = session('current_organization_id');

            // OSAS thread: return the current organization's OSAS conversation
            // and mark the OSAS messages as read.
            if ($userId === 'osas') {
                if (!$organizationId) {
                    return response()->json(['error' => 'No organization selected.'], 422);
                }

                AdminChat::allOrganizations()
                    ->unreadFromOsas($organizationId)
                    ->update(['is_read' => \DB::raw('CAST(1 AS BOOLEAN)')]);

                $thread = AdminChat::allOrganizations()
                    ->osasThread($organizationId)
                    ->select(
                        'id', 'user_id', 'admin_id', 'message', 'sender_type', 'is_read', 'created_at', 'updated_at',
                        'attachment_path', 'attachment_name'
                    )
                    ->orderBy('id', 'desc')
                    ->limit(50)
                    ->get()
                    ->sortBy('id')
                    ->values();

                $total = AdminChat::allOrganizations()
                    ->osasThread($organizationId)
                    ->count();

                $messages = $thread->map(function ($message) {
                    return array_merge($message->toArray(), $this->attachmentPayload($message));
                })->values();

                return response()->json([
                    'messages' => $messages,
                    'has_more' => $total > $messages->count(),
                    'oldest_id' => $messages->first()['id'] ?? 0,
                ]);
            }

            // First, mark student messages as read BEFORE fetching
            // This prevents any race conditions
            $updatedCount = \DB::table('admin_chats')
                ->where('channel', 'student')
                ->where('user_id', $userId)
                ->where('sender_type', 'student')
                ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
                ->update(['is_read' => \DB::raw('CAST(1 AS BOOLEAN)')]);

            \Log::info('Marked messages as read', [
                'user_id' => $userId,
                'updated_count' => $updatedCount
            ]);

            // Then fetch messages - no need for user relationship since we already have user_id
            $messages = AdminChat::where('channel', 'student')
                ->where('user_id', $userId)
                ->select('id', 'user_id', 'admin_id', 'message', 'sender_type', 'is_read', 'created_at', 'updated_at', 'attachment_path', 'attachment_name')
                ->orderBy('created_at', 'asc')
                ->get();

            \Log::info('Successfully loaded messages', [
                'user_id' => $userId,
                'message_count' => $messages->count()
            ]);

            $messages = $messages->map(function ($message) {
                return array_merge($message->toArray(), $this->attachmentPayload($message));
            })->values();

            return response()->json(['messages' => $messages]);
        } catch (\Exception $e) {
            \Log::error('Error getting student messages', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return JSON error response instead of letting Laravel render HTML error page
            return response()->json([
                'error' => 'Failed to load messages',
                'message' => $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ] : null
            ], 500);
        }
    }

    public function sendAdminMessage(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'nullable|string|max:1000',
            'attachment' => [
                'nullable',
                'file',
                'max:'.self::MAX_ATTACHMENT_KB,
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('The attachment must be a file.');
                        return;
                    }
                    if (str_starts_with((string) $value->getMimeType(), 'video/')) {
                        $fail('Video files are not allowed.');
                        return;
                    }
                    if (in_array(strtolower($value->getClientOriginalExtension()), [
                        ...self::VIDEO_EXTENSIONS,
                        ...self::UNSAFE_EXTENSIONS,
                    ], true)) {
                        $fail('This file type is not allowed.');
                    }
                },
            ],
        ]);

        try {
            // Debug: Check if admin is authenticated
            if (!auth()->check()) {
                \Log::error('Admin not authenticated when sending message');
                return response()->json(['success' => false, 'error' => 'Admin not authenticated'], 401);
            }

            if (!$request->filled('message') && !$request->hasFile('attachment')) {
                return response()->json(['success' => false, 'error' => 'Message or attachment is required.'], 422);
            }

            $admin = auth()->user();

            // Explicitly set organization_id to handle cases where admin doesn't have one
            $messageData = [
                'user_id' => $request->user_id,
                'admin_id' => $admin->id,
                'message' => trim((string) $request->message),
                'sender_type' => 'admin',
                'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'), // Cast to boolean for PostgreSQL - Mark as unread so student will see the badge
            ];

            // Resolve the organization from the session (set at login), falling
            // back to the admin's first organization so saved messages stay
            // visible inside the organization-scoped chat queries.
            $organizationId = session('current_organization_id')
                ?: ($admin->organizations->first()?->id ?? null);

            if ($organizationId) {
                $messageData['organization_id'] = $organizationId;
            }

            $message = AdminChat::create(array_merge($messageData, $this->attachmentColumns($request->file('attachment'))));

            // No broadcast needed: Supabase Realtime streams the inserted
            // row straight from the database to subscribed clients.
            return response()->json([
                'success' => true, 
                'message' => array_merge(
                    [
                        'id' => $message->id,
                        'user_id' => $message->user_id,
                        'admin_id' => $message->admin_id,
                        'message' => $message->message,
                        'sender_type' => $message->sender_type,
                        'created_at' => $message->created_at,
                    ],
                    $this->attachmentPayload($message)
                ),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error sending admin message', [
                'admin_id' => auth()->id(),
                'user_id' => $request->user_id ?? 'null',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'error' => config('app.debug') ? $e->getMessage() : 'Failed to send message'
            ], 500);
        }
    }

    /**
     * Attachment presentation meta for a chat message, or empty when none.
     * Derived from the stored path (extension) - no metadata columns exist.
     * The URL points at the attachment download route the caller can reach:
     * students use the chatbot route, admins use the org-scoped admin route.
     *
     * @return array<string, mixed>
     */
    private function attachmentPayload(AdminChat $message): array
    {
        if (! $message->attachment_path) {
            return [];
        }

        $extension = strtolower(pathinfo($message->attachment_path, PATHINFO_EXTENSION));

        $route = auth()->user()->role === 'student'
            ? 'chatbot.attachment'
            : 'admin.chat.attachment';

        return [
            'attachment_path' => $message->attachment_path,
            'attachment_url' => route($route, $message->id),
            'attachment_name' => $message->attachment_name,
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true),
            'file_type' => $extension,
        ];
    }

    /**
     * Attachment column values for an uploaded file, or empty when none.
     */
    private function attachmentColumns(?object $attachment): array
    {
        if (!$attachment) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store('chat', storage_disk()),
            'attachment_name' => $attachment->getClientOriginalName(),
        ];
    }

    /**
     * Download / Inline a student chat attachment (auth-protected).
     * Students may only fetch files from their own conversation.
     */
    public function downloadAttachment(AdminChat $chat)
    {
        $user = auth()->user();

        if ($user->role === 'student') {
            if ((int) $chat->user_id !== (int) $user->id || $chat->channel !== 'student') {
                abort(403, 'Not authorized to access this attachment.');
            }
        }

        if (!$chat->attachment_path || !Storage::disk(storage_disk())->exists($chat->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        $disk = Storage::disk(storage_disk());
        $path = $chat->attachment_path;
        $name = $chat->attachment_name ?: basename($path);

        if ($this->pathIsImage($path)) {
            return $disk->response(
                $path,
                $name,
                ['Content-Type' => $disk->mimeType($path) ?? 'application/octet-stream']
            );
        }

        return $disk->download($path, $name);
    }

    /** @return list<string> */
    private static function imageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    }

    private static function pathIsImage(string $path): bool
    {
        return in_array(self::pathExtension($path), self::imageExtensions(), true);
    }

    private static function pathExtension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
}
