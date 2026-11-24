<?php

namespace Database\Seeders;

use App\Models\CoaDocument;
use App\Models\CoaVerificationToken;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Public verification links for the two issued demo COAs. The seeder reuses
 * active tokens if present, so re-seeding does not rotate the demo URL.
 */
class CoaVerificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Auth::login($admin);

        CoaDocument::query()
            ->where('status', CoaDocument::STATUS_ISSUED)
            ->orderBy('id')
            ->each(function (CoaDocument $document) {
                CoaVerificationToken::ensureFor($document);
            });

        $this->command?->info('COA verification demo seeded: '.CoaVerificationToken::query()->whereNull('revoked_at')->count().' active links');
    }
}
