<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\Line;
use Illuminate\Support\Facades\Gate;

class FactorySecurityService
{
    /**
     * Define the shop-floor security gates.
     */
    public function defineGates(): void
    {
        Gate::define('manage-production', function (User $user) {
            return $user->hasAnyRole(['Admin', 'Supervisor']);
        });

        Gate::define('operate-line', function (User $user, Line $line) {
            if ($user->hasAnyRole(['Admin', 'Supervisor'])) {
                return true;
            }

            // Operators can only work on assigned lines
            return $user->lines->contains($line->id);
        });

        Gate::define('view-oee', function (User $user) {
            return $user->hasAnyRole(['Admin', 'Supervisor', 'Engineer']);
        });

        Gate::define('edit-process-template', function (User $user) {
            return $user->hasAnyRole(['Admin', 'Engineer']);
        });
    }

    /**
     * Get the production context for the current user.
     */
    public function getProductionContext(User $user): array
    {
        return [
            'assigned_lines' => $user->lines->pluck('id')->toArray(),
            'is_shop_floor_operator' => $user->hasRole('Operator'),
            'can_report_downtime' => true, // Standard for all shop-floor users
        ];
    }
}
