<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Outgoing webhook endpoints (#20). An endpoint subscribes to one or more
 * domain events (see WebhookEventRegistry) and receives an HMAC-signed HTTP
 * POST when any of them fire. The signing secret is stored encrypted at the
 * application layer (Webhook::$casts) and is never exposed through the sync
 * read-path (excluded from the Electric shape).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('url', 2048);
            // HMAC signing secret — encrypted via the model cast.
            $table->text('secret');
            // Subscribed event keys (WebhookEventRegistry).
            $table->json('events');
            // Optional static headers sent with every delivery.
            $table->json('headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index('is_active');
        });

        // Name is unique among live (non-deleted) rows so it can be reused after
        // a soft delete. Partial indexes are supported by PostgreSQL (prod) and
        // SQLite (tests); MySQL is not a target for this project.
        DB::statement('CREATE UNIQUE INDEX webhooks_name_unique ON webhooks (name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
