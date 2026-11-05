<?php

namespace App\Services\DocumentLibrary;

final class LibraryUrlBuilder
{
    /** @param array<string, string> $path */
    public function build(LibraryQuery $query, array $path = []): string
    {
        $parameters = array_merge($query->toCanonicalParameters(), $path);
        $base = url('/osas/smart-search');

        return $parameters === []
            ? $base
            : $base.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
