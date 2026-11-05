<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Broadcast;

trait RealBroadcastAuth
{
    protected function useRealBroadcastAuth(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'testing-key',
            'broadcasting.connections.pusher.secret' => 'testing-secret',
            'broadcasting.connections.pusher.app_id' => 'testing-app',
            'broadcasting.connections.pusher.options' => [
                'cluster' => 'mt1',
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);

        Broadcast::forgetDrivers();

        require base_path('routes/channels.php');
    }
}
