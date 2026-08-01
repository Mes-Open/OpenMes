<?php

namespace Tests\Feature\Api\V1;

use App\Models\EngineeringDocument;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Engineering CAD documents (#179) — Phase 1: upload, list, download, lifecycle,
 * authorization and immutability of released documents.
 */
class EngineeringDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');

        $this->material = Material::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'file' => UploadedFile::fake()->create('PART-1000.step', 200, 'model/step'),
            'entity_type' => 'material',
            'entity_id' => $this->material->id,
            'revision' => 'A',
            'document_type' => 'model',
        ], $overrides);
    }

    public function test_admin_uploads_a_document_and_checksum_is_stored(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', $this->payload())
            ->assertCreated();

        $id = $res->json('data.id');
        $doc = EngineeringDocument::find($id);

        $this->assertSame('material', $doc->entity_type);
        $this->assertSame($this->material->id, $doc->entity_id);
        $this->assertSame('neutral_cad', $doc->package_type->value);
        $this->assertSame('draft', $doc->lifecycle_status->value);
        $this->assertNotEmpty($doc->checksum);
        Storage::disk('local')->assertExists($doc->storage_path);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', $this->payload([
                'file' => UploadedFile::fake()->create('malware.exe', 10),
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('engineering_documents', 0);
    }

    public function test_upload_for_a_missing_owner_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', $this->payload(['entity_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('entity_id');
    }

    public function test_operator_can_view_but_not_upload(): void
    {
        EngineeringDocument::factory()->create(['entity_type' => 'material', 'entity_id' => $this->material->id]);

        // View allowed.
        $this->actingAs($this->operator)
            ->getJson('/api/v1/engineering-documents?entity_type=material&entity_id='.$this->material->id)
            ->assertOk();

        // Upload forbidden (no manage permission).
        $this->actingAs($this->operator)
            ->postJson('/api/v1/engineering-documents', $this->payload())
            ->assertForbidden();
    }

    public function test_guest_cannot_list(): void
    {
        $this->getJson('/api/v1/engineering-documents')->assertUnauthorized();
    }

    public function test_download_streams_with_nosniff_and_attachment(): void
    {
        $res = $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', $this->payload())
            ->assertCreated();
        $id = $res->json('data.id');

        $this->actingAs($this->operator)
            ->get("/api/v1/engineering-documents/{$id}/download")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename="PART-1000.step"');
    }

    public function test_release_makes_it_immutable_and_blocks_delete(): void
    {
        $doc = EngineeringDocument::factory()->create(['entity_type' => 'material', 'entity_id' => $this->material->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/engineering-documents/{$doc->id}/release")
            ->assertOk()
            ->assertJsonPath('data.lifecycle_status', 'released');

        // A released document cannot be deleted.
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/engineering-documents/{$doc->id}")
            ->assertStatus(422);
    }

    public function test_draft_can_be_soft_deleted(): void
    {
        $doc = EngineeringDocument::factory()->create(['entity_type' => 'material', 'entity_id' => $this->material->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/engineering-documents/{$doc->id}")
            ->assertOk();

        $this->assertSoftDeleted($doc);
    }
}
