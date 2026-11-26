<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $current = DB::table('system_settings')->where('key', 'default_currency')->value('value');
        $decoded = $current !== null ? json_decode((string) $current, true) : null;
        if ($current === null || $decoded === 'PLN' || $current === 'PLN') {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'default_currency'],
                ['value' => json_encode('CNY')],
            );
        }
    }

    public function down(): void
    {
        // Keep the operator's chosen currency; there is no safe previous value.
    }
};
