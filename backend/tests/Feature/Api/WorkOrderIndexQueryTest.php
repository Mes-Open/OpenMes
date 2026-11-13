<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Api\V1\ListWorkOrdersRequest;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Opt-in paging, search, due-date range and lean listing on
 * GET /api/v1/work-orders, and the guarantee that a request without the new
 * parameters still gets the legacy response.
 */
class WorkOrderIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingAsRole(?string $role = 'Admin'): User
    {
        $user = User::factory()->create();
        if ($role !== null) {
            $user->assignRole($role);
        }
        Sanctum::actingAs($user);

        return $user;
    }

    /** An order with neutral text fields, so search tests only match what they set. */
    private function order(array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'description' => 'plain order',
            'customer_order_no' => null,
        ], $attributes));
    }

    private function orderWithBatchSteps(array $attributes = []): WorkOrder
    {
        $order = $this->order($attributes);
        $batch = Batch::factory()->create(['work_order_id' => $order->id]);
        BatchStep::factory()->count(2)->create(['batch_id' => $batch->id]);

        return $order;
    }

    // ── Backward compatibility ──────────────────────────────────────────────

    public function test_default_response_is_identical_to_the_legacy_query(): void
    {
        $this->actingAsRole('Admin');
        $this->orderWithBatchSteps(['priority' => 1]);
        $this->orderWithBatchSteps(['priority' => 9]);
        $this->order(['priority' => 5]);

        // Exactly what the endpoint returned before paging existed.
        $legacy = WorkOrder::query()
            ->with(['line', 'productType', 'batches.steps'])
            ->byPriority()
            ->get();

        $response = $this->getJson('/api/v1/work-orders')->assertOk();

        $response->assertExactJson(['data' => json_decode($legacy->toJson(), true)]);
        $response->assertJsonMissingPath('meta');
        $response->assertJsonStructure(['data' => [['id', 'order_no', 'line', 'product_type', 'batches' => [['steps']]]]]);
        $this->assertCount(2, $response->json('data.0.batches.0.steps'));
        $this->assertSame([9, 5, 1], array_column($response->json('data'), 'priority'));
    }

    public function test_per_page_without_page_is_ignored_like_before(): void
    {
        // The mobile app sends per_page=200 (above the page-size cap) and expects
        // every order in one unpaginated response.
        $this->actingAsRole('Admin');
        $this->order();
        $this->order();
        $this->order();

        $this->getJson('/api/v1/work-orders?per_page=200')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('meta');

        $this->getJson('/api/v1/work-orders?per_page=1')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_status_list_filter_still_works(): void
    {
        $this->actingAsRole('Admin');
        $this->order(['status' => WorkOrder::STATUS_PENDING]);
        $this->order(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->order(['status' => WorkOrder::STATUS_DONE]);

        $response = $this->getJson('/api/v1/work-orders?status[]=PENDING&status[]=IN_PROGRESS')->assertOk();

        $this->assertEqualsCanonicalizing(
            [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_IN_PROGRESS],
            array_column($response->json('data'), 'status'),
        );
    }

    // ── Pagination ──────────────────────────────────────────────────────────

    public function test_page_returns_a_page_with_meta(): void
    {
        $this->actingAsRole('Admin');
        foreach (range(1, 5) as $priority) {
            $this->order(['priority' => $priority]);
        }

        $first = $this->getJson('/api/v1/work-orders?page=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 2, 'total' => 5, 'last_page' => 3]);
        $this->assertSame([5, 4], array_column($first->json('data'), 'priority'));

        $last = $this->getJson('/api/v1/work-orders?page=3&per_page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3);
        $this->assertSame([1], array_column($last->json('data'), 'priority'));
    }

    public function test_page_alone_uses_the_default_page_size(): void
    {
        $this->actingAsRole('Admin');
        $this->order();

        $this->getJson('/api/v1/work-orders?page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_paginated_items_keep_the_default_relations(): void
    {
        $this->actingAsRole('Admin');
        $this->orderWithBatchSteps();

        $this->getJson('/api/v1/work-orders?page=1')
            ->assertOk()
            ->assertJsonStructure(['data' => [['line', 'product_type', 'batches' => [['steps']]]], 'meta']);
    }

    public function test_page_size_and_page_are_bounded(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/work-orders?page=1&per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/work-orders?page=1&per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/work-orders?page=1&per_page=abc')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/work-orders?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/v1/work-orders?page=1000001')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/v1/work-orders?page=1000000')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/work-orders?page=1&per_page=100')->assertOk();
    }

    // ── Search ──────────────────────────────────────────────────────────────

    public function test_search_matches_order_number_case_insensitively(): void
    {
        $this->actingAsRole('Admin');
        $hit = $this->order(['order_no' => 'WO-ALPHA-77']);
        $this->order(['order_no' => 'WO-BETA-12']);

        $this->assertSearchFinds('alpha-7', $hit);
    }

    public function test_search_matches_customer_order_number(): void
    {
        $this->actingAsRole('Admin');
        $hit = $this->order(['customer_order_no' => 'PO-Kestrel-001']);
        $this->order(['customer_order_no' => 'PO-OTHER-002']);

        $this->assertSearchFinds('KESTREL', $hit);
    }

    public function test_search_matches_description(): void
    {
        $this->actingAsRole('Admin');
        $hit = $this->order(['description' => 'Rush job for the Zephyr account']);
        $this->order();

        $this->assertSearchFinds('zephyr', $hit);
    }

    public function test_search_matches_product_type_name(): void
    {
        $this->actingAsRole('Admin');
        $product = ProductType::factory()->create(['name' => 'Titanium Bracket']);
        $hit = $this->order(['product_type_id' => $product->id]);
        $this->order();

        $this->assertSearchFinds('titanium', $hit);
    }

    public function test_search_matches_product_type_code(): void
    {
        $this->actingAsRole('Admin');
        $product = ProductType::factory()->create(['code' => 'QX-9001']);
        $hit = $this->order(['product_type_id' => $product->id]);
        $this->order();

        $this->assertSearchFinds('qx-9001', $hit);
    }

    public function test_search_with_no_match_returns_an_empty_list(): void
    {
        $this->actingAsRole('Admin');
        $this->order();

        $this->getJson('/api/v1/work-orders?search=nothing-like-this')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_search_is_length_limited(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/work-orders?search='.str_repeat('a', 256))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_search_underscore_is_literal_not_a_wildcard(): void
    {
        $this->actingAsRole('Admin');
        $hit = $this->order(['order_no' => 'WO_1']);
        $this->order(['order_no' => 'WOX1']);

        $this->assertSearchFinds('WO_1', $hit);
    }

    public function test_search_percent_and_backslash_are_literal(): void
    {
        $this->actingAsRole('Admin');
        $percent = $this->order(['description' => 'Discount 50% applied']);
        $this->order(['description' => 'Batch of 500 units']);
        $backslash = $this->order(['description' => 'Path C:\\jobs\\7']);
        $this->order(['description' => 'Path C:jobs7']);

        $this->assertSearchFinds('50%', $percent);
        $this->assertSearchFinds('C:\\jobs', $backslash);
    }

    public function test_search_ignores_soft_deleted_product_types(): void
    {
        $this->actingAsRole('Admin');
        $product = ProductType::factory()->create(['name' => 'Obsolete Widget']);
        $this->order(['product_type_id' => $product->id]);
        $product->delete();

        $this->getJson('/api/v1/work-orders?search=obsolete')->assertOk()->assertExactJson(['data' => []]);
    }

    private function assertSearchFinds(string $term, WorkOrder $expected): void
    {
        $response = $this->getJson('/api/v1/work-orders?search='.urlencode($term))->assertOk();

        $this->assertSame([$expected->id], array_column($response->json('data'), 'id'));
    }

    // ── Due-date range ──────────────────────────────────────────────────────

    public function test_due_date_range_is_inclusive(): void
    {
        $this->actingAsRole('Admin');
        $early = $this->order(['due_date' => '2026-03-01 08:00:00']);
        $inside = $this->order(['due_date' => '2026-03-10 23:30:00']);
        $edge = $this->order(['due_date' => '2026-03-15 06:00:00']);
        $this->order(['due_date' => '2026-03-16 00:00:00']);
        $this->order(['due_date' => null]);

        $response = $this->getJson('/api/v1/work-orders?due_from=2026-03-10&due_to=2026-03-15')->assertOk();
        $this->assertEqualsCanonicalizing([$inside->id, $edge->id], array_column($response->json('data'), 'id'));

        $response = $this->getJson('/api/v1/work-orders?due_to=2026-03-09')->assertOk();
        $this->assertSame([$early->id], array_column($response->json('data'), 'id'));
    }

    public function test_due_date_range_is_validated(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/work-orders?due_from=not-a-date')->assertUnprocessable()->assertJsonValidationErrors('due_from');
        $this->getJson('/api/v1/work-orders?due_from=2026-03-10&due_to=2026-03-01')->assertUnprocessable()->assertJsonValidationErrors('due_to');
    }

    public function test_due_dates_must_be_strict_iso_dates(): void
    {
        $this->actingAsRole('Admin');

        // Accepted by the loose `date` rule, but ambiguous (Postgres reads the
        // first as 3 Oct) or relative: both must be rejected.
        $this->getJson('/api/v1/work-orders?due_from=10.03.2026')->assertUnprocessable()->assertJsonValidationErrors('due_from');
        $this->getJson('/api/v1/work-orders?due_to='.urlencode('2026-03-10 +1 week'))->assertUnprocessable()->assertJsonValidationErrors('due_to');
        $this->getJson('/api/v1/work-orders?due_from=2026-03-10T00:00:00')->assertUnprocessable()->assertJsonValidationErrors('due_from');
        $this->getJson('/api/v1/work-orders?due_from=2026-03-10&due_to=2026-03-10')->assertOk();
    }

    // ── Lean listing ────────────────────────────────────────────────────────

    public function test_include_limits_the_loaded_relations(): void
    {
        $this->actingAsRole('Admin');
        $this->orderWithBatchSteps();

        $row = $this->getJson('/api/v1/work-orders?include=line,product_type')->assertOk()->json('data.0');

        $this->assertArrayHasKey('line', $row);
        $this->assertArrayHasKey('product_type', $row);
        $this->assertArrayNotHasKey('batches', $row);
    }

    public function test_include_batches_omits_steps(): void
    {
        $this->actingAsRole('Admin');
        $this->orderWithBatchSteps();

        $row = $this->getJson('/api/v1/work-orders?include=batches')->assertOk()->json('data.0');

        $this->assertCount(1, $row['batches']);
        $this->assertArrayNotHasKey('steps', $row['batches'][0]);
        $this->assertArrayNotHasKey('line', $row);
        $this->assertArrayNotHasKey('product_type', $row);
    }

    public function test_include_accepts_array_syntax(): void
    {
        $this->actingAsRole('Admin');
        $this->orderWithBatchSteps();

        $row = $this->getJson('/api/v1/work-orders?include[]=product_type&include[]=batches.steps')->assertOk()->json('data.0');

        $this->assertArrayHasKey('product_type', $row);
        $this->assertCount(2, $row['batches'][0]['steps']);
        $this->assertArrayNotHasKey('line', $row);
    }

    public function test_default_relations_equal_the_explicit_include_list(): void
    {
        // The default comes from WorkOrderService::LIST_RELATIONS; every one of
        // them must be requestable, and asking for them explicitly must give the
        // same response as asking for nothing.
        $this->assertEmpty(array_diff(WorkOrderService::LIST_RELATIONS, ListWorkOrdersRequest::INCLUDES));

        $this->actingAsRole('Admin');
        $this->orderWithBatchSteps();
        $this->order();

        $tokens = array_keys(array_intersect(ListWorkOrdersRequest::INCLUDES, WorkOrderService::LIST_RELATIONS));
        $explicit = $this->getJson('/api/v1/work-orders?include='.implode(',', $tokens))->assertOk()->json();

        $this->getJson('/api/v1/work-orders')->assertOk()->assertExactJson($explicit);
    }

    public function test_unknown_include_is_rejected(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/work-orders?include=line,issues')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('include.1');
    }

    // ── Combined filters ────────────────────────────────────────────────────

    public function test_new_parameters_combine_with_the_existing_filters(): void
    {
        $this->actingAsRole('Admin');
        $lineA = Line::factory()->create();
        $lineB = Line::factory()->create();

        $hit = $this->order(['order_no' => 'WO-GAMMA-1', 'line_id' => $lineA->id, 'status' => WorkOrder::STATUS_PENDING, 'due_date' => '2026-05-05']);
        $this->order(['order_no' => 'WO-GAMMA-2', 'line_id' => $lineB->id, 'status' => WorkOrder::STATUS_PENDING, 'due_date' => '2026-05-05']);
        $this->order(['order_no' => 'WO-GAMMA-3', 'line_id' => $lineA->id, 'status' => WorkOrder::STATUS_DONE, 'due_date' => '2026-05-05']);
        $this->order(['order_no' => 'WO-GAMMA-4', 'line_id' => $lineA->id, 'status' => WorkOrder::STATUS_PENDING, 'due_date' => '2026-06-05']);
        $this->order(['order_no' => 'WO-DELTA-5', 'line_id' => $lineA->id, 'status' => WorkOrder::STATUS_PENDING, 'due_date' => '2026-05-05']);

        $response = $this->getJson("/api/v1/work-orders?search=gamma&status=PENDING&line_id={$lineA->id}&due_to=2026-05-31&include=line&page=1&per_page=10")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame([$hit->id], array_column($response->json('data'), 'id'));
        $this->assertArrayNotHasKey('batches', $response->json('data.0'));
    }

    public function test_operator_scope_still_applies_with_search_and_paging(): void
    {
        $operator = $this->actingAsRole('Operator');
        $mine = Line::factory()->create();
        $other = Line::factory()->create();
        $operator->lines()->attach($mine->id);

        $visible = $this->order(['order_no' => 'WO-SCOPE-1', 'line_id' => $mine->id]);
        $this->order(['order_no' => 'WO-SCOPE-2', 'line_id' => $other->id]);

        $response = $this->getJson('/api/v1/work-orders?search=scope&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertSame([$visible->id], array_column($response->json('data'), 'id'));
    }

    // ── Validation of legacy filters / authorization ────────────────────────

    public function test_status_of_the_wrong_type_is_rejected_with_a_translated_message(): void
    {
        $this->actingAsRole('Admin');

        // Only a JSON body can carry a non-string, non-array status.
        $this->json('GET', '/api/v1/work-orders', ['status' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'The status must be a string or a list of strings.']);

        $this->assertSame(
            'Status musi być tekstem lub listą tekstów.',
            __('The status must be a string or a list of strings.', [], 'pl'),
        );
    }

    public function test_non_numeric_line_id_is_rejected(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/work-orders?line_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('line_id');
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/work-orders?page=1&search=x')->assertUnauthorized();
    }

    public function test_user_without_a_role_is_forbidden(): void
    {
        $this->actingAsRole(null);

        $this->getJson('/api/v1/work-orders')->assertForbidden();
        // Authorization is decided before the query string is validated.
        $this->getJson('/api/v1/work-orders?page=0&per_page=999')->assertForbidden();
    }
}
