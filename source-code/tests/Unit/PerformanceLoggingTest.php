<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Services\OrganizationPermissionService;
use App\Services\PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PerformanceLoggingTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(OrganizationPermissionService::class);
    }

    /** @test */
    public function it_logs_cache_miss_on_first_access()
    {
        // Arrange
        $user = User::factory()->create(['role' => 'admin']);
        $organization = Organization::factory()->create();
        
        $user->organizations()->attach($organization->id, [
            'role' => 'Secretary',
            'membership_status' => 'Active',
        ]);

        // Clear cache to ensure miss
        Cache::flush();

        // Expect performance log for cache miss
        Log::shouldReceive('channel')
            ->with('performance')
            ->once()
            ->andReturnSelf();
        
        Log::shouldReceive('info')
            ->with('Permission cache', \Mockery::on(function ($data) use ($user, $organization) {
                return $data['user_id'] === $user->id
                    && $data['organization_id'] === $organization->id
                    && $data['cache_hit'] === false
                    && $data['position'] === 'Secretary';
            }))
            ->once();

        // Act
        $position = $this->service->getPosition($user->id, $organization->id);

        // Assert
        $this->assertEquals('Secretary', $position);
    }

    /** @test */
    public function it_logs_cache_hit_on_subsequent_access()
    {
        // Arrange
        $user = User::factory()->create(['role' => 'admin']);
        $organization = Organization::factory()->create();
        
        $user->organizations()->attach($organization->id, [
            'role' => 'Treasurer',
            'membership_status' => 'Active',
        ]);

        // Warm the cache
        Cache::flush();
        $this->service->getPosition($user->id, $organization->id);

        // Expect performance log for cache hit
        Log::shouldReceive('channel')
            ->with('performance')
            ->once()
            ->andReturnSelf();
        
        Log::shouldReceive('info')
            ->with('Permission cache', \Mockery::on(function ($data) use ($user, $organization) {
                return $data['user_id'] === $user->id
                    && $data['organization_id'] === $organization->id
                    && $data['cache_hit'] === true
                    && $data['position'] === 'Treasurer';
            }))
            ->once();

        // Act
        $position = $this->service->getPosition($user->id, $organization->id);

        // Assert
        $this->assertEquals('Treasurer', $position);
    }

    /** @test */
    public function it_logs_authorization_check_duration()
    {
        // Arrange
        $user = User::factory()->create(['role' => 'admin']);
        $organization = Organization::factory()->create();
        
        $user->organizations()->attach($organization->id, [
            'role' => 'President',
            'membership_status' => 'Active',
        ]);
        OrganizationSettings::create(['organization_id' => $organization->id]);

        Cache::flush();

        // Expect performance logs
        Log::shouldReceive('channel')
            ->with('performance')
            ->twice() // Once for cache, once for authorization
            ->andReturnSelf();
        
        // Cache log
        Log::shouldReceive('info')
            ->with('Permission cache', \Mockery::any())
            ->once();

        // Authorization check log
        Log::shouldReceive('info')
            ->with('Authorization check', \Mockery::on(function ($data) use ($user, $organization) {
                return $data['user_id'] === $user->id
                    && $data['organization_id'] === $organization->id
                    && $data['module'] === 'announcements'
                    && $data['required_level'] === 'full'
                    && $data['granted'] === true
                    && isset($data['duration_ms'])
                    && is_numeric($data['duration_ms']);
            }))
            ->once();

        // Act
        $hasAccess = $this->service->hasAccess($user->id, $organization->id, 'announcements', 'full');

        // Assert
        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function it_logs_denied_authorization_check()
    {
        // Arrange
        $user = User::factory()->create(['role' => 'admin']);
        $organization = Organization::factory()->create();
        
        $user->organizations()->attach($organization->id, [
            'role' => 'Auditor',
            'membership_status' => 'Active',
        ]);
        OrganizationSettings::create(['organization_id' => $organization->id]);

        Cache::flush();

        // Expect performance logs
        Log::shouldReceive('channel')
            ->with('performance')
            ->twice()
            ->andReturnSelf();
        
        // Cache log
        Log::shouldReceive('info')
            ->with('Permission cache', \Mockery::any())
            ->once();

        // Authorization check log - denied
        Log::shouldReceive('info')
            ->with('Authorization check', \Mockery::on(function ($data) use ($user, $organization) {
                return $data['user_id'] === $user->id
                    && $data['organization_id'] === $organization->id
                    && $data['module'] === 'payments'
                    && $data['required_level'] === 'full'
                    && $data['granted'] === false
                    && isset($data['duration_ms']);
            }))
            ->once();

        // Act
        $hasAccess = $this->service->hasAccess($user->id, $organization->id, 'payments', 'full');

        // Assert
        $this->assertFalse($hasAccess);
    }
}
