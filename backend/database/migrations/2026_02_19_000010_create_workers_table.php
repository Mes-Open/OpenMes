<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            // Constrained only when the table it points at exists. These lookups
            // now ship as an optional module, so a community installation never
            // creates them — the column stays, unconstrained, holding nothing.
            $crew = $table->foreignId('crew_id')->nullable();
            if (Schema::hasTable('crews')) {
                $crew->constrained()->nullOnDelete();
            }
            $wage = $table->foreignId('wage_group_id')->nullable();
            if (Schema::hasTable('wage_groups')) {
                $wage->constrained()->nullOnDelete();
            }
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
