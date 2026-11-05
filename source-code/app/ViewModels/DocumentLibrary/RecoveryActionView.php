<?php

namespace App\ViewModels\DocumentLibrary;

final readonly class RecoveryActionView
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
