<?php

namespace App\Console\Commands;

use App\Events\AnnouncementUpdated;
use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Console\Command;
use Carbon\Carbon;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish scheduled announcements and events when their scheduled time arrives';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // Publish scheduled announcements
        $announcements = Announcement::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($announcements as $announcement) {
            $announcement->update([
                'status' => 'active',
                'is_published' => \DB::raw('TRUE'),
                'published_at' => now(),
            ]);

            try {
                broadcast(new AnnouncementUpdated($announcement));
            } catch (\Exception $e) {
                \Log::error('Scheduled announcement broadcast failed for ID ' . $announcement->id . ': ' . $e->getMessage());
            }

            $this->info("Published announcement: {$announcement->title}");
        }

        // Publish scheduled events
        $events = Event::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($events as $event) {
            $event->update([
                'status' => 'active',
                'is_published' => \DB::raw('TRUE'),
                'published_at' => now(),
            ]);
            $this->info("Published event: {$event->title}");
        }

        $total = $announcements->count() + $events->count();
        $this->info("Total posts published: {$total}");

        return 0;
    }
}
