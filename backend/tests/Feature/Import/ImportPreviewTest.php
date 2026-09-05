<?php

namespace Tests\Feature\Import;

use App\Import\Importers\ProductTypeImporter;
use App\Models\User;
use App\Services\Import\ParseHealth;
use App\Services\Import\RowMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The mapping screen's preview: re-reading an upload with different parse
 * settings, the warnings that name a wrong separator or encoding, and the
 * per-cell check that marks values the import would reject.
 */
class ImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findOrCreate('Admin', 'web'));
    }

    /** Put a file on the fake disk and remember it the way upload() does. */
    private function remember(string $contents, ?string $token = null, string $ext = 'csv'): string
    {
        $token ??= str_repeat('a', 32);
        $path = "imports/{$token}.{$ext}";
        Storage::disk('local')->put($path, $contents);

        session()->put('import.uploads.'.$token, [
            'entity' => 'product_types',
            'file_path' => $path,
            'original_filename' => 'products.'.$ext,
            'file_options' => ['delimiter' => 'auto', 'encoding' => 'utf-8'],
            'options' => ['strategy' => 'update_or_create'],
            'mapping' => null,
        ]);

        return $token;
    }

    public function test_changing_the_separator_re_reads_the_stored_file(): void
    {
        $token = $this->remember("code;name;category\r\nA;Alpha;FG\r\n");

        // Read as commas the whole line is one column; as semicolons, three.
        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'comma', 'encoding' => 'utf-8'])
            ->assertOk()
            ->assertJsonCount(1, 'headers');

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'semicolon', 'encoding' => 'utf-8'])
            ->assertOk()
            ->assertJsonPath('headers', ['code', 'name', 'category'])
            ->assertJsonPath('totalRows', 1);
    }

    public function test_the_previewed_settings_are_what_the_run_will_use(): void
    {
        $token = $this->remember("code;name\r\nA;Alpha\r\n");

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'semicolon', 'encoding' => 'windows-1250'])
            ->assertOk();

        // process() reads the remembered options, so a preview that did not
        // persist them would import with the settings the user rejected.
        $this->assertSame(
            ['delimiter' => 'semicolon', 'encoding' => 'windows-1250'],
            session('import.uploads.'.$token)['file_options'],
        );
    }

    public function test_a_wrong_separator_is_reported_with_the_one_that_works(): void
    {
        $token = $this->remember("code;name;category\r\nA;Alpha;FG\r\n");

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'comma', 'encoding' => 'utf-8'])
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'delimiter_single_column')
            ->assertJsonPath('warnings.0.fix.delimiter', 'semicolon');
    }

    public function test_a_file_that_is_not_utf8_is_reported_with_the_encoding_that_reads_it(): void
    {
        // "Zażółć" as Windows-1250 bytes. Written literally: the bundled mbstring
        // knows no CP1250 (which is why the reader converts with iconv), and a
        // lone 0xBF is invalid UTF-8 on its own — exactly the case the check is for.
        $token = $this->remember("code,name\r\nA,Za\xBF\xF3\xB3\xE6\r\n");

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'comma', 'encoding' => 'utf-8'])
            ->assertOk()
            ->assertJsonPath('warnings.0.code', 'encoding_not_utf8')
            ->assertJsonPath('warnings.0.fix.encoding', 'windows-1250');
    }

    public function test_a_correctly_read_file_raises_no_warning(): void
    {
        $token = $this->remember("code,name\r\nA,Zażółć\r\n");

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'comma', 'encoding' => 'utf-8'])
            ->assertOk()
            ->assertJsonPath('warnings', []);
    }

    public function test_cells_the_import_would_reject_are_reported_by_row_and_column(): void
    {
        $token = $this->remember("code,name,is_active\r\nA,Alpha,yes\r\nB,Beta,perhaps\r\n");

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", [
                'delimiter' => 'comma',
                'encoding' => 'utf-8',
                'mapping' => ['code' => 'code', 'name' => 'name', 'is_active' => 'is_active'],
            ])
            ->assertOk()
            // Row 0 is clean; row 1's "perhaps" is not a boolean.
            ->assertJsonMissingPath('problems.0')
            ->assertJsonStructure(['problems' => ['1' => ['is_active']]]);
    }

    public function test_run_options_changed_after_the_upload_are_persisted(): void
    {
        $token = $this->remember("code,name\r\nA,Alpha\r\n");

        // The file is uploaded as soon as it is picked, so options set after
        // that only reach the session through the preview call on submit.
        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", [
                'delimiter' => 'comma',
                'encoding' => 'utf-8',
                'options' => ['strategy' => 'skip_existing', 'external_system' => 'pantheon', 'only_categories' => 'FG'],
            ])
            ->assertOk();

        $this->assertSame(
            ['strategy' => 'skip_existing', 'external_system' => 'pantheon', 'only_categories' => 'FG'],
            session('import.uploads.'.$token)['options'],
        );
    }

    public function test_preview_rejects_a_separator_the_reader_does_not_know(): void
    {
        $token = $this->remember("code,name\r\nA,Alpha\r\n");

        $this->actingAs($this->admin)
            ->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'pipe', 'encoding' => 'utf-8'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('delimiter');
    }

    public function test_preview_is_gone_when_the_upload_is(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/import/product-types/preview/'.str_repeat('z', 32), ['delimiter' => 'comma', 'encoding' => 'utf-8'])
            ->assertStatus(410);
    }

    public function test_a_guest_cannot_preview(): void
    {
        $token = $this->remember("code,name\r\nA,Alpha\r\n");

        $this->postJson("/admin/import/product-types/preview/{$token}", ['delimiter' => 'comma', 'encoding' => 'utf-8'])
            ->assertStatus(401);
    }

    public function test_row_mapper_reports_every_bad_cell_not_just_the_first(): void
    {
        $problems = app(RowMapper::class)->problems(
            ['code' => 'A', 'is_active' => 'perhaps', 'unit_of_measure' => 'pcs'],
            ['code' => 'code', 'is_active' => 'is_active', 'unit_of_measure' => 'unit_of_measure'],
            app(ProductTypeImporter::class),
        );

        // map() throws on the first; the preview needs them all at once.
        $this->assertSame(['is_active'], array_keys($problems));
        $this->assertNotEmpty($problems['is_active']);
    }

    public function test_a_blank_cell_is_never_a_problem(): void
    {
        $problems = app(RowMapper::class)->problems(
            ['code' => 'A', 'is_active' => ''],
            ['code' => 'code', 'is_active' => 'is_active'],
            app(ProductTypeImporter::class),
        );

        // Blank means "leave alone on update", not a rejected value.
        $this->assertSame([], $problems);
    }

    public function test_encoding_is_never_questioned_for_a_spreadsheet(): void
    {
        // .xlsx carries its own encoding, so neither setting can be wrong.
        Storage::disk('local')->put('imports/x.xlsx', "\x50\x4b\x03\x04not really a zip");

        $warnings = app(ParseHealth::class)->inspect(
            Storage::disk('local')->path('imports/x.xlsx'),
            ['delimiter' => 'comma', 'encoding' => 'utf-8'],
            ['headers' => ['code'], 'rows' => [['code' => 'A']], 'total' => 1],
        );

        $this->assertSame([], $warnings);
    }
}
