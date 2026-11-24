<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coa_documents', function (Blueprint $table) {
            $table->foreignId('inspector_id')->nullable()->after('prepared_by_id')->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable()->after('inspector_id');
        });
    }

    public function down(): void
    {
        Schema::table('coa_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspector_id');
            $table->dropColumn('inspected_at');
        });
    }
};
