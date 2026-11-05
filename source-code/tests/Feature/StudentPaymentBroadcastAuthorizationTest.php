<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrganizationPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RealBroadcastAuth;
use Tests\TestCase;

class StudentPaymentBroadcastAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use RealBroadcastAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useRealBroadcastAuth();
    }

    public function test_student_payment_channel_only_authorizes_the_owning_student(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post('/broadcasting/auth', $this->channelPayload('private-student-payments.'.$student->id))
            ->assertOk();

        $this->actingAs($otherStudent)
            ->post('/broadcasting/auth', $this->channelPayload('private-student-payments.'.$student->id))
            ->assertForbidden();
    }

    public function test_organization_payment_channel_requires_payment_read_access(): void
    {
        $organizationId = 73;
        $authorizedAdmin = User::factory()->create(['role' => 'admin']);
        $unauthorizedAdmin = User::factory()->create(['role' => 'admin']);

        $this->mock(OrganizationPermissionService::class, function ($mock) use ($authorizedAdmin, $unauthorizedAdmin, $organizationId) {
            $mock->shouldReceive('hasAccess')
                ->with($authorizedAdmin->id, $organizationId, 'payments', 'read')
                ->once()
                ->andReturnTrue();
            $mock->shouldReceive('hasAccess')
                ->with($unauthorizedAdmin->id, $organizationId, 'payments', 'read')
                ->once()
                ->andReturnFalse();
        });

        $channel = 'private-organization-payments.'.$organizationId;

        $this->actingAs($authorizedAdmin)
            ->post('/broadcasting/auth', $this->channelPayload($channel))
            ->assertOk();

        $this->actingAs($unauthorizedAdmin)
            ->post('/broadcasting/auth', $this->channelPayload($channel))
            ->assertForbidden();
    }

    /** @return array<string, string> */
    private function channelPayload(string $channelName): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => $channelName,
        ];
    }
}
