<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('system_settings')) {
            return;
        }

        $row = DB::table('system_settings')->where('key', 'modules_enabled')->first();
        if (! $row) {
            return;
        }

        $enabled = json_decode($row->value, true) ?? [];
        $filtered = array_values(array_filter($enabled, fn ($name) => $name !== 'Packaging'));

        if ($filtered === $enabled) {
            return;
        }

        DB::table('system_settings')
            ->where('key', 'modules_enabled')
            ->update(['value' => json_encode($filtered)]);
    }

    public function down(): void
    {
        // No-op — Packaging is now part of core and cannot meaningfully be
        // re-enabled as a module. Reverting this migration would only re-add
        // a stale entry that ModuleManager would silently ignore anyway.
    }
};
