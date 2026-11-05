<?php

namespace App\Services;

use App\Models\ApplyOrg;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use RuntimeException;
use Throwable;

final readonly class OsasNotificationDestinationResolver
{
    /** @var list<string> */
    private const UNAVAILABLE_ROUTES = [];

    public function __construct(
        private Router $router,
        private UrlGenerator $urlGenerator,
    ) {}

    public function resolve(DatabaseNotification $notification, Request $request): string
    {
        $fallback = $this->fallback($request);

        try {
            $reference = $notification->data['url'] ?? null;

            if (! is_string($reference) || $reference === '') {
                return $fallback;
            }

            $parsed = $this->parseReference($reference, $request);

            if ($parsed === null) {
                return $fallback;
            }

            [$path, $querySuffix, $fragmentSuffix] = $parsed;

            if ($path === '/osas/proposalstapos') {
                $path = '/osas/proposals';
            }

            if (! $this->isSafeOsasPath($path)) {
                return $fallback;
            }

            $route = $this->matchGetRoute($path.$querySuffix);

            if ($route === null || ! $this->isAuthorizedAvailableRoute($route)) {
                return $fallback;
            }

            if (! $this->trustedRecordExists($notification, $route)) {
                return $fallback;
            }

            return $request->getSchemeAndHttpHost().$path.$querySuffix.$fragmentSuffix;
        } catch (Throwable $exception) {
            $this->reportFailure($notification, $exception);

            return $fallback;
        }
    }

    /** @return array{string, string, string}|null */
    private function parseReference(string $reference, Request $request): ?array
    {
        if ($this->containsInvalidCharactersOrEncoding($reference)) {
            return null;
        }

        [$address, $querySuffix, $fragmentSuffix] = $this->splitSuffixes($reference);
        $scheme = strtolower($request->getScheme());
        $hasScheme = preg_match('/^([a-z][a-z0-9+.-]*):/i', $address, $schemeMatch) === 1;
        $isNetworkPath = str_starts_with($address, '//');

        if ($hasScheme || $isNetworkPath) {
            $absolute = $isNetworkPath ? $scheme.':'.$address : $address;
            $parts = parse_url($absolute);

            if (! is_array($parts)
                || isset($parts['user'])
                || isset($parts['pass'])
                || ! isset($parts['scheme'], $parts['host'])) {
                return null;
            }

            $referenceScheme = strtolower($parts['scheme']);

            if (! in_array($referenceScheme, ['http', 'https'], true)
                || ! $this->isCurrentOrigin($parts, $request)) {
                return null;
            }

            $path = $parts['path'] ?? '/';
        } else {
            if ($address === '' || str_starts_with($address, '\\')) {
                return null;
            }

            $path = str_starts_with($address, '/') ? $address : '/'.$address;
        }

        return [$path, $querySuffix, $fragmentSuffix];
    }

    private function containsInvalidCharactersOrEncoding(string $reference): bool
    {
        return preg_match('/[\x00-\x20\x7f\\\\]/', $reference) === 1
            || preg_match('/%(?![0-9a-f]{2})/i', $reference) === 1;
    }

    /** @return array{string, string, string} */
    private function splitSuffixes(string $reference): array
    {
        $fragmentSuffix = '';
        $fragmentPosition = strpos($reference, '#');

        if ($fragmentPosition !== false) {
            $fragmentSuffix = substr($reference, $fragmentPosition);
            $reference = substr($reference, 0, $fragmentPosition);
        }

        $querySuffix = '';
        $queryPosition = strpos($reference, '?');

        if ($queryPosition !== false) {
            $querySuffix = substr($reference, $queryPosition);
            $reference = substr($reference, 0, $queryPosition);
        }

        return [$reference, $querySuffix, $fragmentSuffix];
    }

    /** @param array<string, mixed> $parts */
    private function isCurrentOrigin(array $parts, Request $request): bool
    {
        $referenceScheme = strtolower((string) $parts['scheme']);
        $requestScheme = strtolower($request->getScheme());
        $referenceHost = strtolower((string) $parts['host']);
        $requestHost = strtolower($request->getHost());

        return $referenceScheme === $requestScheme
            && $referenceHost === $requestHost
            && $this->normalizedPort($referenceScheme, $parts['port'] ?? null)
                === $this->normalizedPort($requestScheme, $request->getPort());
    }

    private function normalizedPort(string $scheme, mixed $port): ?int
    {
        if ($port !== null) {
            return is_int($port) ? $port : null;
        }

        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }

    private function isSafeOsasPath(string $path): bool
    {
        if (! str_starts_with($path, '/')) {
            return false;
        }

        $decoded = $path;

        for ($iteration = 0; $iteration < 5; $iteration++) {
            if (preg_match('/%(?:25|2f|5c|2e)/i', $decoded) === 1) {
                return false;
            }

            $next = rawurldecode($decoded);

            if (preg_match('/[\x00-\x1f\x7f\\\\]/', $next) === 1) {
                return false;
            }

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return $decoded === '/osas' || str_starts_with($decoded, '/osas/');
    }

    private function matchGetRoute(string $uri): ?Route
    {
        try {
            return $this->router->getRoutes()->match(Request::create($uri, 'GET'));
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException|\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return null;
        }
    }

    private function isAuthorizedAvailableRoute(Route $route): bool
    {
        $name = $route->getName();

        if (! is_string($name)
            || ! str_starts_with($name, 'osas.')
            || in_array($name, self::UNAVAILABLE_ROUTES, true)) {
            return false;
        }

        $methods = $route->methods();

        if (! in_array('GET', $methods, true) && ! in_array('HEAD', $methods, true)) {
            return false;
        }

        $middleware = $route->gatherMiddleware();
        $hasAuth = in_array('auth', $middleware, true)
            || count(array_filter($middleware, fn (string $entry): bool => str_starts_with($entry, 'auth:'))) > 0;

        return $hasAuth && in_array('role:osas_admin', $middleware, true);
    }

    private function trustedRecordExists(DatabaseNotification $notification, Route $route): bool
    {
        if ($route->getName() !== 'osas.apply-org.show') {
            return true;
        }

        $data = $notification->data;
        $rawId = is_array($data) ? ($data['apply_org_id'] ?? null) : null;
        $applicationId = filter_var($rawId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($applicationId === false
            || (string) $route->parameter('applyOrg') !== (string) $applicationId) {
            return false;
        }

        return ApplyOrg::query()->whereKey($applicationId)->exists();
    }

    private function fallback(Request $request): string
    {
        try {
            $path = $this->urlGenerator->route('osas.notifications.index', [], false);
        } catch (Throwable $exception) {
            $this->reportSanitized('Unable to generate the OSAS notification fallback route.', $exception);
            $path = '/osas/notifications';
        }

        return $request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
    }

    private function reportFailure(DatabaseNotification $notification, Throwable $exception): void
    {
        $notificationId = (string) $notification->getKey();
        $safeId = preg_match('/^[a-z0-9-]{1,64}$/i', $notificationId) === 1
            ? $notificationId
            : 'unknown';

        $this->reportSanitized(
            "OSAS notification destination resolution failed for notification [{$safeId}].",
            $exception,
        );
    }

    private function reportSanitized(string $message, Throwable $exception): void
    {
        try {
            report(new RuntimeException($message.' Cause: '.$exception::class.'.'));
        } catch (Throwable) {
            // Reporting must never prevent deterministic fallback navigation.
        }
    }
}
