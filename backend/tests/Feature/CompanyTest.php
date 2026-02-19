<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_companies(): void
    {
        Company::create([
            'code'      => 'CO001',
            'name'      => 'Acme Supplier',
            'type'      => Company::TYPE_SUPPLIER,
            'is_active' => true,
        ]);
        Company::create([
            'code'      => 'CO002',
            'name'      => 'Globex Customer',
            'type'      => Company::TYPE_CUSTOMER,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.companies.index'));

        $response->assertStatus(200);
        $response->assertSee('Acme Supplier');
        $response->assertSee('Globex Customer');
    }

    public function test_admin_can_create_company(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.companies.store'), [
            'code'      => 'CO001',
            'name'      => 'Test Supplier Ltd',
            'type'      => 'supplier',
            'email'     => 'contact@testsupplier.com',
            'phone'     => '+48123456789',
            'address'   => 'ul. Testowa 1, Warszawa',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('companies', [
            'code' => 'CO001',
            'name' => 'Test Supplier Ltd',
            'type' => 'supplier',
        ]);
    }

    public function test_admin_can_create_company_of_each_type(): void
    {
        foreach (['supplier', 'customer', 'both'] as $index => $type) {
            $code = 'CO00' . ($index + 1);
            $response = $this->actingAs($this->admin)->post(route('admin.companies.store'), [
                'code'      => $code,
                'name'      => "Company {$type}",
                'type'      => $type,
                'is_active' => true,
            ]);

            $response->assertRedirect(route('admin.companies.index'));
            $this->assertDatabaseHas('companies', ['code' => $code, 'type' => $type]);
        }
    }

    public function test_admin_can_update_company(): void
    {
        $company = Company::create([
            'code'      => 'CO001',
            'name'      => 'Old Company Name',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.companies.update', $company), [
            'code'      => 'CO001',
            'name'      => 'New Company Name',
            'type'      => 'both',
            'email'     => 'new@company.com',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('companies', [
            'id'   => $company->id,
            'name' => 'New Company Name',
            'type' => 'both',
        ]);
    }

    public function test_admin_can_toggle_active(): void
    {
        $company = Company::create([
            'code'      => 'CO001',
            'name'      => 'Active Company',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.companies.toggle-active', $company));

        $response->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('companies', [
            'id'        => $company->id,
            'is_active' => false,
        ]);

        // Toggle again — should restore active state.
        $this->actingAs($this->admin)
            ->post(route('admin.companies.toggle-active', $company));

        $this->assertDatabaseHas('companies', [
            'id'        => $company->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_company(): void
    {
        $company = Company::create([
            'code'      => 'CO001',
            'name'      => 'Deletable Company',
            'type'      => 'customer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.companies.destroy', $company));

        $response->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    public function test_company_code_must_be_unique(): void
    {
        Company::create([
            'code'      => 'CO001',
            'name'      => 'Existing Company',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.companies.store'), [
            'code'      => 'CO001',
            'name'      => 'Duplicate Company',
            'type'      => 'customer',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_company_name_is_required(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.companies.store'), [
            'code'      => 'CO001',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_company_type_must_be_valid(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.companies.store'), [
            'code'      => 'CO001',
            'name'      => 'Bad Type Company',
            'type'      => 'invalid_type',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_company_email_must_be_valid_if_provided(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.companies.store'), [
            'code'      => 'CO001',
            'name'      => 'Company With Bad Email',
            'type'      => 'supplier',
            'email'     => 'not-an-email',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_update_allows_same_code_for_same_company(): void
    {
        $company = Company::create([
            'code'      => 'CO001',
            'name'      => 'Original Name',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.companies.update', $company), [
            'code'      => 'CO001',
            'name'      => 'Updated Name',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.companies.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_guest_cannot_access_companies(): void
    {
        $response = $this->get(route('admin.companies.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_company(): void
    {
        $response = $this->post(route('admin.companies.store'), [
            'code'      => 'CO001',
            'name'      => 'Ghost Company',
            'type'      => 'supplier',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('companies', ['code' => 'CO001']);
    }
}
