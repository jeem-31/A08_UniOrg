<?php

namespace App\Http\Controllers\Osas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DocumentLibrary\AuthorizedFilterOptions;
use App\Services\DocumentLibrary\DocumentLibraryQueryService;
use App\Services\DocumentLibrary\FolderPathResolver;
use App\Services\DocumentLibrary\LibraryQuery;
use App\Services\DocumentLibrary\LibraryQueryParser;
use App\ViewModels\DocumentLibrary\LibraryPageView;
use App\ViewModels\DocumentLibraryPresenter;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class SmartSearchController extends Controller
{
    public function __construct(
        private readonly DocumentLibraryQueryService $queries,
        private readonly LibraryQueryParser $parser,
        private readonly FolderPathResolver $folders,
        private readonly DocumentLibraryPresenter $presenter,
    ) {}

    public function index(Request $request): View|Response
    {
        $user = $request->user() ?? Auth::user();
        abort_unless($user instanceof User && $user->role === 'osas_admin', 403);

        $query = new LibraryQuery;
        $retryUrl = $request->fullUrl();

        try {
            [$library, $options, $status] = $this->withinReadSnapshot(
                function () use ($request, $user, &$query, &$retryUrl): array {
                    // Scope-aware options: parse archive_scope first so `current`
                    // never offers archived years/types and vice versa.
                    $options = $this->queries->authorizedFilterOptions($user, $this->preliminaryArchiveScope($request));
                    $parsed = $this->parser->parse($request, $options);
                    $query = $parsed->query;
                    $path = $this->folders->resolve(
                        $this->queryString($request, 'folder'),
                        $this->queryString($request, 'year_folder'),
                        $query,
                        $user,
                    );
                    if (! $path->found()) {
                        $library = $this->presenter->notFound($query);
                        $retryUrl = $library->canonicalUrl;

                        return [$library, $options, 404];
                    }

                    if ($query->keyword !== null) {
                        $page = $this->queries->searchPage($query, $user, $path);
                        $library = $this->presenter->page($page, $path, $parsed);
                    } elseif ($path->level === 'year') {
                        $page = $this->queries->folderPage($query, $path, $user);
                        $library = $this->presenter->page($page, $path, $parsed);
                    } elseif ($path->level === 'organization' && $path->organization !== null) {
                        $library = $this->presenter->hierarchyPage(
                            $this->queries->yearFolders($user, $query, $path->organization),
                            $path,
                            $query,
                            $parsed,
                        );
                    } else {
                        $library = $this->presenter->hierarchyPage(
                            $this->queries->organizationFolders($user, $query),
                            $path,
                            $query,
                            $parsed,
                        );
                    }

                    $retryUrl = $library->canonicalUrl;

                    return [$library, $options, 200];
                },
            );
        } catch (Throwable $exception) {
            $correlationId = (string) Str::uuid();
            Log::error('Document library request failed.', [
                'correlation_id' => $correlationId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_trace' => $exception->getTraceAsString(),
                'route' => $request->route()?->getName() ?? 'osas.smart-search',
                'user_id' => $user->getKey(),
                'query_params' => $request->query->all(),
            ]);

            $library = $this->presenter->retrievalFailure($query, $retryUrl);
            $options = new AuthorizedFilterOptions;
            $status = 503;
        }

        $viewData = $this->viewData($library, $options, $request, $user);

        return $status === 200
            ? view('osas.smart-search.index', $viewData)
            : response()->view('osas.smart-search.index', $viewData, $status);
    }

    /** @return array<string, mixed> */
    private function viewData(
        LibraryPageView $library,
        AuthorizedFilterOptions $options,
        Request $request,
        User $user,
    ): array {
        // The aliases keep the existing Blade renderable until the dedicated
        // folder-first template consumes the explicit library view model.
        $legacyDocuments = new LengthAwarePaginator(
            [],
            0,
            12,
            1,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return [
            'library' => $library,
            'filterOptions' => $options->all(),
            'filterLabels' => $options->labels(),
            'documents' => $legacyDocuments,
            'departments' => collect(),
            'osasUser' => $user,
        ];
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function preliminaryArchiveScope(Request $request): ?string
    {
        $value = $this->queryString($request, 'archive_scope');

        return match ($value) {
            'current', 'archive' => $value,
            default => null,
        };
    }

    private function withinReadSnapshot(Closure $callback): mixed
    {
        $connection = DB::connection();
        $outerTransactionLevel = $connection->transactionLevel();

        if ($outerTransactionLevel === 0 && $connection->getDriverName() === 'mysql') {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        return $connection->transaction(function () use (
            $callback,
            $connection,
            $outerTransactionLevel,
        ): mixed {
            if ($outerTransactionLevel === 0 && $connection->getDriverName() === 'pgsql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $callback();
        }, 1);
    }
}
