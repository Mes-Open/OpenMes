<?php

namespace Tests\Feature\Packaging;

use App\Models\Batch;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\DownloadName;
use Database\Seeders\LabelTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A number with a slash ("WO/2026/0001") names a label download: the file
 * name is made safe instead of the response failing on its header.
 */
class LabelFileNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_labels_of_numbers_with_slashes_download_with_a_safe_name(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $this->seed(LabelTemplatesSeeder::class);
        $order = WorkOrder::factory()->create(['order_no' => 'WO/2026/0001']);
        $batch = Batch::factory()->create(['work_order_id' => $order->id, 'lot_number' => 'LOT 26/001']);

        $this->actingAs($operator)->get(route('packaging.labels.work-order.pdf', $order))
            ->assertOk()->assertHeader('content-disposition', 'inline; filename=label-wo-WO_2026_0001.pdf');
        $this->actingAs($operator)->get(route('packaging.labels.work-order.zpl', $order))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename=label-wo-WO_2026_0001.zpl');
        $this->actingAs($operator)->get(route('packaging.labels.finished-goods.pdf', $batch))->assertOk();

        $this->assertSame('file', DownloadName::safe('///'));
        $this->assertSame('A_B-1.2', DownloadName::safe(' A\\B-1.2 '));
    }
}
