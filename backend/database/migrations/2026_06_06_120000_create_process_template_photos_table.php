<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_template_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained()->cascadeOnDelete();
            // Client filename is metadata ONLY — never used on the filesystem.
            $table->string('original_name', 255);
            // Random hash name under a private disk path.
            $table->string('storage_path', 255);
            // Set from the server-side re-encode, never from the client.
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('caption', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['process_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_template_photos');
    }
};
