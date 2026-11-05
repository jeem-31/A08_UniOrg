<?php

namespace App\Services\DocumentIntelligence\Data;

use App\Services\DocumentIntelligence\StoredDocumentDescriptor;

interface ReadOnlyDocumentSource
{
    public function descriptor(): StoredDocumentDescriptor;

    /**
     * Return a fresh stream opened for reading only.
     *
     * @return resource
     */
    public function readStream();
}
