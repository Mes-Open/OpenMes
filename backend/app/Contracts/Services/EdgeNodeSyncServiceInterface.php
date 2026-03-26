<?php

namespace App\Contracts\Services;

interface EdgeNodeSyncServiceInterface
{
    public function sync(string $cloudUrl, string $apiKey): array;
}
