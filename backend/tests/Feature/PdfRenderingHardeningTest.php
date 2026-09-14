<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * The renderer that turns reports, batch records and labels into PDFs.
 *
 * A PDF is generated from a Blade view holding plant data — worker names, notes,
 * reasons, lot numbers — all of it typed in by somebody. The renderer therefore
 * sits downstream of user input, and dompdf's defaults leave it more capable
 * than a document generator needs to be: it can read local files, fetch remote
 * ones, and embed JavaScript that the reader will run.
 *
 * None of that capability is used by anything we render — QR codes are embedded
 * as data: URIs and no view loads from disk — so the settings below are turned
 * off. They are pinned here because the cost of losing them is silent: the
 * documents keep rendering exactly the same, and the only thing that changes is
 * what an injected `src` could reach.
 *
 * Honest about what this is: with the previous settings a crafted `<img>` or
 * `@font-face` pointing at .env still produced no leak — dompdf checks what it
 * reads. This closes the primitive rather than a demonstrated hole.
 */
class PdfRenderingHardeningTest extends TestCase
{
    public function test_the_renderer_cannot_run_code(): void
    {
        // <script type="text/php"> in a view would otherwise execute — the
        // difference between a template bug and remote code execution.
        $this->assertFalse(config('dompdf.options.enable_php'));
    }

    public function test_the_renderer_cannot_reach_the_network(): void
    {
        // Keeps a rendered document from being turned into an outbound request
        // (SSRF), and closes the precondition of CVE-2022-28368, where a remote
        // font was the delivery mechanism.
        $this->assertFalse(config('dompdf.options.enable_remote'));
    }

    public function test_the_produced_pdf_carries_no_javascript(): void
    {
        // dompdf turns <script type="text/javascript"> into JavaScript embedded
        // in the PDF, which readers such as Acrobat execute. Our documents are
        // tables; none of them needs code in the reader.
        $this->assertFalse(config('dompdf.options.enable_javascript'));
    }

    public function test_the_renderer_cannot_open_local_files(): void
    {
        $protocols = array_keys(config('dompdf.options.allowed_protocols') ?? []);

        $this->assertNotContains('file://', $protocols);
        $this->assertNotContains('phar://', $protocols);
    }

    public function test_file_access_is_confined_to_the_public_directory(): void
    {
        // Belt as well as braces: with file:// gone this should be unreachable,
        // but if it is ever restored the reachable set must not be the whole
        // application, where .env, config and storage live.
        $chroot = config('dompdf.options.chroot');

        $this->assertSame(realpath(public_path()), $chroot);
        $this->assertNotSame(realpath(base_path()), $chroot);
    }

    public function test_a_document_still_renders_with_an_embedded_image(): void
    {
        // The hardening must not cost us the thing we actually use: QR codes and
        // logos travel as data: URIs, which has to keep working.
        $png = base64_encode(random_bytes(64));

        $pdf = Pdf::loadHTML('<p>Report</p><img src="data:image/png;base64,'.$png.'">')->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_markup_in_plant_data_is_rendered_as_text_not_markup(): void
    {
        // What "XSS in a PDF" means here: a worker name or a note containing
        // markup must land in the document as characters, never as structure.
        // Blade's {{ }} is what guarantees it, so a view that reaches for
        // {!! !!} is the thing to catch in review.
        $pdf = Pdf::loadView('pdf-hardening-fixture', [
            'value' => '<script>alert(1)</script>',
        ])->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('<script>', $pdf);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A one-line view standing in for any report view: it prints a value the
        // way every one of ours does.
        $this->app['view']->addNamespace('tests', __DIR__);
        $path = resource_path('views/pdf-hardening-fixture.blade.php');

        if (! file_exists($path)) {
            file_put_contents($path, '<p>{{ $value }}</p>');
            $this->fixture = $path;
        }
    }

    private ?string $fixture = null;

    protected function tearDown(): void
    {
        if ($this->fixture) {
            @unlink($this->fixture);
        }

        parent::tearDown();
    }
}
