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

    public function test_index_reports_manage_capability_for_the_ui(): void
    {
        // A manager sees can_manage = true (upload/lifecycle controls shown)…
        $this->actingAs($this->admin)
            ->getJson('/api/v1/engineering-documents')
            ->assertOk()
            ->assertJsonPath('can_manage', true);

        // …a view-only operator sees can_manage = false.
        $this->actingAs($this->operator)
            ->getJson('/api/v1/engineering-documents')
            ->assertOk()
            ->assertJsonPath('can_manage', false);
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
            // A CAD blob is a forced download served as an inert octet-stream, never
            // the sniffed mime type.
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Disposition', 'attachment; filename=PART-1000.step');
    }

    public function test_inline_image_is_served_with_a_fixed_safe_content_type(): void
    {
        // A real 1x1 PNG (content matches the .png extension).
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $id = $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', [
                'file' => UploadedFile::fake()->createWithContent('drawing.png', $png),
                'entity_type' => 'material', 'entity_id' => $this->material->id, 'revision' => 'A',
            ])->assertCreated()->json('data.id');

        $this->actingAs($this->admin)
            ->get("/api/v1/engineering-documents/{$id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=drawing.png');
    }

    public function test_html_payload_disguised_as_an_image_is_rejected(): void
    {
        // A file whose real (sniffed) type is text/html but whose name claims .png.
        // In production `getMimeType()` sniffs content via finfo; here we pin the
        // fake's mime to text/html to reproduce that. Must be rejected at upload so
        // it can never be served as active content (stored-XSS guard).
        $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', [
                'file' => UploadedFile::fake()->create('drawing.png', 1, 'text/html'),
                'entity_type' => 'material', 'entity_id' => $this->material->id, 'revision' => 'A',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('engineering_documents', 0);
    }

    public function test_released_then_obsoleted_document_still_cannot_be_deleted(): void
    {
        $doc = EngineeringDocument::factory()->create(['entity_type' => 'material', 'entity_id' => $this->material->id]);

        $this->actingAs($this->admin)->postJson("/api/v1/engineering-documents/{$doc->id}/release")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/v1/engineering-documents/{$doc->id}/obsolete")
            ->assertOk()->assertJsonPath('data.lifecycle_status', 'obsolete');

        // The release -> obsolete -> delete bypass must be closed.
        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/engineering-documents/{$doc->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('engineering_documents', ['id' => $doc->id, 'deleted_at' => null]);
    }

    public function test_obsolete_document_cannot_be_released_again(): void
    {
        $doc = EngineeringDocument::factory()->create(['entity_type' => 'material', 'entity_id' => $this->material->id]);

        $this->actingAs($this->admin)->postJson("/api/v1/engineering-documents/{$doc->id}/release")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/v1/engineering-documents/{$doc->id}/obsolete")->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/engineering-documents/{$doc->id}/release")
            ->assertStatus(422);
    }

    public function test_storage_path_is_not_exposed_over_the_api(): void
    {
        $doc = EngineeringDocument::factory()->create(['entity_type' => 'material', 'entity_id' => $this->material->id]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/engineering-documents/{$doc->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.storage_path');
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
