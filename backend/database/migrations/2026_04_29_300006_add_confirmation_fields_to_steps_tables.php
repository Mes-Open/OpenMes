<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->integer('min_duration_minutes')->nullable()->after('estimated_duration_minutes');
            $table->boolean('requires_confirmation')->default(false)->after('min_duration_minutes');
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('completed_at');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_steps', function (Blueprint $table) {
            $table->dropColumn(['min_duration_minutes', 'requires_confirmation']);
        });

        Schema::table('batch_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });
    }
};
