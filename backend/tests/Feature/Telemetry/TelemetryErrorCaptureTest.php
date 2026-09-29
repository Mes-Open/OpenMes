<?php

namespace Tests\Feature\Telemetry;

use App\Exceptions\InsufficientStockException;
use App\Models\Material;
use App\Models\MaterialType;
use App\Services\Telemetry\TelemetryErrorBuffer;
use App\Support\TelemetrySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The boundary that matters most: errors are counted, never quoted.
 *
 * OpenMES throws exceptions that name production data — the material short in
 * stock, the product type an import could not match, the SQL a query failed on
 * together with its bound values. None of that may leave the customer's site,
 * and the guarantee has to hold for exceptions nobody has written yet. So the
 * buffer never reads a message at all, and these tests prove it by throwing the
 * real exceptions with real data in them.
 */
class TelemetryErrorCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite runs with telemetry off (phpunit.xml). Turn the machinery on
        // for these tests only — the point is to exercise the real capture path.
        config()->set('telemetry.enabled', true);
        $this->markInstalled();
        // Opt-in jest teraz domyslne, wiec przypadek, ktory cwiczy sciezke
        // raportowania, musi ja wlaczyc wprost. Wczesniej brak wiersza znaczyl
        // zgode i testy korzystaly z tego milczaco.
        TelemetrySettings::put(TelemetrySettings::SETTING_KEY, true);
        TelemetrySettings::forget();
        TelemetryErrorBuffer::clear();
    }

    private function markInstalled(): void
    {
        $path = storage_path('installed');
        if (! is_file($path)) {
            @file_put_contents($path, date('Y-m-d H:i:s'));
        }
    }

    private function payload(): string
    {
        return json_encode(TelemetryErrorBuffer::collect(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function test_a_stock_shortage_never_reports_the_material_it_was_short_of(): void
    {
        // The message reads: Insufficient stock for material "Bolt M8" (BOLT-M8-001)…
        // The code is the part that survives naive redaction, and it tells a
        // competitor what the factory makes.
        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $material = Material::create([
            'code' => 'BOLT-M8-001',
            'name' => 'Bolt M8 galvanised',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'pcs',
        ]);

        TelemetryErrorBuffer::record(new InsufficientStockException($material, 500.0, 12.0));

        $payload = $this->payload();

        $this->assertStringNotContainsString('BOLT-M8-001', $payload);
        $this->assertStringNotContainsString('Bolt M8', $payload);
        $this->assertStringNotContainsString('Insufficient stock', $payload);
        $this->assertStringContainsString('InsufficientStockException', $payload, 'The class is the useful part and must survive.');
    }

    public function test_a_failed_query_never_reports_the_sql_or_its_bindings(): void
    {
        // QueryException interpolates bindings into getMessage(), so a failed
        // lookup for a lot number puts that lot number in the exception text.
        try {
            DB::select('select * from a_table_that_does_not_exist where lot = ?', ['LOT-2026-SECRET']);
            $this->fail('Expected the query to fail.');
        } catch (\Throwable $e) {
            TelemetryErrorBuffer::record($e);
        }

        $payload = $this->payload();

        $this->assertStringNotContainsString('LOT-2026-SECRET', $payload);
        $this->assertStringNotContainsString('a_table_that_does_not_exist', $payload);
        $this->assertStringNotContainsString('select', strtolower($payload));
    }

    public function test_no_buffered_error_carries_a_message_field_at_all(): void
    {
        // Structural, not per-case: whatever is thrown, the shape has no room
        // for prose. A future contributor adding one would fail here.
        TelemetryErrorBuffer::record(new \RuntimeException('Lot LOT-99 for customer Acme Ltd is already closed'));

        $collected = TelemetryErrorBuffer::collect();

        $this->assertCount(1, $collected['items']);
        $this->assertSame(
            ['fingerprint', 'class', 'file', 'line', 'count', 'first_seen', 'last_seen'],
            array_keys($collected['items'][0]),
        );
        $this->assertStringNotContainsString('Acme', $this->payload());
        $this->assertStringNotContainsString('LOT-99', $this->payload());
    }

    public function test_repeated_errors_collapse_into_one_banded_count(): void
    {
        for ($i = 0; $i < 30; $i++) {
            TelemetryErrorBuffer::record(new \RuntimeException('same place'));
        }

        $collected = TelemetryErrorBuffer::collect();

        $this->assertCount(1, $collected['items'], 'Identical faults are one entry, not thirty.');
        $this->assertSame('10-49', $collected['items'][0]['count'], 'Counts are bands, never exact.');
    }

    public function test_an_error_storm_cannot_inflate_the_payload(): void
    {
        // A fingerprint is class+file+line, so a loop throwing the same class
        // from the same line makes one entry however many times it runs. To
        // reach the cap the classes have to genuinely differ — hence the
        // generated ones. Without this the test would pass while proving
        // nothing about the cap.
        $max = (int) config('telemetry.max_fingerprints');

        for ($i = 0; $i < $max * 2; $i++) {
            $class = 'TelemetryStormException'.$i;
            if (! class_exists($class, false)) {
                eval("class {$class} extends \\RuntimeException {}");
            }
            TelemetryErrorBuffer::record(new $class('storm'));
        }

        $collected = TelemetryErrorBuffer::collect();

        $this->assertCount($max, $collected['items'], 'The cap must hold.');
        $this->assertNotSame(
            '0',
            $collected['overflow_count'],
            'What was dropped is still reported, as a band — otherwise a storm looks like calm.',
        );
    }

    public function test_the_ordinary_working_of_the_app_is_not_reported_as_a_fault(): void
    {
        // A 404, a rejected form and a failed login are the app behaving. If
        // these landed in the buffer the real faults would be invisible.
        TelemetryErrorBuffer::record(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException);
        TelemetryErrorBuffer::record(new \Illuminate\Auth\AuthenticationException);
        TelemetryErrorBuffer::record(\Illuminate\Validation\ValidationException::withMessages(['x' => 'y']));
        TelemetryErrorBuffer::record(new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'nope'));

        $this->assertSame([], TelemetryErrorBuffer::collect()['items']);
    }

    public function test_a_fault_is_located_in_our_code_not_in_the_framework(): void
    {
        TelemetryErrorBuffer::record(new \RuntimeException('thrown from the test'));

        $item = TelemetryErrorBuffer::collect()['items'][0];

        $this->assertStringStartsWith('tests/', $item['file'], 'The frame should be ours, and relative.');
        $this->assertStringNotContainsString(base_path(), $item['file'], 'Absolute paths can carry a home directory name.');
        $this->assertGreaterThan(0, $item['line']);
    }

    public function test_nothing_is_recorded_while_telemetry_is_switched_off(): void
    {
        config()->set('telemetry.enabled', false);

        TelemetryErrorBuffer::record(new \RuntimeException('should not be kept'));

        $this->assertSame([], TelemetryErrorBuffer::collect()['items']);
    }
}
