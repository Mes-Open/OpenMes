<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISA-95 Personnel — extend worker_skills pivot with certification metadata.
 *
 * The pivot historically tracks a coarse 1/2/3 proficiency `level`. ISA-95
 * Personnel Capability requires a richer notion of a certified competency:
 *  - cert_level: who can perform / supervise / train,
 *  - certified_from/until: validity window (nullable until = never expires),
 *  - certified_by_id: who issued the cert (link to users),
 *  - cert_notes: free-text remarks.
 *
 * The original `level` column is preserved to keep existing data untouched.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('worker_skills', function (Blueprint $table) {
            $table->enum('cert_level', ['trainee', 'operator', 'expert', 'trainer'])
                ->default('operator')
                ->after('skill_id');
            $table->date('certified_from')->nullable()->after('cert_level');
            $table->date('certified_until')->nullable()->after('certified_from');
            $table->foreignId('certified_by_id')->nullable()->after('certified_until')
                ->constrained('users')->nullOnDelete();
            $table->text('cert_notes')->nullable()->after('certified_by_id');

            // Pivot had no timestamps — add them so syncWithoutDetaching with
            // ->withTimestamps() keeps audit information.
            $table->timestamps();

            $table->index(['skill_id', 'cert_level']);
            $table->index('certified_until');
        });
    }

    public function down(): void
    {
        Schema::table('worker_skills', function (Blueprint $table) {
            $table->dropIndex(['skill_id', 'cert_level']);
            $table->dropIndex(['certified_until']);
            $table->dropForeign(['certified_by_id']);
            $table->dropColumn([
                'cert_level',
                'certified_from',
                'certified_until',
                'certified_by_id',
                'cert_notes',
                'created_at',
                'updated_at',
            ]);
        });
    }
};
