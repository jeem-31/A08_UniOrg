<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Services\OsasNotificationService;
use App\Services\OsasProposalQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class OsasNotificationController extends Controller
{
    public function __construct(
        private readonly OsasNotificationService $notifications,
        private readonly OsasProposalQueueService $proposalQueue,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $counts = $this->notifications->counts($user);

        return view('osas.notifications.index', [
            'notifications' => $this->notifications->paginate($user, $request),
            'totalCount' => $counts->total,
            'unreadCount' => $counts->unread,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $state = $this->notifications->realtimeState($request->user(), $request);

        return response()
            ->json([
                'success' => true,
                ...$state,
                'pending_proposals_count' => $this->proposalQueue->pendingCount(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        try {
            $result = $this->notifications->markRead($request->user(), $notification);

            if (! $result->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'read_at' => $result->readAt?->toISOString(),
                'unread_count' => $result->unreadCount,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update notification read state.',
            ], 500);
        }
    }

    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $result = $this->notifications->markAllRead($request->user());

            return response()->json([
                'success' => $result->success,
                'unread_count' => $result->unreadCount,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update notification read state.',
            ], 500);
        }
    }
}
