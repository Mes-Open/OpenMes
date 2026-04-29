<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('lot_number', 50)->nullable()->after('batch_number');
            $table->string('lot_assigned_at', 10)->nullable()->after('lot_number'); // on_start, on_release
            $table->foreignId('workstation_id')->nullable()->after('lot_assigned_at')->constrained()->nullOnDelete();
            $table->date('expiry_date')->nullable()->after('completed_at');
            $table->timestamp('released_at')->nullable()->after('expiry_date');
            $table->foreignId('released_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->string('release_type', 20)->nullable()->after('released_by'); // for_production, for_sale
            $table->string('udi_code', 100)->nullable()->after('release_type');
            $table->decimal('scrap_qty', 10, 2)->nullable()->after('udi_code');
            $table->text('notes')->nullable()->after('scrap_qty');

            $table->index('lot_number');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex(['lot_number']);
            $table->dropConstrainedForeignId('workstation_id');
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn([
                'lot_number', 'lot_assigned_at', 'expiry_date',
                'released_at', 'release_type', 'udi_code', 'scrap_qty', 'notes',
            ]);
        });
    }
};
