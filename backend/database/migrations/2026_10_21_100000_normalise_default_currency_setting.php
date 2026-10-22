<?php

use App\Support\CurrencyRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One currency per installation, from the ISO 4217 allowlist.
 *
 * Fresh installs get PLN. Existing installs keep their stored code, uppercased;
 * a value the allowlist does not know (the old rule only checked `size:3`)
 * becomes PLN so every screen formats with a real currency. Idempotent, touches
 * one row, never deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => CurrencyRegistry::SETTING_KEY,
            'value' => json_encode(CurrencyRegistry::DEFAULT),
            'description' => 'Installation currency (ISO 4217) for every pay rate and production cost amount.',
        ]);

        $raw = DB::table('system_settings')->where('key', CurrencyRegistry::SETTING_KEY)->value('value');
        $decoded = json_decode((string) $raw, true);
        $code = CurrencyRegistry::normalise(is_string($decoded) ? $decoded : (string) $raw);

        if (! CurrencyRegistry::isValid($code)) {
            $code = CurrencyRegistry::DEFAULT;
        }

        DB::table('system_settings')
            ->where('key', CurrencyRegistry::SETTING_KEY)
            ->update(['value' => json_encode($code)]);

        CurrencyRegistry::forget();
    }

    public function down(): void
    {
        // Data normalisation — the previous (invalid) value is not recoverable.
    }
};
