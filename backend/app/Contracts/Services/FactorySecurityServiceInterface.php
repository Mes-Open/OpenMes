<?php

namespace App\Contracts\Services;

interface FactorySecurityServiceInterface
{
    public function defineGates(): void;
    public function getProductionContext(\App\Models\User $user): array;
}
