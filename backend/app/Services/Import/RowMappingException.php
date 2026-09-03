<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * A file row that could not be turned into an importer row — a value the field
 * type rejects, before the entity importer ever sees it. Carries the field so
 * the error lands in the same {row, field, message} shape the importers report.
 */
class RowMappingException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
