<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\WorkOrder;
use App\Services\Warehouse\StockDocumentService;
use App\Support\Integration\StockDocumentPresenter;
use App\Support\Integration\WorkOrderPresenter;
use App\Support\TenantContext;
use App\Support\WebhookEventRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Domain events fanned out to webhooks: the full work-order payload (ERP
 * export shape), work_order.created / progress (throttled), stock_document.posted,
 * quality_issue.created, after-commit dispatch and tenant isolation.
 */
class WebhookEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['openmmes.webhooks.progress_throttle_seconds' => 60]);
    }

    /** @return \Illuminate\Support\Collection<int, WebhookDelivery> */
    private function deliveriesFor(Webhook $webhook, ?string $event = null)
    {
        return WebhookDelivery::where('webhook_id', $webhook->id)
            ->when($event, fn ($q) => $q->where('event_type', $event))
            ->orderBy('id')
            ->get();
    }

    public function test_status_changed_payload_contains_codes_and_dates(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED])->create();

        $line = Line::factory()->create(['code' => 'LINE-A']);
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $product->id,
            'customer_order_no' => 'PO-4711',
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 100,
        ]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'completed_at' => now()]);

        $deliveries = $this->deliveriesFor($webhook, WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED);
        $this->assertCount(1, $deliveries);
        $data = $deliveries->first()->payload['data'];

        $this->assertSame($wo->id, $data['id']);
        $this->assertSame($wo->order_no, $data['order_no']);
        $this->assertSame('PO-4711', $data['customer_order_no']);
        $this->assertSame(WorkOrder::STATUS_DONE, $data['status']);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $data['previous_status']);
        $this->assertSame('LINE-A', $data['line_code']);
        $this->assertSame('BREAD-01', $data['product_type_code']);
        $this->assertNotNull($data['completed_at']);
        $this->assertNotNull($data['updated_at']);
        $this->assertEquals(100, $data['planned_qty']);
        // Kept for receivers built against the old id-only payload.
        $this->assertSame($line->id, $data['line_id']);
        $this->assertSame($product->id, $data['product_type_id']);

        // Same fields as the ERP production export — one source of truth.
        $exportKeys = array_keys(WorkOrderPresenter::present($wo->fresh(['line', 'productType'])));
        $this->assertEmpty(array_diff($exportKeys, array_keys($data)));
    }

    public function test_work_order_created_fires_only_for_subscribers(): void
    {
        $subscribed = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_CREATED])->create();
        $other = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED])->create();

        $wo = WorkOrder::factory()->create();

        $deliveries = $this->deliveriesFor($subscribed);
        $this->assertCount(1, $deliveries);
        $this->assertSame(WebhookEventRegistry::WORK_ORDER_CREATED, $deliveries->first()->event_type);
        $this->assertSame($wo->id, $deliveries->first()->payload['data']['id']);
        $this->assertNull($deliveries->first()->payload['data']['previous_status']);
        $this->assertCount(0, $this->deliveriesFor($other));
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_progress_fires_on_produced_qty_change(): void
    {
        $subscribed = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_PROGRESS])->create();
        $other = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_CREATED])->create();

        $wo = WorkOrder::factory()->create(['produced_qty' => 0]);
        $wo->update(['produced_qty' => 12]);

        $deliveries = $this->deliveriesFor($subscribed, WebhookEventRegistry::WORK_ORDER_PROGRESS);
        $this->assertCount(1, $deliveries);
        $data = $deliveries->first()->payload['data'];
        $this->assertEquals(12, $data['produced_qty']);
        $this->assertEquals(0, $data['previous_produced_qty']);
        // Status did not change in this update.
        $this->assertNull($data['previous_status']);
        $this->assertCount(0, $this->deliveriesFor($other, WebhookEventRegistry::WORK_ORDER_PROGRESS));
    }

    public function test_progress_is_throttled_per_work_order(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_PROGRESS])->create();
        $wo = WorkOrder::factory()->create(['produced_qty' => 0]);
        $otherWo = WorkOrder::factory()->create(['produced_qty' => 0]);

        $wo->update(['produced_qty' => 1]);
        $wo->update(['produced_qty' => 2]);
        $wo->update(['produced_qty' => 3]);
        $this->assertCount(1, $this->deliveriesFor($webhook));

        // A different order has its own window.
        $otherWo->update(['produced_qty' => 5]);
        $this->assertCount(2, $this->deliveriesFor($webhook));

        $this->travel(61)->seconds();
        $wo->update(['produced_qty' => 4]);

        $deliveries = $this->deliveriesFor($webhook);
        $this->assertCount(3, $deliveries);
        $this->assertEquals(4, $deliveries->last()->payload['data']['produced_qty']);
    }

    public function test_progress_throttle_zero_sends_every_change(): void
    {
        config(['openmmes.webhooks.progress_throttle_seconds' => 0]);
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_PROGRESS])->create();
        $wo = WorkOrder::factory()->create(['produced_qty' => 0]);

        $wo->update(['produced_qty' => 1]);
        $wo->update(['produced_qty' => 2]);

        $this->assertCount(2, $this->deliveriesFor($webhook));
    }

    public function test_progress_throttle_not_consumed_without_subscribers(): void
    {
        $wo = WorkOrder::factory()->create(['produced_qty' => 0]);
        $wo->update(['produced_qty' => 1]);

        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_PROGRESS])->create();
        $wo->update(['produced_qty' => 2]);

        $this->assertCount(1, $this->deliveriesFor($webhook));
    }

    public function test_status_only_update_does_not_fire_progress(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_PROGRESS])->create();
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->assertCount(0, $this->deliveriesFor($webhook));
    }

    public function test_stock_document_posted_fires_with_export_payload(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::STOCK_DOCUMENT_POSTED])->create();
        $other = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_CREATED])->create();

        $service = app(StockDocumentService::class);
        $warehouse = Warehouse::factory()->finishedGoods()->create(['code' => 'FG-1']);
        $product = ProductType::factory()->create(['code' => 'BREAD-01']);

        $document = $service->createDraft([
            'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
            'warehouse_id' => $warehouse->id,
            'lines' => [['product_type_id' => $product->id, 'quantity' => 240]],
        ]);
        // A draft is not a stock movement yet.
        $this->assertCount(0, $this->deliveriesFor($webhook));

        $service->post($document);

        $deliveries = $this->deliveriesFor($webhook, WebhookEventRegistry::STOCK_DOCUMENT_POSTED);
        $this->assertCount(1, $deliveries);
        $data = $deliveries->first()->payload['data'];
        $this->assertSame($document->document_no, $data['document_no']);
        $this->assertSame(StockDocument::STATUS_POSTED, $data['status']);
        $this->assertSame('FG-1', $data['warehouse_code']);
        $this->assertSame('BREAD-01', $data['lines'][0]['product_type_code']);
        $this->assertEquals(240, $data['lines'][0]['quantity']);
        $this->assertNotNull($data['posted_at']);

        $expected = json_decode(json_encode(
            StockDocumentPresenter::present($document->fresh(['warehouse', 'workOrder', 'lines.material', 'lines.productType', 'lines.materialLot'])),
        ), true);
        $this->assertSame($expected, $data);
        $this->assertCount(0, $this->deliveriesFor($other));

        // Cancelling is not a post.
        $service->cancel($document->fresh());
        $this->assertCount(1, $this->deliveriesFor($webhook));
    }

    public function test_quality_issue_created_fires_with_export_payload(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::QUALITY_ISSUE_CREATED])->create();
        $legacy = Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create();

        $wo = WorkOrder::factory()->create();
        $type = IssueType::factory()->create(['name' => 'Seal defect', 'severity' => 'HIGH']);
        Issue::factory()->create(['work_order_id' => $wo->id, 'issue_type_id' => $type->id, 'non_conforming_qty' => 3]);

        $deliveries = $this->deliveriesFor($webhook, WebhookEventRegistry::QUALITY_ISSUE_CREATED);
        $this->assertCount(1, $deliveries);
        $data = $deliveries->first()->payload['data'];
        $this->assertSame($wo->order_no, $data['work_order_no']);
        $this->assertSame('Seal defect', $data['issue_type']);
        $this->assertSame('HIGH', $data['severity']);
        $this->assertEquals(3, $data['non_conforming_qty']);
        $this->assertCount(1, $this->deliveriesFor($legacy, WebhookEventRegistry::ISSUE_CREATED));
    }

    public function test_issue_without_work_order_fires_no_quality_issue_event(): void
    {
        $webhook = Webhook::factory()->subscribedTo([
            WebhookEventRegistry::QUALITY_ISSUE_CREATED,
            WebhookEventRegistry::ISSUE_CREATED,
        ])->create();

        Issue::factory()->create(['work_order_id' => null]);

        $this->assertCount(0, $this->deliveriesFor($webhook, WebhookEventRegistry::QUALITY_ISSUE_CREATED));
        $this->assertCount(1, $this->deliveriesFor($webhook, WebhookEventRegistry::ISSUE_CREATED));
    }

    public function test_events_do_not_leak_between_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $all = WebhookEventRegistry::keys();

        $hookA = Webhook::factory()->subscribedTo($all)->create(['tenant_id' => $tenantA->id]);
        $hookB = Webhook::factory()->subscribedTo($all)->create(['tenant_id' => $tenantB->id]);
        $hookGlobal = Webhook::factory()->subscribedTo($all)->create(['tenant_id' => null]);

        // No logged-in user and no TenantContext — the machine/daemon/queue case,
        // where TenantScope filters nothing.
        $wo = WorkOrder::factory()->create(['tenant_id' => $tenantA->id, 'status' => WorkOrder::STATUS_PENDING]);
        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS, 'produced_qty' => 5]);
        Issue::factory()->create(['work_order_id' => $wo->id]);

        $service = app(StockDocumentService::class);
        $warehouse = Warehouse::factory()->finishedGoods()->create(['tenant_id' => $tenantA->id]);
        $product = ProductType::factory()->create();
        $document = $service->createDraft([
            'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
            'warehouse_id' => $warehouse->id,
            'tenant_id' => $tenantA->id,
            'lines' => [['product_type_id' => $product->id, 'quantity' => 10]],
        ]);
        $service->post($document);

        $eventsA = $this->deliveriesFor($hookA)->pluck('event_type')->unique()->sort()->values()->all();
        $this->assertSame([
            WebhookEventRegistry::ISSUE_CREATED,
            WebhookEventRegistry::QUALITY_ISSUE_CREATED,
            WebhookEventRegistry::STOCK_DOCUMENT_POSTED,
            WebhookEventRegistry::WORK_ORDER_CREATED,
            WebhookEventRegistry::WORK_ORDER_PROGRESS,
            WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED,
        ], $eventsA);
        $this->assertCount(0, $this->deliveriesFor($hookB));
        $this->assertCount(0, $this->deliveriesFor($hookGlobal));

        // A tenant-less entity reaches only tenant-less endpoints.
        WorkOrder::factory()->create(['tenant_id' => null]);
        $this->assertCount(1, $this->deliveriesFor($hookGlobal, WebhookEventRegistry::WORK_ORDER_CREATED));
        $this->assertCount(1, $this->deliveriesFor($hookA, WebhookEventRegistry::WORK_ORDER_CREATED));
        $this->assertCount(0, $this->deliveriesFor($hookB));
    }

    public function test_rolled_back_transaction_sends_nothing(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_STATUS_CHANGED])->create();
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        try {
            DB::transaction(function () use ($wo) {
                $wo->update(['status' => WorkOrder::STATUS_DONE]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertCount(0, $this->deliveriesFor($webhook));
        Bus::assertNotDispatched(DeliverWebhookJob::class);
    }

    public function test_registry_labels_are_translated(): void
    {
        app()->setLocale('pl');

        $labels = collect(WebhookEventRegistry::forForm())->pluck('label', 'key');

        $this->assertSame('Postęp zlecenia', $labels[WebhookEventRegistry::WORK_ORDER_PROGRESS]);
        $this->assertSame('Dokument magazynowy zaksięgowany', $labels[WebhookEventRegistry::STOCK_DOCUMENT_POSTED]);
    }

    public function test_progress_carries_previous_status_when_status_changed_in_same_update(): void
    {
        $webhook = Webhook::factory()->subscribedTo([WebhookEventRegistry::WORK_ORDER_PROGRESS])->create();
        $wo = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS, 'produced_qty' => 0]);

        $wo->update(['status' => WorkOrder::STATUS_DONE, 'produced_qty' => 10]);

        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $this->deliveriesFor($webhook)->first()->payload['data']['previous_status']);
    }

    public function test_issue_without_work_order_reaches_reporters_tenant_only(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $hookA = Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create(['tenant_id' => $tenantA->id]);
        $hookB = Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create(['tenant_id' => $tenantB->id]);

        $reporter = User::factory()->create(['tenant_id' => $tenantA->id]);
        Issue::factory()->create(['work_order_id' => null, 'reported_by_id' => $reporter->id]);

        $this->assertCount(1, $this->deliveriesFor($hookA, WebhookEventRegistry::ISSUE_CREATED));
        $this->assertCount(0, $this->deliveriesFor($hookB));
    }

    public function test_issue_without_work_order_or_reporter_uses_tenant_context(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $hookA = Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create(['tenant_id' => $tenantA->id]);
        $hookB = Webhook::factory()->subscribedTo([WebhookEventRegistry::ISSUE_CREATED])->create(['tenant_id' => $tenantB->id]);

        // Headless (API-key) write: no user, tenant from TenantContext.
        app(TenantContext::class)->set($tenantA->id);
        try {
            Issue::factory()->create(['work_order_id' => null, 'reported_by_id' => null]);
        } finally {
            app(TenantContext::class)->clear();
        }

        $this->assertCount(1, $this->deliveriesFor($hookA, WebhookEventRegistry::ISSUE_CREATED));
        $this->assertCount(0, $this->deliveriesFor($hookB));
    }
}
