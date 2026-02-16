<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->text('description')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        // Insert default settings
        DB::table('system_settings')->insert([
            [
                'key' => 'allow_overproduction',
                'value' => json_encode(false),
                'description' => 'Allow produced_qty to exceed planned_qty',
            ],
            [
                'key' => 'default_token_ttl_minutes',
                'value' => json_encode(15),
                'description' => 'Access token expiration time in minutes',
            ],
            [
                'key' => 'force_sequential_steps',
                'value' => json_encode(true),
                'description' => 'Require steps to be completed in order',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
