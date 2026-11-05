<?php

namespace Tests\Feature;

use App\Http\Controllers\ApplyOrgController;
use App\Http\Controllers\Osas\DocumentTestingController;
use App\Http\Controllers\Osas\OsasController;
use App\Http\Controllers\Osas\SmartSearchController;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class DocumentIntelligenceRoutesTest extends TestCase
{
    public function test_document_library_preserves_smart_search_and_full_case_routes(): void
    {
        $library = Route::getRoutes()->getByName('osas.smart-search');
        $fullCase = Route::getRoutes()->getByName('osas.apply-org.show');

        self::assertNotNull($library);
        self::assertSame('osas/smart-search', $library->uri());
        self::assertSame(SmartSearchController::class.'@index', $library->getActionName());
        self::assertContains('GET', $library->methods());
        self::assertContains('auth', $library->gatherMiddleware());
        self::assertContains('role:osas_admin', $library->gatherMiddleware());

        self::assertNotNull($fullCase);
        self::assertSame('osas/apply-org/{applyOrg}', $fullCase->uri());
        self::assertSame(OsasController::class.'@showApplyOrg', $fullCase->getActionName());
        self::assertContains('GET', $fullCase->methods());
        self::assertContains('auth', $fullCase->gatherMiddleware());
        self::assertContains('role:osas_admin', $fullCase->gatherMiddleware());
    }

    public function test_document_library_registers_no_mutating_endpoints(): void
    {
        $libraryRoutes = collect(Route::getRoutes())
            ->filter(static fn ($route): bool => str_contains((string) $route->getName(), 'library-documents'))
            ->values();

        self::assertCount(1, $libraryRoutes);
        self::assertSame('osas.apply-org.library-documents.source', $libraryRoutes->first()->getName());
        self::assertSame([], array_values(array_intersect(
            ['POST', 'PUT', 'PATCH', 'DELETE'],
            $libraryRoutes->first()->methods(),
        )));
    }

    public function test_osas_sidebar_labels_the_active_destination_as_document_library(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/osas.blade.php'));

        self::assertStringContainsString('<!-- Document Library -->', $layout);
        self::assertStringContainsString("route('osas.smart-search')", $layout);
        self::assertStringContainsString("request()->routeIs('osas.smart-search')", $layout);
        self::assertStringContainsString('<span class="nav-text">Document Library</span>', $layout);
        self::assertStringNotContainsString('<span class="nav-text">Smart Search</span>', $layout);
    }

    public function test_nested_analysis_routes_are_registered_with_scoping_and_authorization(): void
    {
        // 4 routes remain scoped + can; source is union (live+archived) and is handled inside controller without scope/can
        $scopedRoutes = [
            'osas.apply-org.analyses.status' => ['GET', 'can:view,analysis,applyOrg'],
            'osas.apply-org.analyses.summary' => ['GET', 'can:viewSummary,analysis,applyOrg'],
            'osas.apply-org.analyses.details' => ['GET', 'can:viewDetails,analysis,applyOrg'],
            'osas.apply-org.analyses.retry' => ['POST', 'can:retry,analysis,applyOrg'],
            'osas.apply-org.analyses.resummarize' => ['POST', 'can:resummarize,analysis,applyOrg'],
        ];

        foreach ($scopedRoutes as $name => [$method, $ability]) {
            $route = Route::getRoutes()->getByName($name);
            self::assertNotNull($route, "Missing route {$name}");
            self::assertSame('osas/apply-org/{applyOrg}/analyses/{analysis}'.($name === 'osas.apply-org.analyses.status' ? '' : '/'.str($name)->afterLast('.')), $route->uri());
            self::assertContains($method, $route->methods());
            self::assertTrue($route->enforcesScopedBindings());
            self::assertContains('auth', $route->gatherMiddleware());
            self::assertContains('role:osas_admin', $route->gatherMiddleware());
            self::assertContains($ability, $route->gatherMiddleware());
        }

        // Union source routes (live+archived) intentionally have no scoped bindings / can — controller handles both
        $unionSourceRoutes = [
            'osas.apply-org.analyses.source',
            'osas.apply-org.library-documents.source',
        ];
        foreach ($unionSourceRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);
            self::assertNotNull($route, "Missing route {$name}");
            self::assertContains('GET', $route->methods());
            self::assertFalse($route->enforcesScopedBindings());
            self::assertContains('auth', $route->gatherMiddleware());
            self::assertContains('role:osas_admin', $route->gatherMiddleware());
            self::assertNotContains('can:viewSource,analysis,applyOrg', $route->gatherMiddleware());
            self::assertNotContains('can:viewSource,document,applyOrg', $route->gatherMiddleware());
        }
    }

    public function test_nested_library_source_route_is_registered_with_scoping_and_authorization(): void
    {
        $route = Route::getRoutes()->getByName('osas.apply-org.library-documents.source');

        self::assertNotNull($route);
        self::assertSame('osas/apply-org/{applyOrg}/library-documents/{document}/source', $route->uri());
        self::assertContains('GET', $route->methods());
        // Union route: no scoped bindings / can — handled inside controller for live+archived
        self::assertFalse($route->enforcesScopedBindings());
        self::assertContains('auth', $route->gatherMiddleware());
        self::assertContains('role:osas_admin', $route->gatherMiddleware());
        self::assertNotContains('can:viewSource,document,applyOrg', $route->gatherMiddleware());
    }

    public function test_manual_review_processing_routes_are_removed_but_document_lab_remains(): void
    {
        self::assertFalse(Route::has('osas.apply-org.process-document'));
        self::assertFalse(Route::has('osas.apply-org.process-all-documents'));
        self::assertFalse(Route::has('osas.apply-org.get-processed-document'));

        $documentLab = Route::getRoutes()->getByName('osas.document-testing');
        $documentLabProcess = Route::getRoutes()->getByName('osas.document-testing.process');

        self::assertSame(DocumentTestingController::class.'@show', $documentLab->getActionName());
        self::assertContains('GET', $documentLab->methods());
        self::assertSame(DocumentTestingController::class.'@process', $documentLabProcess->getActionName());
        self::assertNotContains('GET', $documentLabProcess->methods());
        self::assertContains('POST', $documentLabProcess->methods());
    }

    public function test_isolated_document_lab_has_no_application_review_entry_points(): void
    {
        $controller = new ReflectionClass(DocumentTestingController::class);
        $publicMethods = collect($controller->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === DocumentTestingController::class)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        self::assertSame(['process', 'show'], $publicMethods);
        self::assertSame('osas.document-testing', (new DocumentTestingController)->show()->name());

        $source = file_get_contents($controller->getFileName());
        self::assertDoesNotMatchRegularExpression(
            '/ApplyOrg|ProcessedDocument|DocumentAnalysisRegistrar|ProcessDocumentAnalysis|dispatch\s*\(/',
            $source,
        );
    }

    public function test_no_get_route_invokes_document_ocr_ai_or_dispatch_actions(): void
    {
        $forbiddenActions = [
            DocumentTestingController::class.'@process',
            'App\\Http\\Controllers\\Osas\\DocumentIntelligenceController@retry',
            'App\\Http\\Controllers\\Osas\\DocumentIntelligenceController@resummarize',
        ];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            self::assertNotContains($route->getActionName(), $forbiddenActions, $route->uri());
        }

        $uris = collect(Route::getRoutes())->map->uri()->all();
        self::assertNotContains('test-google-cloud-vision', $uris);
        self::assertNotContains('test-google-cloud-nlp', $uris);
    }

    public function test_approval_and_rejection_do_not_depend_on_or_dispatch_document_processing(): void
    {
        foreach (['approve', 'reject'] as $method) {
            $source = $this->methodSource(ApplyOrgController::class, $method);
            self::assertDoesNotMatchRegularExpression(
                '/(?<!ProcessApprovedApplication)::dispatch\s*\(|ProcessedDocument|processing_status|output_source_version|DocumentAnalysis|StudentListProcessor/',
                $source,
                "{$method} must remain independent of document intelligence.",
            );
        }
    }

    public function test_production_ai_http_clients_do_not_disable_tls_verification(): void
    {
        foreach ([app_path('Services/GeminiService.php')] as $path) {
            $source = file_get_contents($path);
            self::assertDoesNotMatchRegularExpression(
                '/[\'\"]verify[\'\"]\s*=>\s*false|withoutVerifying\s*\(/i',
                $source,
                "TLS verification is disabled in {$path}",
            );
        }
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }
}
