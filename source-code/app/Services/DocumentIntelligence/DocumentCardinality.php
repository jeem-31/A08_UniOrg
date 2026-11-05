<?php

namespace App\Services\DocumentIntelligence;

enum DocumentCardinality: string
{
    case Scalar = 'scalar';
    case MultiFile = 'multi_file';
}
