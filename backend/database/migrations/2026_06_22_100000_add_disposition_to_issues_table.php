<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-conformance disposition workflow (#11). Extends an issue (the
 * non-conformance record) with a disposition decision, the non-conforming
 * quantity, root-cause / containment narrative and a responsibility-source
 * classification.
 *
 * Note: `nc_source` (internal/external/supplier — who is responsible) is a
 * separate axis from the existing `source` column (inbound_inspection /
 * in_process / customer_complaint — where the issue originated), which is set
 * by InboundInspectionService and QualityTriggerService and stays untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // pending | scrap | rework | return_to_supplier | use_as_is
            $table->string('disposition', 20)->default('pending')->after('status');
            $table->decimal('non_conforming_qty', 12, 2)->nullable()->after('disposition');
            $table->text('root_cause')->nullable()->after('non_conforming_qty');
            $table->text('containment_action')->nullable()->after('root_cause');
            // internal | external | supplier
            $table->string('nc_source', 20)->nullable()->after('containment_action');
            $table->foreignId('disposition_by_id')->nullable()->after('nc_source')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('disposition_at')->nullable()->after('disposition_by_id');

            $table->index('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropForeign(['disposition_by_id']);
            $table->dropIndex(['disposition']);
            $table->dropColumn([
                'disposition',
                'non_conforming_qty',
                'root_cause',
                'containment_action',
                'nc_source',
                'disposition_by_id',
                'disposition_at',
            ]);
        });
    }
};
