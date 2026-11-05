<?php

namespace App\Http\View\Composers;

use App\Models\User;
use App\Services\OsasNotificationService;
use App\Services\OsasProposalQueueService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final readonly class OsasNotificationComposer
{
    public function __construct(
        private OsasNotificationService $notifications,
        private OsasProposalQueueService $proposalQueue,
        private Request $request,
    ) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();

        if (! $user instanceof User || $user->role !== 'osas_admin') {
            return;
        }

        $counts = $this->notifications->counts($user);

        $view->with([
            'osasDropdownNotifications' => $this->notifications->recent($user, $this->request, 10),
            'totalCount' => $counts->total,
            'unreadCount' => $counts->unread,
            'pendingProposalsCount' => $this->proposalQueue->pendingCount(),
        ]);
    }
}
