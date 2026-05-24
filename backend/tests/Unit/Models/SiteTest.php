<?php

namespace Tests\Unit\Models;

use App\Models\Area;
use App\Models\Company;
use App\Models\Line;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_has_many_areas(): void
    {
        $site = Site::factory()->create();
        $a1 = Area::factory()->create(['site_id' => $site->id]);
        $a2 = Area::factory()->create(['site_id' => $site->id]);
        Area::factory()->create(); // unrelated

        $this->assertCount(2, $site->areas);
        $this->assertEqualsCanonicalizing(
            [$a1->id, $a2->id],
            $site->areas->pluck('id')->all()
        );
    }

    public function test_site_belongs_to_company(): void
    {
        $company = Company::create([
            'code' => 'C-1', 'name' => 'Acme', 'type' => 'both', 'is_active' => true,
        ]);
        $site = Site::factory()->create(['company_id' => $company->id]);

        $this->assertSame($company->id, $site->company->id);
    }

    public function test_site_lines_through_areas(): void
    {
        $site = Site::factory()->create();
        $area = Area::factory()->create(['site_id' => $site->id]);
        $line = Line::factory()->create(['area_id' => $area->id]);
        // Orphan line on a different area: must not appear
        Line::factory()->create();

        $this->assertCount(1, $site->lines);
        $this->assertSame($line->id, $site->lines->first()->id);
    }

    public function test_site_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b']);

        $user = User::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($user);

        Site::create([
            'name' => 'A-Site', 'code' => 'A-SITE-1', 'tenant_id' => $tenantA->id, 'is_active' => true,
        ]);
        Site::create([
            'name' => 'B-Site', 'code' => 'B-SITE-1', 'tenant_id' => $tenantB->id, 'is_active' => true,
        ]);

        // Default scope limits to tenantA only.
        $this->assertSame(1, Site::query()->count());
        $this->assertSame('A-Site', Site::first()->name);
    }

    public function test_area_workstations_through_lines_relation_exists(): void
    {
        $area = Area::factory()->create();
        // Sanity: relation object resolves without error
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasManyThrough::class,
            $area->workstations()
        );
    }
}
