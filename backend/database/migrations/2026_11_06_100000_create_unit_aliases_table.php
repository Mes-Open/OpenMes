<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unit-of-measure aliases — the dictionary ERP imports use to turn the unit
 * names an ERP sends ("Units", "szt.", "Stück", "KGM") into the unit OpenMES
 * stores ("pcs", "kg"). `alias` keeps what the admin typed for display;
 * `alias_normalized` is the lookup key (trimmed, lower-cased, trailing dot
 * dropped — see App\Support\Units\UnitNormalizer::key()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 20);
            $table->string('alias_normalized', 20);
            $table->string('unit', 20);

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Partial unique so an alias frees up again after a soft delete.
        // COALESCE keeps single-tenant installs (tenant_id NULL) covered — a
        // plain unique would treat every NULL tenant as distinct.
        DB::statement(
            'CREATE UNIQUE INDEX unit_aliases_alias_unique
             ON unit_aliases (alias_normalized, COALESCE(tenant_id, 0))
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_aliases');
    }
};
