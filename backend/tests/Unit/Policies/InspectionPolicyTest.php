<?php

namespace Tests\Unit\Policies;

use App\Models\Inspection;
use App\Models\User;
use App\Policies\InspectionPolicy;
use App\Support\InspectionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InspectionPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * granted permissions, may perform (view/create/record/complete), may decide
     *
     * @return array<string, array{list<string>, bool, bool}>
     */
    public static function grants(): array
    {
        return [
            'nothing' => [[], false, false],
            'inspector' => [[InspectionPermissions::PERFORM], true, false],
            'disposition includes inspector' => [[InspectionPermissions::DISPOSITION], true, true],
            'both' => [InspectionPermissions::ALL, true, true],
        ];
    }

    #[DataProvider('grants')]
    public function test_abilities(array $permissions, bool $perform, bool $decide): void
    {
        foreach (InspectionPermissions::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $inspection = new Inspection;
        $policy = new InspectionPolicy;

        $this->assertSame($perform, $policy->viewAny($user));
        $this->assertSame($perform, $policy->view($user, $inspection));
        $this->assertSame($perform, $policy->create($user));
        $this->assertSame($perform, $policy->recordResult($user, $inspection));
        $this->assertSame($perform, $policy->complete($user, $inspection));
        $this->assertSame($decide, $policy->disposition($user, $inspection));
    }

    public function test_missing_permission_rows_deny_instead_of_throwing(): void
    {
        // An installation that has not re-seeded yet has no such permissions.
        $user = User::factory()->create();

        $this->assertFalse((new InspectionPolicy)->create($user));
        $this->assertFalse((new InspectionPolicy)->disposition($user, new Inspection));
    }
}
