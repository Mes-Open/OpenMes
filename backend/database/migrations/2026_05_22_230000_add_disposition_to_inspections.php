<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('disposition', 30)->default('pending')->after('status');
            $table->text('disposition_notes')->nullable()->after('disposition');
            $table->foreignId('disposition_by_id')->nullable()->after('disposition_notes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('disposition_at')->nullable()->after('disposition_by_id');
            $table->index('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropForeign(['disposition_by_id']);
            $table->dropColumn(['disposition', 'disposition_notes', 'disposition_by_id', 'disposition_at']);
        });
    }
};
