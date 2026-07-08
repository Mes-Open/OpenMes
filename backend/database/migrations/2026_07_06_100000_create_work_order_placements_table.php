<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra schedule segments beyond the order's primary placement (which
        // stays on work_orders.line_id/due_date/... so everything outside the
        // planner keeps working unchanged). Each row is one coarse segment on
        // one line — an order can hop across any number of lines over time.
        Schema::create('work_order_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->timestamp('due_date');
            $table->smallInteger('shift_number')->nullable();
            $table->date('end_date')->nullable();
            $table->smallInteger('end_shift_number')->nullable();
            $table->timestamps();
            $table->index(['work_order_id']);
            $table->index(['line_id', 'due_date']);
        });

        // The single-secondary columns generalise into rows here.
        DB::table('work_orders')
            ->whereNotNull('secondary_line_id')
            ->orderBy('id')
            ->each(function ($wo) {
                $due = $wo->secondary_due_date ?? $wo->due_date;
                if ($due === null) {
                    return; // an undated placement can't be represented — skip
                }
                DB::table('work_order_placements')->insert([
                    'work_order_id' => $wo->id,
                    'line_id' => $wo->secondary_line_id,
                    'due_date' => $due,
                    'shift_number' => $wo->secondary_shift_number ?? $wo->shift_number,
                    'end_date' => $wo->secondary_end_date,
                    'end_shift_number' => $wo->secondary_end_shift_number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secondary_line_id');
            $table->dropColumn([
                'secondary_due_date',
                'secondary_shift_number',
                'secondary_end_date',
                'secondary_end_shift_number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('secondary_line_id')->nullable()->after('line_id')->constrained('lines')->nullOnDelete();
            $table->timestamp('secondary_due_date')->nullable()->after('secondary_line_id');
            $table->smallInteger('secondary_shift_number')->nullable()->after('secondary_due_date');
            $table->date('secondary_end_date')->nullable()->after('secondary_shift_number');
            $table->smallInteger('secondary_end_shift_number')->nullable()->after('secondary_end_date');
        });

        // Best effort: only the first extra placement fits back into columns.
        DB::table('work_order_placements')
            ->orderBy('work_order_id')
            ->orderBy('id')
            ->get()
            ->groupBy('work_order_id')
            ->each(function ($rows, $workOrderId) {
                $p = $rows->first();
                DB::table('work_orders')->where('id', $workOrderId)->update([
                    'secondary_line_id' => $p->line_id,
                    'secondary_due_date' => $p->due_date,
                    'secondary_shift_number' => $p->shift_number,
                    'secondary_end_date' => $p->end_date,
                    'secondary_end_shift_number' => $p->end_shift_number,
                ]);
            });

        Schema::dropIfExists('work_order_placements');
    }
};
