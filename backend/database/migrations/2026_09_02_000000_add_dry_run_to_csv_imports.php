<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('csv_imports', function (Blueprint $table): void {
            // A validation-only run: the file is read, mapped and fed to the
            // importer exactly as a real run, then rolled back. Kept on the row
            // so the history can label it and nobody mistakes it for a write.
            $table->boolean('dry_run')->default(false)->after('import_strategy');
        });
    }

    public function down(): void
    {
        Schema::table('csv_imports', function (Blueprint $table): void {
            $table->dropColumn('dry_run');
        });
    }
};
