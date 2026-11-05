<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StudentAnnouncementFragmentTest extends TestCase
{
    public function test_announcement_list_is_renderable_as_a_replaceable_fragment(): void
    {
        $announcements = new LengthAwarePaginator(
            new Collection(),
            0,
            10,
            1,
            ['path' => '/announcements']
        );

        $html = view('components.announcement-list', [
            'announcements' => $announcements,
            'currentOrgId' => 42,
        ])->render();

        $this->assertStringContainsString('data-student-announcement-list', $html);
        $this->assertStringContainsString('No announcements available at the moment', $html);
    }

    public function test_student_navigation_fragment_keeps_the_full_announcement_page(): void
    {
        request()->query->set('fragment', '1');

        try {
            $html = view('student.announcements.index', [
                'announcements' => new LengthAwarePaginator(
                    new Collection(),
                    0,
                    10,
                    1,
                    ['path' => '/announcements']
                ),
                'currentOrgId' => 42,
                'orgAcronym' => 'TEST',
                'visibleOrganizationIds' => [42],
            ])->render();
        } finally {
            request()->query->remove('fragment');
        }

        $this->assertStringContainsString('<main', $html);
        $this->assertStringContainsString('data-student-announcement-list', $html);
        $this->assertStringContainsString("searchParams.set('announcement_list', '1')", $html);
    }
}
