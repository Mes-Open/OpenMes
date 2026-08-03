<?php

namespace Tests\Feature\Sync;

use App\Events\CollectionChanged;
use App\Models\Webhook;
use App\Sync\CollectionBroadcaster;
use App\Sync\ShapeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * A Shape's column list is an allowlist ("never include password hashes, tokens,
 * PII"). The snapshot endpoint has always honoured it, but the delta broadcast
 * used to send whatever `attributesToArray()` returned — so any model attribute
 * outside the list reached every subscriber on the collection's private channel.
 *
 * That was harmless while every synced table was innocuous, and became a real
 * leak the moment `webhooks` was registered: its `headers` column holds the
 * receiving system's auth header and is deliberately absent from the shape.
 */
class CollectionBroadcastProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_row_never_exceeds_the_collections_allowlist(): void
    {
        $captured = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$captured) {
            $captured[] = $e;
        });

        Webhook::create([
            'name' => 'Projection probe',
            'url' => 'https://example.test/hook',
            'secret' => 'super-secret',
            'events' => ['work_order.created'],
            'headers' => ['Authorization' => 'Bearer LEAKED-IF-BROADCAST'],
            'is_active' => true,
        ]);

        $registry = app(ShapeRegistry::class);
        $seen = 0;

        foreach ($captured as $event) {
            $allowed = $registry->find($event->collection)?->columns();
            $this->assertNotNull($allowed, "No shape for broadcast collection [{$event->collection}].");

            $extra = array_diff(array_keys($event->row), $allowed);
            $this->assertSame([], $extra, "Collection [{$event->collection}] broadcast columns outside its allowlist.");
            $seen++;
        }

        $this->assertGreaterThan(0, $seen, 'Expected the webhook write to broadcast at least one delta.');
    }

    public function test_webhook_secret_and_auth_headers_are_never_broadcast(): void
    {
        $rows = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$rows) {
            if ($e->collection === 'webhooks') {
                $rows[] = $e->row;
            }
        });

        $webhook = Webhook::create([
            'name' => 'Secret probe',
            'url' => 'https://example.test/hook',
            'secret' => 'super-secret',
            'events' => ['work_order.created'],
            'headers' => ['Authorization' => 'Bearer LEAKED-IF-BROADCAST'],
            'is_active' => true,
        ]);
        $webhook->update(['name' => 'Renamed']);
        $webhook->delete();

        $this->assertNotEmpty($rows, 'Expected webhook writes to broadcast.');

        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('headers', $row);
            $this->assertArrayNotHasKey('secret', $row);
            // Still usable: the client keys upserts and deletes by id.
            $this->assertArrayHasKey('id', $row);
        }
    }

    public function test_flush_projects_too(): void
    {
        $rows = [];
        Event::listen(CollectionChanged::class, function (CollectionChanged $e) use (&$rows) {
            if ($e->collection === 'webhooks') {
                $rows[] = $e->row;
            }
        });

        $webhook = Webhook::withoutEvents(fn () => Webhook::create([
            'name' => 'Flush probe',
            'url' => 'https://example.test/hook',
            'secret' => 'super-secret',
            'events' => [],
            'headers' => ['Authorization' => 'Bearer LEAKED-IF-BROADCAST'],
            'is_active' => true,
        ]));

        // The manual path used after writes that bypass Eloquent events.
        CollectionBroadcaster::flush($webhook);

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('headers', $row);
            $this->assertArrayNotHasKey('secret', $row);
        }
    }
}
