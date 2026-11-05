<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeaders;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'active.account' => \App\Http\Middleware\EnsureActiveAccount::class,
            'auth.activity' => \App\Http\Middleware\RecordFailedLogin::class,
            'turnstile' => \App\Http\Middleware\VerifyTurnstile::class,
            'org.permission' => \App\Http\Middleware\CheckOrganizationPermission::class,
            'module' => \App\Http\Middleware\CheckModuleEnabled::class,
            'check.org.access' => \App\Http\Middleware\CheckOrganizationAccess::class,
            'check.mother.org' => \App\Http\Middleware\CheckMotherOrg::class,
            'comelec.election.only' => \App\Http\Middleware\ComelecOnlyElection::class,
        ]);
        
        // Add PreventBackHistory to web middleware group
        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistory::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response) {
            return app(SecurityHeaders::class)->apply($response, request());
        });
    })->create();
