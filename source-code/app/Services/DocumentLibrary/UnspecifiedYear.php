<?php

namespace App\Services\DocumentLibrary;

enum UnspecifiedYear: string
{
    case Value = 'unspecified';

    public const LABEL = 'Unspecified Year';

    public function key(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return self::LABEL;
    }
}
