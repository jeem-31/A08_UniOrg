<?php

namespace App\Services\DocumentLibrary;

use App\Models\User;

final class FolderPathResolver
{
    public function __construct(
        private OrganizationFolderToken $tokens,
        private AuthorizedFolderRepository $folders,
        private LibraryUrlBuilder $urls,
    ) {}

    public function resolve(
        ?string $folderToken,
        ?string $yearKey,
        LibraryQuery $query,
        User $user,
    ): FolderResolution {
        $rootUrl = $this->urls->build($query);

        if ($folderToken === null && $yearKey === null) {
            return new FolderResolution(
                'root',
                [new FolderBreadcrumb('Document Library', null, true)],
                null,
                null,
                null,
                $rootUrl,
                null,
            );
        }

        $identityKey = $this->tokens->verify($folderToken);
        $organization = $identityKey === null
            ? null
            : $this->folders->findOrganization($user, $identityKey, $query->archiveScope);

        if ($organization === null) {
            return $this->notFound($rootUrl);
        }

        $organizationUrl = $this->urls->build($query, ['folder' => $organization->token]);
        if ($yearKey === null) {
            return new FolderResolution(
                'organization',
                [
                    new FolderBreadcrumb('Document Library', $rootUrl, false),
                    new FolderBreadcrumb($organization->label, null, true),
                ],
                $organization,
                null,
                $rootUrl,
                $rootUrl,
                null,
            );
        }

        $year = $yearKey === UnspecifiedYear::Value->key()
            ? UnspecifiedYear::Value
            : AccreditationYear::parse($yearKey);

        if ($year === null || ! $this->folders->containsYear($organization, $year, $query->archiveScope)) {
            return $this->notFound($rootUrl);
        }

        return new FolderResolution(
            'year',
            [
                new FolderBreadcrumb('Document Library', $rootUrl, false),
                new FolderBreadcrumb($organization->label, $organizationUrl, false),
                new FolderBreadcrumb($year->label(), null, true),
            ],
            $organization,
            $year,
            $organizationUrl,
            $rootUrl,
            null,
        );
    }

    private function notFound(string $rootUrl): FolderResolution
    {
        return new FolderResolution(
            'not_found',
            [new FolderBreadcrumb('Document Library', $rootUrl, false)],
            null,
            null,
            null,
            $rootUrl,
            'The requested folder is unavailable. Return to the Document Library and try again.',
        );
    }
}
