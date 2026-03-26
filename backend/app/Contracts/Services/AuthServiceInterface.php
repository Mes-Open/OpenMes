<?php

namespace App\Contracts\Services;

interface AuthServiceInterface
{
    public function login(string $username, string $password): array;
    public function logout(\App\Models\User $user): void;
    public function me(\App\Models\User $user): \App\Models\User;
}
