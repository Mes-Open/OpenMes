<?php

namespace Tests\Feature\Telemetry;

use App\Services\Telemetry\Buckets;
use App\Services\Telemetry\TelemetrySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The promise made to the customer, held to by machine.
 *
 * The installer and the settings page both say: OpenMES reports on the
 * software and never on anything you entered into it. Documentation goes stale;
 * this does not. It seeds a plant's worth of real-looking data — bakery
 * products, material codes, lot numbers, staff accounts — builds the actual
 * payload, and reads the resulting JSON back looking for any of it.
 *
 * The forbidden values are gathered from the database rather than hard-coded,
 * so a column added to a seeder next year is covered without anyone
 * remembering to extend this test.
 */
class TelemetryPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('telemetry.enabled', true);

        $path = storage_path('installed');
        if (! is_file($path)) {
            @file_put_contents($path, date('Y-m-d H:i:s'));
        }
    }

    private function seedAPlant(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\MaterialTypesSeeder::class);
        $this->seed(\Database\Seeders\BakeryDemoSeeder::class);
    }

    private function payloadJson(): string
    {
        return json_encode(
            (new TelemetrySnapshot)->build(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Text columns whose contents are the customer's, gathered live.
     *
     * @return array<string, list<string>> value => where it came from
     */
    private function forbiddenValues(): array
    {
        $sources = [
            'users' => ['username', 'email', 'name'],
            'materials' => ['code', 'name'],
            'product_types' => ['code', 'name'],
            'work_orders' => ['order_number'],
            'lines' => ['name'],
            'workstations' => ['code', 'name'],
            'process_templates' => ['name'],
            'material_lots' => ['lot_number'],
            'batches' => ['lot_number'],
        ];

        $forbidden = [];

        foreach ($sources as $table => $columns) {
            foreach ($columns as $column) {
                try {
                    $values = DB::table($table)->pluck($column)->all();
                } catch (\Throwable) {
                    continue; // column or table absent in this schema
                }

                foreach ($values as $value) {
                    // Very short strings produce false positives against JSON
                    // punctuation and band labels; they are also not
                    // identifying on their own.
                    if (is_string($value) && strlen(trim($value)) >= 4) {
                        $forbidden[trim($value)] = "{$table}.{$column}";
                    }
                }
            }
        }

        return $forbidden;
    }

    public function test_the_payload_contains_nothing_the_customer_entered(): void
    {
        $this->seedAPlant();

        $json = $this->payloadJson();
        $forbidden = $this->forbiddenValues();

        $this->assertNotEmpty($forbidden, 'The seeder produced nothing to check against — this test would be vacuous.');

        foreach ($forbidden as $value => $origin) {
            $this->assertStringNotContainsString(
                $value,
                $json,
                "Telemetry leaked {$origin}: \"{$value}\". The payload may describe the software only.",
            );
        }
    }

    public function test_the_payload_contains_no_email_address_at_all(): void
    {
        $this->seedAPlant();

        $this->assertDoesNotMatchRegularExpression(
            '/[\w.+-]+@[\w-]+\.[\w.]+/',
            $this->payloadJson(),
            'Something that looks like an email address reached the payload.',
        );
    }

    public function test_every_count_leaves_as_a_band_and_never_as_a_number(): void
    {
        $this->seedAPlant();

        $usage = (new TelemetrySnapshot)->build()['usage'];
        $labels = Buckets::labels();

        $walk = function (array $node, string $path) use (&$walk, $labels) {
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $walk($value, "{$path}.{$key}");

                    continue;
                }

                $this->assertContains(
                    $value,
                    $labels,
                    "usage{$path}.{$key} is \"{$value}\", which is not a band. An exact count describes the customer's throughput.",
                );
            }
        };

        $walk($usage, '');
    }

    public function test_the_payload_describes_the_software(): void
    {
        // The counterweight to the tests above: having established that it
        // carries nothing of the customer's, confirm it is still worth sending.
        $this->seedAPlant();

        $payload = (new TelemetrySnapshot)->build();

        $this->assertNotNull($payload['install_id']);
        $this->assertSame(1, $payload['schema_version']);
        $this->assertSame(config('version.current'), $payload['app']['version']);
        $this->assertSame(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, $payload['runtime']['php']);
        $this->assertNotNull($payload['runtime']['db_driver']);
        $this->assertNotNull($payload['runtime']['db_version']);
        $this->assertIsArray($payload['features']['modules']);
        $this->assertArrayHasKey('mqtt', $payload['usage']['machine_connections_by_protocol']);
        $this->assertNotSame('0', $payload['usage']['work_orders'], 'A seeded plant should register as used.');
    }

    public function test_an_installation_age_is_reported_but_not_its_birthday(): void
    {
        $payload = (new TelemetrySnapshot)->build();

        $this->assertIsInt($payload['app']['installed_days']);
        $this->assertStringNotContainsString(
            date('Y-m-d', filemtime(storage_path('installed'))),
            json_encode($payload['app']),
            'The install date is the customer project’s; only its age is ours to ask.',
        );
    }

    public function test_the_report_survives_a_database_that_will_not_answer(): void
    {
        // A snapshot taken while the database is in trouble must still produce
        // something sendable, because that is exactly the moment we would most
        // like to hear from the installation.
        DB::shouldReceive('table')->andThrow(new \RuntimeException('database is gone'));
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('database is gone'));

        $payload = (new TelemetrySnapshot)->build();

        $this->assertNotNull($payload['install_id']);
        $this->assertNotNull($payload['runtime']['php'], 'Facts about the host do not need the database.');
    }
}
