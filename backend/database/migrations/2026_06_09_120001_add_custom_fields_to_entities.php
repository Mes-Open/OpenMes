<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `custom_fields` JSON column to every table in the custom-field
 * entity registry (config/custom_fields.php). Driven by the registry so the
 * column set and the registry can never drift. Idempotent and guarded so it's
 * safe to re-run as the registry grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'custom_fields')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->json('custom_fields')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'custom_fields')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('custom_fields');
                });
            }
        }
    }

    /** @return string[] */
    private function tables(): array
    {
        return collect(config('custom_fields.entities', []))
            ->pluck('table')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};
