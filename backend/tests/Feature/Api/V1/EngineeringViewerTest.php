<?php

namespace Tests\Feature\Api\V1;

use App\Models\EngineeringDocument;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

/**
 * #179 Phase 3 — interactive-HTML packages are validated + extracted on upload,
 * and served only through a short-lived signed URL behind a strict CSP.
 */
class EngineeringViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->material = Material::factory()->create();
    }

    private function zip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'engzip').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'ASSEMBLY-1000-B-viewer.zip', 'application/zip', null, true);
    }

    private function upload(UploadedFile $file, array $extra = []): int
    {
        return $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', array_merge([
                'file' => $file,
                'entity_type' => 'material',
                'entity_id' => $this->material->id,
                'revision' => 'B',
                'entry_point' => 'index.html',
            ], $extra))
            ->assertCreated()
            ->json('data.id');
    }

    public function test_valid_package_is_extracted_with_entry_point(): void
    {
        $id = $this->upload($this->zip([
            'index.html' => '<html><body>viewer</body></html>',
            'viewer.js' => 'console.log(1)',
            'assets/style.css' => 'body{}',
        ]));

        $doc = EngineeringDocument::find($id);
        $this->assertSame('interactive_html', $doc->package_type->value);
        $this->assertSame('index.html', $doc->entry_point);
        $this->assertGreaterThan(0, $doc->extracted_size);
        Storage::disk('local')->assertExists("engineering/interactive/{$doc->id}/index.html");
        Storage::disk('local')->assertExists("engineering/interactive/{$doc->id}/assets/style.css");
    }

    public function test_zip_slip_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', [
                'file' => $this->zip(['index.html' => 'ok', '../escape.html' => 'evil']),
                'entity_type' => 'material', 'entity_id' => $this->material->id, 'revision' => 'B',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('engineering_documents', 0);
    }

    public function test_disallowed_inner_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/engineering-documents', [
                'file' => $this->zip(['index.html' => 'ok', 'shell.php' => '<?php system($_GET[0]);']),
                'entity_type' => 'material', 'entity_id' => $this->material->id, 'revision' => 'B',
            ])
            ->assertStatus(422);
    }

    public function test_viewer_url_is_issued_and_serves_the_package_with_csp(): void
    {
        $id = $this->upload($this->zip(['index.html' => '<html>MODEL</html>']));

        $url = $this->actingAs($this->admin)
            ->getJson("/api/v1/engineering-documents/{$id}/viewer-url")
            ->assertOk()
            ->json('data.url');

        // The signed URL serves the entry point (no auth session needed).
        $res = $this->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString("default-src 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('MODEL', $res->getContent());
    }

    public function test_viewer_requires_a_valid_signature(): void
    {
        $doc = EngineeringDocument::factory()->create([
            'entity_type' => 'material', 'entity_id' => $this->material->id,
            'package_type' => 'interactive_html', 'entry_point' => 'index.html',
        ]);

        // Unsigned request is rejected by the `signed` middleware.
        $this->get("/engineering/viewer/{$doc->id}/index.html")->assertForbidden();
    }

    public function test_non_interactive_document_has_no_viewer(): void
    {
        $doc = EngineeringDocument::factory()->create([
            'entity_type' => 'material', 'entity_id' => $this->material->id,
        ]); // neutral_cad by default

        $this->actingAs($this->admin)
            ->getJson("/api/v1/engineering-documents/{$doc->id}/viewer-url")
            ->assertStatus(422);
    }

    public function test_viewer_blocks_path_traversal(): void
    {
        $id = $this->upload($this->zip(['index.html' => 'ok']));
        $doc = EngineeringDocument::find($id);

        // A signed URL pointing at a traversal path must 404, not escape the dir.
        $url = URL::temporarySignedRoute('engineering.viewer', now()->addMinutes(5), [
            'engineeringDocument' => $doc->id, 'path' => '../../../.env',
        ]);

        $this->get($url)->assertNotFound();
    }
}
