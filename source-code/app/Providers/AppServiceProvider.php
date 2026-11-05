<?php

namespace App\Providers;

use App\Http\View\Composers\AdminNavigationComposer;
use App\Http\View\Composers\OsasNotificationComposer;
use App\Mail\Transport\GmailApiTransport;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\LibraryResource;
use App\Models\OrganizationUser;
use App\Observers\ContentCacheObserver;
use App\Observers\OrganizationUserObserver;
use App\Policies\OsasAccountPolicy;
use App\Services\DocumentIntelligence\AIOutputValidator;
use App\Services\DocumentIntelligence\Contracts\AIOutputValidator as AIOutputValidatorContract;
use App\Services\DocumentIntelligence\Contracts\DocumentIntelligenceProvider as DocumentIntelligenceProviderContract;
use App\Services\DocumentIntelligence\Contracts\FailureSanitizer as FailureSanitizerContract;
use App\Services\DocumentIntelligence\Contracts\SourceDocumentReader as SourceDocumentReaderContract;
use App\Services\DocumentIntelligence\Contracts\SourceTextExtractor as SourceTextExtractorContract;
use App\Services\DocumentIntelligence\FailureSanitizer;
use App\Services\DocumentIntelligence\GoogleCloudSourceTextExtractor;
use App\Services\DocumentIntelligence\GoogleDocumentIntelligenceProvider;
use App\Services\DocumentIntelligence\StorageSourceDocumentReader;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIOutputValidatorContract::class, AIOutputValidator::class);
        $this->app->singleton(FailureSanitizerContract::class, FailureSanitizer::class);
        $this->app->singleton(SourceDocumentReaderContract::class, StorageSourceDocumentReader::class);
        $this->app->singleton(SourceTextExtractorContract::class, GoogleCloudSourceTextExtractor::class);
        $this->app->singleton(DocumentIntelligenceProviderContract::class, GoogleDocumentIntelligenceProvider::class);
    }

    public function boot(): void
    {
        // Prohibit destructive database commands
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (! $this->app->environment('local', 'testing')) {
            $this->app->instance(
                CacheRateLimiter::class,
                new CacheRateLimiter($this->app['cache']->store('database'))
            );
        }

        RateLimiter::for('login', function (Request $request) {
            $studentId = $request->input('student_id');
            $studentId = is_string($studentId) ? mb_strtolower(trim($studentId)) : '';
            $ip = trim((string) $request->header('CF-Connecting-IP'));

            if ($ip === '') {
                $ip = (string) $request->ip();
            }

            $response = function (Request $request, array $headers) {
                app(\App\Services\AuthenticationActivity::class)->record('login.throttled', $request);

                return response()->json([
                    'success' => false,
                    'errors' => ['student_id' => ['Too many login attempts. Please try again later.']],
                ], 429, $headers);
            };

            return [
                Limit::perMinute(5)->by('login:'.$studentId)->response($response),
                Limit::perHour(20)->by('login-ip:'.$ip)->response($response),
            ];
        });

        foreach (['password-email' => 3, 'password-reset' => 5] as $name => $attempts) {
            RateLimiter::for($name, function (Request $request) use ($name, $attempts) {
                $email = $request->input('email');
                $email = is_string($email) ? mb_strtolower(trim($email)) : '';
                $response = function (Request $request, array $headers) use ($name) {
                    app(\App\Services\AuthenticationActivity::class)->record($name.'.throttled', $request, 'email');
                    $message = 'Too many requests. Please wait before trying again.';

                    return response()->json(['error' => $message, 'message' => $message], 429, $headers);
                };

                return [
                    Limit::perMinutes(15, $attempts)->by($name.':email:'.hash('sha256', $email))->response($response),
                    Limit::perHour(30)->by($name.':ip:'.$request->ip())->response($response),
                ];
            });
        }

        if ($this->app->runningInConsole()) {
            EventFacade::listen(CommandStarting::class, function (CommandStarting $event) {
                $blocked = [
                    'migrate:fresh',
                    'migrate:refresh',
                    'migrate:reset',
                    'migrate:rollback',
                    'db:wipe',
                ];

                if (in_array($event->command, $blocked, true) && ! $this->app->environment('testing')) {
                    $event->output?->writeln('<error>Command [' . $event->command . '] is blocked to prevent accidental database wipe/data loss.</error>');
                    exit(1);
                }
            });
        }

        Mail::extend('gmail_api', function () {
            return new GmailApiTransport();
        });

        // Force HTTPS in production
        if (config('app.env') === 'production') {
            \URL::forceScheme('https');

            // Trust Railway proxy
            $this->app['request']->server->set('HTTPS', 'on');

            // Fall back to the real public host when APP_URL is still a local
            // or LAN address so web-generated links point to the deployed site.
            // Queued notifications rendered by the queue worker still rely on
            // APP_URL being set to the public domain in the production env.
            $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $isLocalAddress = in_array($configuredHost, ['localhost', '127.0.0.1', '::1', null], true)
                || filter_var($configuredHost, FILTER_VALIDATE_IP);
            if ($isLocalAddress && ! $this->app->runningInConsole()) {
                \URL::forceRootUrl('https://' . $this->app['request']->getHttpHost());
            }
        }

        Schema::defaultStringLength(191);
        Gate::define(OsasAccountPolicy::ABILITY, [OsasAccountPolicy::class, 'manage']);

        // Register observers for scoped cache invalidation.
        OrganizationUser::observe(OrganizationUserObserver::class);
        Event::observe(ContentCacheObserver::class);
        Announcement::observe(ContentCacheObserver::class);
        LibraryResource::observe(ContentCacheObserver::class);
        // OPTIMIZATION: Register view composer for admin navigation
        // This caches all navigation permissions in session (one-time check per login)
        \View::composer('layouts.admin', AdminNavigationComposer::class);
        \View::composer('layouts.dean', AdminNavigationComposer::class);
        \View::composer('layouts.osas', OsasNotificationComposer::class);

        // Register RBAC Blade directives
        Blade::directive('canAccessModule', function ($module) {
            return "<?php if(app(\App\Services\PermissionHelper::class)->canAccessModule($module)): ?>";
        });

        Blade::directive('endCanAccessModule', function () {
            return '<?php endif; ?>';
        });

        Blade::directive('hasFullAccess', function ($module) {
            return "<?php if(app(\App\Services\PermissionHelper::class)->hasFullAccess($module)): ?>";
        });

        Blade::directive('endHasFullAccess', function () {
            return '<?php endif; ?>';
        });

        Blade::directive('hasReadOnly', function ($module) {
            return "<?php if(app(\App\Services\PermissionHelper::class)->hasReadOnly($module)): ?>";
        });

        Blade::directive('endHasReadOnly', function () {
            return '<?php endif; ?>';
        });
    }
}
