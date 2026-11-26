<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            // Links an inbound inspection to the warehouse receipt line that
            // created it. Unique so dispatching the same arrival twice cannot
            // enqueue two inspections for one delivery.
            $table->foreignId('stock_document_line_id')
                ->nullable()
                ->after('inspection_plan_id')
                ->constrained('stock_document_lines')
                ->nullOnDelete();
            $table->unique('stock_document_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropUnique(['stock_document_line_id']);
            $table->dropConstrainedForeignId('stock_document_line_id');
        });
    }
};
