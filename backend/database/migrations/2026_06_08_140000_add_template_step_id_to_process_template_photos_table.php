<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_template_photos', function (Blueprint $table) {
            // A photo can be tied to one specific production step (one photo per
            // step). NULL = a general template-level photo (the existing
            // gallery). nullOnDelete so removing a step doesn't delete the photo
            // row out from under the model's disk-cleanup event.
            $table->foreignId('template_step_id')->nullable()->after('process_template_id')
                ->constrained('template_steps')->nullOnDelete();

            $table->index('template_step_id');
        });
    }

    public function down(): void
    {
        Schema::table('process_template_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_step_id');
        });
    }
};
