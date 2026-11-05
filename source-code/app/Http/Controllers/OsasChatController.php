<?php

namespace App\Http\Controllers;

use App\Models\AdminChat;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewOsasChatNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OsasChatController extends Controller
{
    /**
     * Number of messages loaded per page (initial render + history batches).
     */
    private const PAGE_SIZE = 50;

    /** @var list<string> Extension backstop for the video ban (MIME may misreport). */
    private const VIDEO_EXTENSIONS = [
        'mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', '3gp', 'mpeg', 'mpg',
    ];

    /** @var list<string> Executable/scripting types that chat uploads never accept. */
    private const UNSAFE_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'sh', 'php', 'js', 'html', 'htm', 'svg', 'msi', 'dll', 'scr', 'ps1', 'vbs', 'jar', 'apk',
    ];

    /**
     * OSAS floating chat: list of organizations with last message + unread counts.
     */
    public function orgs(Request $request)
    {
        $query = Organization::with('settings')
            ->withCount(['adminChats as unread_count' => function ($q) {
                $q->withoutGlobalScope('organization')
                    ->where('channel', 'osas')
                    ->where('sender_type', 'org')
                    ->whereRaw('CAST(is_read AS INTEGER) = ?', [0]);
            }]);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('org_acronym', 'like', '%' . $request->search . '%');
            });
        }

        $organizations = $query->orderBy('unread_count', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        // Single query for every organization's OSAS thread so the widget can
        // render a clicked conversation instantly without a follow-up fetch.
        // Only the newest batch is bundled so long conversations never ship
        // the whole history; older messages load via getHistory.
        $messagesByOrg = AdminChat::allOrganizations()
            ->where('channel', 'osas')
            ->whereIn('organization_id', $organizations->pluck('id'))
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('organization_id');

        return response()->json([
            'organizations' => $organizations->map(function ($org) use ($messagesByOrg) {
                $rawLogo = $org->settings?->getRawOriginal('logo_url');

                $messages = $messagesByOrg->get($org->id, collect())
                    ->sortByDesc('created_at')
                    ->take(50)
                    ->sortBy('created_at')
                    ->values();

                $lastMessage = $messages->last();

                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'org_acronym' => $org->org_acronym,
                    'logo_url' => $rawLogo ? $org->settings->logo_url : null,
                    'unread_count' => (int) $org->unread_count,
                    'last_message' => $lastMessage
                        ? ($lastMessage->message ?: ($lastMessage->attachment_path ? '[Attachment]' : null))
                        : null,
                    'last_message_time' => $lastMessage ? $lastMessage->created_at?->toIso8601String() : null,
                    'last_message_from_osas' => $lastMessage ? $lastMessage->sender_type === 'osas' : null,
                    'has_more' => $messagesByOrg->get($org->id, collect())->count() > $messages->count(),
                    'oldest_id' => $messages->first()?->id,
                    'messages' => $messages->map(fn ($msg) => $this->withAttachmentMeta($msg)),
                ];
            })->values(),
        ]);
    }

    /**
     * Aggregate unread count for the OSAS floating chat badge.
     */
    public function unreadCount()
    {
        $count = AdminChat::allOrganizations()
            ->unreadForOsas()
            ->count();

        return response()->json(['count' => $count]);
    }

    /** Maximum upload size for chat attachments (KB). */
    private const MAX_ATTACHMENT_KB = 20480;

    /**
     * Send Message (shared by OSAS and organizations).
     */
    public function sendMessage(Request $request)
    {
        $isOsas = auth()->user()->role === 'osas_admin';

        try {
            $request->validate([
                'message' => 'nullable|string|max:2000',
                'attachment' => [
                    'nullable',
                    'file',
                    'max:'.self::MAX_ATTACHMENT_KB,
                    function ($attribute, $value, $fail) {
                        if (!$value) {
                            return;
                        }
                        // A non-file value (e.g. a string field or an upload that
                        // PHP dropped) must never reach the file-type checks.
                        if (!$value instanceof \Illuminate\Http\UploadedFile) {
                            $fail('The attachment must be a file.');
                            return;
                        }
                        // MIME-based ban; some servers misreport office documents as
                        // application/octet-stream, so only video MIME is rejected here.
                        if (str_starts_with((string) $value->getMimeType(), 'video/')) {
                            $fail('Video files are not allowed.');
                            return;
                        }
                        // Extension backstop for when the MIME is misreported entirely,
                        // plus a hard ban on executable/scripting uploads.
                        if (in_array(strtolower($value->getClientOriginalExtension()), [
                            ...self::VIDEO_EXTENSIONS,
                            ...self::UNSAFE_EXTENSIONS,
                        ], true)) {
                            $fail('This file type is not allowed.');
                        }
                    },
                ],
                'organization_ids' => $isOsas ? 'required|array|min:1' : 'nullable',
                'organization_ids.*' => 'exists:organizations,id',
                'organization_id' => $isOsas ? 'nullable' : 'nullable|exists:organizations,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $attachmentField = $request->all()['attachment'] ?? null;
            \Log::error('Chat send validation failed', [
                'errors' => $e->errors(),
                'user_id' => auth()->id(),
                'role' => auth()->user()->role,
                'has_message' => $request->filled('message'),
                'has_attachment' => $request->hasFile('attachment'),
                'attachment_field_type' => is_object($attachmentField) ? get_class($attachmentField) : gettype($attachmentField),
                'attachment_field_value' => is_scalar($attachmentField) ? substr((string) $attachmentField, 0, 120) : null,
                'attachment_valid' => $request->file('attachment')?->isValid(),
                'attachment_size' => $request->file('attachment')?->getSize(),
                'attachment_mime' => $request->file('attachment')?->getMimeType(),
                'attachment_ext' => $request->file('attachment')?->getClientOriginalExtension(),
                'upload_error_code' => $request->file('attachment')?->getError(),
                'content_type' => $request->header('Content-Type'),
            ]);

            throw $e;
        }

        if (!$request->filled('message') && !$request->hasFile('attachment')) {
            return response()->json(['success' => false, 'error' => 'Message or attachment is required.'], 422);
        }

        $messageText = trim((string) $request->message);
        $attachment = $request->file('attachment');

        if ($isOsas) {
            // OSAS can target one or many organizations (broadcast).
            $organizationIds = array_map('intval', array_unique($request->organization_ids));
            $chats = [];

            foreach ($organizationIds as $organizationId) {
                $chats[] = AdminChat::create(array_merge([
                    'channel' => 'osas',
                    'organization_id' => $organizationId,
                    'user_id' => auth()->id(),
                    'message' => $messageText,
                    'sender_type' => 'osas',
                    'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
                ], $this->attachmentColumns($attachment)));
            }

            // No database notifications for OSAS sends - the organization
            // admins see them through the floating chat badge instead.

            $chat = $chats[0]->load('sender');
            $chat->recipient_count = count($chats);

            return response()->json([
                'success' => true,
                'message' => $this->withAttachmentMeta($chat),
            ]);
        }

        // Organization side: force the sender's own organization.
        $organizationId = session('current_organization_id')
            ?: (auth()->user()->organizations()->first()?->id ?? null);

        if (!$organizationId) {
            return response()->json([
                'success' => false,
                'error' => 'No organization selected.',
            ], 422);
        }

        $chat = AdminChat::create(array_merge([
            'channel' => 'osas',
            'organization_id' => $organizationId,
            'user_id' => auth()->id(),
            'message' => $messageText,
            'sender_type' => 'org',
            'is_read' => \DB::raw('CAST(0 AS BOOLEAN)'),
        ], $this->attachmentColumns($attachment)));

        // Notify OSAS admins about the new message.
        try {
            $osasAdmins = User::where('role', 'osas_admin')->get();
            foreach ($osasAdmins as $admin) {
                $admin->notify(new NewOsasChatNotification($chat));
            }
        } catch (\Exception $e) {
            \Log::error('Chat Notification Error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $this->withAttachmentMeta($chat->load('sender')),
        ]);
    }

    /**
     * Get New Messages (Polling).
     */
    public function getMessages(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'last_id' => 'required|integer|min:0',
        ]);

        $isOsas = auth()->user()->role === 'osas_admin';

        $organizationId = (int) $request->organization_id;

        if (!$isOsas) {
            $userOrgId = session('current_organization_id')
                ?: (auth()->user()->organizations()->first()?->id ?? null);

            if ($organizationId !== (int) $userOrgId) {
                return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $messages = AdminChat::allOrganizations()
            ->osasThread($organizationId)
            ->where('id', '>', $request->last_id)
            ->with('sender')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($msg) => $this->withAttachmentMeta($msg));

        // Mark the counterpart's messages as read.
        if ($messages->count() > 0) {
            AdminChat::allOrganizations()
                ->osasThread($organizationId)
                ->where('sender_type', $isOsas ? 'org' : 'osas')
                ->whereRaw('CAST(is_read AS INTEGER) = ?', [0])
                ->update(['is_read' => \DB::raw('CAST(1 AS BOOLEAN)')]);
        }

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    /**
     * Get Older Messages (History Pagination).
     */
    public function getHistory(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'before_id' => 'required|integer',
        ]);

        $messages = AdminChat::allOrganizations()
            ->osasThread($request->organization_id)
            ->where('id', '<', $request->before_id)
            ->latest('id')
            ->limit(self::PAGE_SIZE)
            ->with('sender')
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($msg) => $this->withAttachmentMeta($msg));

        $remaining = AdminChat::allOrganizations()
            ->osasThread($request->organization_id)
            ->where('id', '<', $request->before_id)
            ->count();

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'has_more' => $remaining > $messages->count(),
        ]);
    }

    /**
     * Download / Inline a chat attachment (auth-protected).
     */
    public function downloadAttachment(AdminChat $chat)
    {
        if (!$chat->attachment_path || !Storage::disk(storage_disk())->exists($chat->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        $disk = Storage::disk(storage_disk());
        $path = $chat->attachment_path;
        $name = $chat->attachment_name ?: basename($path);

        if (self::pathIsImage($path)) {
            return $disk->response(
                $path,
                $name,
                ['Content-Type' => $disk->mimeType($path) ?? 'application/octet-stream']
            );
        }

        return $disk->download($path, $name);
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
     * Attach presentation meta (url / is_image / file_type) to a chat message
     * for JSON output. Everything is derived from the stored path.
     */
    private function withAttachmentMeta(AdminChat $chat)
    {
        if ($chat->attachment_path) {
            $route = auth()->user()->role === 'osas_admin' ? 'osas.chat.attachment' : 'admin.chat.attachment';
            $chat->attachment_url = route($route, $chat->id);
            $chat->is_image = self::pathIsImage($chat->attachment_path);
            $chat->file_type = self::pathExtension($chat->attachment_path);
        }

        return $chat;
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
