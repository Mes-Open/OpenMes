<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['user', 'workstation'])->default('user')->after('email');
            $table->foreignId('workstation_id')->nullable()->after('account_type')->constrained()->onDelete('set null');
        });

        // Update existing users to 'user' type (already set by default)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['workstation_id']);
            $table->dropColumn(['account_type', 'workstation_id']);
        });
    }
};
