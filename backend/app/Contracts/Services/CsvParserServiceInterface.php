<?php

namespace App\Contracts\Services;

interface CsvParserServiceInterface
{
    public function parse(string $filePath, int $previewRows = 5): array;
    public function storeTemporary($file): string;
    public function getFullPath(string $storagePath): string;
    public function validateMapping(array $headers, array $mapping): array;
    public function parseWithMapping(string $filePath, array $mapping): array;
    public function cleanupTemporary(string $filePath): void;
}
